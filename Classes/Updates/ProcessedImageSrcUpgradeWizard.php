<?php

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Updates;

use DOMDocument;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class ProcessedImageSrcUpgradeWizard implements UpgradeWizardInterface
{
    public function getIdentifier(): string
    {
        return 'processedImageSrcUpgrade';
    }

    public function getTitle(): string
    {
        return 'Replace processed image src attributes';
    }

    public function getDescription(): string
    {
        return 'Replaces image sources of processed images in RTE fields with the original file URL.';
    }

    public function updateNecessary(): bool
    {
        return true;
    }

    public function executeUpdate(): bool
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        foreach ($GLOBALS['TCA'] as $table => $tableConfig) {
            foreach ($tableConfig['columns'] ?? [] as $field => $fieldConfig) {
                if (!($fieldConfig['config']['enableRichtext'] ?? false)) {
                    continue;
                }

                $connection = $connectionPool->getConnectionForTable($table);
                $queryBuilder = $connection->createQueryBuilder();
                $queryBuilder
                    ->select('uid', $field)
                    ->from($table)
                    ->where(
                        $queryBuilder->expr()->like($field, $queryBuilder->createNamedParameter('%_processed_%')),
                        $queryBuilder->expr()->like($field, $queryBuilder->createNamedParameter('%data-htmlarea-file-uid%'))
                    );

                $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

                foreach ($rows as $row) {
                    $newContent = $this->replaceImageSources($row[$field] ?? '', $resourceFactory);
                    if ($newContent !== $row[$field]) {
                        $connection->update($table, [$field => $newContent], ['uid' => (int)$row['uid']]);
                    }
                }
            }
        }

        return true;
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    private function replaceImageSources(string $html, ResourceFactory $resourceFactory): string
    {
        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($dom->getElementsByTagName('img') as $img) {
            $src = $img->getAttribute('src');
            $fileUid = (int)$img->getAttribute('data-htmlarea-file-uid');
            if ($fileUid > 0 && strpos($src, '_processed_') !== false) {
                try {
                    /** @var File $file */
                    $file = $resourceFactory->getFileObject($fileUid);
                    $img->setAttribute('src', (string)$file->getPublicUrl());
                } catch (FileDoesNotExistException) {
                    // ignore missing files
                }
            }
        }

        libxml_use_internal_errors($internalErrors);

        $result = '';
        $element = $dom->documentElement;
        if ($element !== null) {
            foreach ($element->childNodes as $child) {
                $result .= $dom->saveHTML($child);
            }
        }

        return $result === '' ? $html : $result;
    }
}
