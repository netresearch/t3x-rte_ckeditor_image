<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Listener\FileOperation;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileRenamedEvent;

/**
 * Updates stale `src` attributes in RTE content when FAL files are moved or renamed.
 *
 * When a file is renamed or moved in TYPO3's Filelist module, the stored HTML
 * contains both `data-htmlarea-file-uid` (tracked by soft reference parser) and
 * `src` (display URL, NOT tracked). This listener keeps `src` in sync with the
 * file's current public URL.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/612
 */
class UpdateImageReferences
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly HtmlParser $htmlParser,
        LogManager $logManager,
    ) {
        $this->logger = $logManager->getLogger(self::class);
    }

    /**
     * Handle file moved event.
     */
    public function handleFileMoved(AfterFileMovedEvent $event): void
    {
        $file = $event->getFile();

        if (!$file instanceof AbstractFile) {
            return;
        }

        $this->updateReferences($file);
    }

    /**
     * Handle file renamed event.
     */
    public function handleFileRenamed(AfterFileRenamedEvent $event): void
    {
        $file = $event->getFile();

        if (!$file instanceof AbstractFile) {
            return;
        }

        $this->updateReferences($file);
    }

    /**
     * Update all RTE image references for the given file.
     */
    private function updateReferences(AbstractFile $file): void
    {
        $fileUid = $file->getUid();

        if ($fileUid === 0) {
            return;
        }

        $publicUrl = $file->getPublicUrl();

        if ($publicUrl === null || $publicUrl === '') {
            $this->logger->debug('Skipping file without public URL', ['fileUid' => $fileUid]);

            return;
        }

        // Query sys_refindex for records referencing this file via rtehtmlarea_images
        $affectedRecords = $this->findAffectedRecords($fileUid);

        if ($affectedRecords === []) {
            return;
        }

        $updatedCount = 0;

        foreach ($affectedRecords as $record) {
            $tableName = $record['tablename'];
            $rawRecuid = $record['recuid'];
            $field     = $record['field'];

            if (!is_string($tableName) || !is_string($field)) {
                continue;
            }

            // Database drivers may return int or string for integer columns
            if (!is_int($rawRecuid) && !is_string($rawRecuid)) {
                continue;
            }

            $recuid = (int) $rawRecuid;

            $currentValue = $this->fetchFieldValue($tableName, $recuid, $field);

            if ($currentValue === null) {
                continue;
            }

            $updatedValue = $this->updateImageSrcInHtml($currentValue, $fileUid, $publicUrl);

            if ($updatedValue === $currentValue) {
                continue;
            }

            $this->writeFieldValue($tableName, $recuid, $field, $updatedValue);
            ++$updatedCount;
        }

        if ($updatedCount > 0) {
            $this->logger->info(
                'Updated image src references after file operation',
                ['fileUid' => $fileUid, 'updatedRecords' => $updatedCount, 'newUrl' => $publicUrl],
            );
        }
    }

    /**
     * Find records referencing the given file via rtehtmlarea_images soft reference.
     *
     * @return array<int, array<string, mixed>>
     */
    private function findAffectedRecords(int $fileUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_refindex');
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('tablename', 'recuid', 'field')
            ->from('sys_refindex')
            ->where(
                $queryBuilder->expr()->eq(
                    'softref_key',
                    $queryBuilder->createNamedParameter('rtehtmlarea_images'),
                ),
                $queryBuilder->expr()->eq(
                    'ref_table',
                    $queryBuilder->createNamedParameter('sys_file'),
                ),
                $queryBuilder->expr()->eq(
                    'ref_uid',
                    $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT),
                ),
            )
            ->groupBy('tablename', 'recuid', 'field')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Fetch the current field value from the database.
     *
     * Removes all restrictions to include hidden/deleted records that may be restored.
     */
    private function fetchFieldValue(string $tableName, int $recuid, string $field): ?string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($tableName);
        $queryBuilder->getRestrictions()->removeAll();

        $result = $queryBuilder
            ->select($field)
            ->from($tableName)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($recuid, Connection::PARAM_INT),
                ),
            )
            ->executeQuery()
            ->fetchOne();

        return is_string($result) ? $result : null;
    }

    /**
     * Write updated field value via direct SQL.
     *
     * Uses Connection::update() instead of DataHandler to avoid re-triggering
     * RteImagesDbHook and permission checks. This is an internal maintenance
     * operation that only updates the display URL.
     */
    private function writeFieldValue(string $tableName, int $recuid, string $field, string $value): void
    {
        $connection = $this->connectionPool->getConnectionForTable($tableName);
        $connection->update(
            $tableName,
            [$field => $value],
            ['uid'  => $recuid],
        );
    }

    /**
     * Update src attributes in HTML for img tags matching the given file UID.
     *
     * Uses the same HtmlParser::splitTags() + get_tag_attributes() pattern
     * as RteImageSoftReferenceParser for consistency.
     */
    private function updateImageSrcInHtml(string $html, int $fileUid, string $newSrc): string
    {
        $splitContent = $this->htmlParser->splitTags('img', $html);
        $changed      = false;

        foreach ($splitContent as $key => $part) {
            if (!is_string($part) || !str_starts_with($part, '<img')) {
                continue;
            }

            $attributes    = $this->htmlParser->get_tag_attributes($part);
            $tagAttributes = $attributes[0] ?? [];

            if (!is_array($tagAttributes)) {
                continue;
            }

            $uid = $tagAttributes['data-htmlarea-file-uid'] ?? '';

            if (!is_string($uid) || $uid !== (string) $fileUid) {
                continue;
            }

            $currentSrc = $tagAttributes['src'] ?? '';

            if (!is_string($currentSrc) || $currentSrc === $newSrc) {
                continue;
            }

            // Replace src attribute value in the tag
            if ($currentSrc !== '') {
                $splitContent[$key] = str_replace(
                    'src="' . $currentSrc . '"',
                    'src="' . $newSrc . '"',
                    $part,
                );
            }

            $changed = true;
        }

        if (!$changed) {
            return $html;
        }

        return implode('', $splitContent);
    }
}
