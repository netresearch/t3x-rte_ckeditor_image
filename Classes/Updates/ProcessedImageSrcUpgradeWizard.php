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
            $this->processTableColumn($table, $tableConfig, $connectionPool, $resourceFactory);
        }

        return true;
    }

    private function processTableColumn(string $table, array $tableConfig, ConnectionPool $connectionPool, ResourceFactory $resourceFactory) 
    {
        foreach ($tableConfig['columns'] ?? [] as $field => $fieldConfig) {
            if (!$this->isRelevantField($table, $field, $fieldConfig)) {
                continue;
            }

            $this->processField($table, $field, $fieldConfig, $connectionPool, $resourceFactory);
        }
    }

    private function isRelevantField(string $table, string $field, array $fieldConfig): bool
    {
        $isRelevantContentElement =
            $table === 'tt_content'
            && $field === 'bodytext';

        $isRichtextField =
            ($fieldConfig['config']['enableRichtext'] ?? false);

        return $isRelevantContentElement || $isRichtextField;
    }

    private function processField(string $table, string $field, array $fieldConfig, ConnectionPool $connectionPool, ResourceFactory $resourceFactory): void
    {
        $connection = $connectionPool->getConnectionForTable($table);
        $queryBuilder = $connection->createQueryBuilder();
        
        $this->buildQuery($queryBuilder, $table, $field);
        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();

        $this->updateRows($table, $field, $rows, $connection, $resourceFactory);
    }

    private function buildQuery(\TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder, string $table, string $field): void
    {
        $queryBuilder
            ->select('uid', $field)
            ->from($table);

        $isRelevantContentElement = $table === 'tt_content' && $field === 'bodytext';

        if ($isRelevantContentElement) {
            $this->addContentElementRestrictions($queryBuilder, $field);
        } else {
            $this->addGeneralRestrictions($queryBuilder, $field);
        }
    }

    private function addContentElementRestrictions(\TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder, string $field): void
    {
        $queryBuilder->where(
            $queryBuilder->expr()->in('CType', $queryBuilder->createNamedParameter(
                ['text', 'textmedia', 'textpic'],
                \Doctrine\DBAL\Connection::PARAM_STR_ARRAY
            )),
            $this->buildLikeExpression($queryBuilder, $field)
        );
    }

    private function addGeneralRestrictions(\TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder, string $field): void
    {
        $queryBuilder->where(
            $this->buildLikeExpression($queryBuilder, $field)
        );
    }

    private function buildLikeExpression(\TYPO3\CMS\Core\Database\Query\QueryBuilder $queryBuilder, string $field): \TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression
    {
        return $queryBuilder->expr()->andX(
            $queryBuilder->expr()->like($field, $queryBuilder->createNamedParameter('%data-htmlarea-file-uid%')),
            $queryBuilder->expr()->orX(
                $queryBuilder->expr()->like($field, $queryBuilder->createNamedParameter('%_processed_%')),
                $queryBuilder->expr()->like($field, $queryBuilder->createNamedParameter('%typo3/image/process%'))
            )
        );
    }

    private function updateRows(string $table, string $field, array $rows, \Doctrine\DBAL\Connection $connection, ResourceFactory $resourceFactory): void
    {
        foreach ($rows as $row) {
            $newContent = $this->replaceImageSources($row[$field] ?? '', $resourceFactory);
            if ($newContent !== $row[$field]) {
                $connection->update($table, [$field => $newContent], ['uid' => (int)$row['uid']]);
            }
        }
    }

    private function replaceImageSources(string $html, ResourceFactory $resourceFactory): string
    {
        $internalErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        foreach ($dom->getElementsByTagName('img') as $img) {
            $src = $img->getAttribute('src');
            $fileUid = (int)$img->getAttribute('data-htmlarea-file-uid');
            if ($fileUid > 0 && $this->isProcessedImage($src)) {
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

    private function isProcessedImage(string $src): bool
    {
        return str_contains($src, '_processed_') || str_contains($src, 'typo3/image/process');
    }

    public function getPrerequisites(): array
    {
        return [];
    }
}
