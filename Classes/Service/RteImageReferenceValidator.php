<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service;

use Netresearch\RteCKEditorImage\Dto\ValidationIssue;
use Netresearch\RteCKEditorImage\Dto\ValidationIssueType;
use Netresearch\RteCKEditorImage\Dto\ValidationResult;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Validates and fixes image references in RTE content fields.
 *
 * Detects stale src attributes, orphaned file UIDs, missing file UIDs,
 * and processed image URLs that won't survive TYPO3 upgrades.
 *
 * Uses the same HtmlParser pattern as {@see \Netresearch\RteCKEditorImage\Listener\FileOperation\UpdateImageReferences}
 * and {@see \Netresearch\RteCKEditorImage\DataHandling\SoftReference\RteImageSoftReferenceParser}.
 */
class RteImageReferenceValidator
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ResourceFactory $resourceFactory,
        private readonly HtmlParser $htmlParser,
    ) {}

    /**
     * Scan all RTE fields and return validation issues.
     *
     * @param string|null $limitToTable Restrict scan to a specific table (e.g. 'tt_content')
     */
    public function validate(?string $limitToTable = null): ValidationResult
    {
        $result  = new ValidationResult();
        $records = $this->findAffectedRecords($limitToTable);

        foreach ($records as $record) {
            $tableName = $record['tablename'];
            $rawRecuid = $record['recuid'];
            $field     = $record['field'];

            if (!is_string($tableName)) {
                continue;
            }

            if (!is_string($field)) {
                continue;
            }

            if (!is_int($rawRecuid) && !is_string($rawRecuid)) {
                continue;
            }

            $recuid = (int) $rawRecuid;

            $currentValue = $this->fetchFieldValue($tableName, $recuid, $field);

            if ($currentValue === null) {
                continue;
            }

            if ($currentValue === '') {
                continue;
            }

            $result->incrementScannedRecords();
            $issues = $this->validateHtml($currentValue, $tableName, $recuid, $field, $result);

            foreach ($issues as $issue) {
                $result->addIssue($issue);
            }
        }

        return $result;
    }

    /**
     * Apply fixes for all fixable issues in the result.
     *
     * @return int Number of records updated
     */
    public function fix(ValidationResult $result): int
    {
        $fixableIssues = $result->getFixableIssues();

        if ($fixableIssues === []) {
            return 0;
        }

        // Group issues by table:uid:field for batch processing
        $grouped = $this->groupIssuesByRecord($fixableIssues);

        $updatedCount = 0;

        foreach ($grouped as $key => $issues) {
            [$tableName, $uid, $field] = explode(':', $key, 3);
            $recuid                    = (int) $uid;

            $currentValue = $this->fetchFieldValue($tableName, $recuid, $field);

            if ($currentValue === null) {
                continue;
            }

            $updatedValue = $this->applyFixes($currentValue, $issues);

            if ($updatedValue === $currentValue) {
                continue;
            }

            $this->writeFieldValue($tableName, $recuid, $field, $updatedValue);
            ++$updatedCount;
        }

        return $updatedCount;
    }

    /**
     * Find records with RTE image references via sys_refindex.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAffectedRecords(?string $limitToTable = null): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_refindex');
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
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
            )
            ->groupBy('tablename', 'recuid', 'field');

        if ($limitToTable !== null) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'tablename',
                    $queryBuilder->createNamedParameter($limitToTable),
                ),
            );
        }

        return $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Validate HTML content and return issues found.
     *
     * @return list<ValidationIssue>
     */
    public function validateHtml(
        string $html,
        string $table,
        int $uid,
        string $field,
        ?ValidationResult $result = null,
    ): array {
        $splitContent = $this->htmlParser->splitTags('img', $html);
        $issues       = [];
        $imgIndex     = 0;

        foreach ($splitContent as $part) {
            if (!is_string($part)) {
                continue;
            }

            if (!str_starts_with($part, '<img')) {
                continue;
            }

            $result?->incrementScannedImages();

            $attributes    = $this->htmlParser->get_tag_attributes($part);
            $tagAttributes = $attributes[0] ?? [];

            if (!is_array($tagAttributes)) {
                ++$imgIndex;

                continue;
            }

            $src     = $this->getStringAttribute($tagAttributes, 'src');
            $fileUid = $this->getStringAttribute($tagAttributes, 'data-htmlarea-file-uid');

            $issue = $this->detectIssue($src, $fileUid, $table, $uid, $field, $imgIndex);

            if ($issue instanceof ValidationIssue) {
                $issues[] = $issue;
            }

            ++$imgIndex;
        }

        return $issues;
    }

    /**
     * Detect what kind of issue (if any) exists for a single img tag.
     */
    private function detectIssue(
        ?string $src,
        ?string $fileUidStr,
        string $table,
        int $uid,
        string $field,
        int $imgIndex,
    ): ?ValidationIssue {
        // Missing file-uid attribute
        if ($fileUidStr === null || $fileUidStr === '') {
            return new ValidationIssue(
                type: ValidationIssueType::MissingFileUid,
                table: $table,
                uid: $uid,
                field: $field,
                fileUid: null,
                currentSrc: $src,
                expectedSrc: null,
                imgIndex: $imgIndex,
            );
        }

        $fileUid = (int) $fileUidStr;

        // Try to resolve the FAL file
        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException) {
            return new ValidationIssue(
                type: ValidationIssueType::OrphanedFileUid,
                table: $table,
                uid: $uid,
                field: $field,
                fileUid: $fileUid,
                currentSrc: $src,
                expectedSrc: null,
                imgIndex: $imgIndex,
            );
        }

        $publicUrl = $file->getPublicUrl();

        if ($publicUrl === null || $publicUrl === '') {
            return null;
        }

        // Check for processed image URL
        if ($src !== null && str_contains($src, '/_processed_/')) {
            return new ValidationIssue(
                type: ValidationIssueType::ProcessedImageSrc,
                table: $table,
                uid: $uid,
                field: $field,
                fileUid: $fileUid,
                currentSrc: $src,
                expectedSrc: $publicUrl,
                imgIndex: $imgIndex,
            );
        }

        // Check for src mismatch
        if (!in_array($src, [null, '', $publicUrl], true)) {
            return new ValidationIssue(
                type: ValidationIssueType::SrcMismatch,
                table: $table,
                uid: $uid,
                field: $field,
                fileUid: $fileUid,
                currentSrc: $src,
                expectedSrc: $publicUrl,
                imgIndex: $imgIndex,
            );
        }

        // Check for broken/empty src
        if ($src === null || $src === '') {
            return new ValidationIssue(
                type: ValidationIssueType::BrokenSrc,
                table: $table,
                uid: $uid,
                field: $field,
                fileUid: $fileUid,
                currentSrc: $src,
                expectedSrc: $publicUrl,
                imgIndex: $imgIndex,
            );
        }

        return null;
    }

    /**
     * Apply fixes to HTML content for the given issues.
     *
     * @param list<ValidationIssue> $issues
     */
    private function applyFixes(string $html, array $issues): string
    {
        // Build a map of fileUid => expectedSrc for quick lookup
        $fixMap = [];

        foreach ($issues as $issue) {
            if ($issue->fileUid !== null && $issue->expectedSrc !== null) {
                $fixMap[$issue->fileUid] = $issue->expectedSrc;
            }
        }

        if ($fixMap === []) {
            return $html;
        }

        $splitContent = $this->htmlParser->splitTags('img', $html);
        $changed      = false;

        foreach ($splitContent as $key => $part) {
            if (!is_string($part)) {
                continue;
            }

            if (!str_starts_with($part, '<img')) {
                continue;
            }

            $attributes    = $this->htmlParser->get_tag_attributes($part);
            $tagAttributes = $attributes[0] ?? [];

            if (!is_array($tagAttributes)) {
                continue;
            }

            $fileUidStr = $this->getStringAttribute($tagAttributes, 'data-htmlarea-file-uid');

            if ($fileUidStr === null) {
                continue;
            }

            $fileUid = (int) $fileUidStr;

            if (!isset($fixMap[$fileUid])) {
                continue;
            }

            $currentSrc = $this->getStringAttribute($tagAttributes, 'src');
            $newSrc     = $fixMap[$fileUid];

            if ($currentSrc === $newSrc) {
                continue;
            }

            if ($currentSrc === null) {
                // No src attribute exists — insert one after <img
                $splitContent[$key] = str_replace(
                    '<img ',
                    '<img src="' . $newSrc . '" ',
                    $part,
                );
            } else {
                // Replace existing src (empty or non-empty)
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

    /**
     * Fetch the current field value from the database.
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
     * Group fixable issues by table:uid:field key.
     *
     * @param list<ValidationIssue> $issues
     *
     * @return array<string, list<ValidationIssue>>
     */
    private function groupIssuesByRecord(array $issues): array
    {
        $grouped = [];

        foreach ($issues as $issue) {
            $key             = $issue->table . ':' . $issue->uid . ':' . $issue->field;
            $grouped[$key][] = $issue;
        }

        return $grouped;
    }

    /**
     * Safely extract a string attribute from parsed tag attributes.
     *
     * @param array<mixed, mixed> $attributes
     */
    private function getStringAttribute(array $attributes, string $name): ?string
    {
        $value = $attributes[$name] ?? null;

        return is_string($value) ? $value : null;
    }
}
