<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Service;

use Netresearch\RteCKEditorImage\Dto\ValidationIssue;
use Netresearch\RteCKEditorImage\Dto\ValidationIssueType;
use Netresearch\RteCKEditorImage\Service\RteImageReferenceValidator;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for RteImageReferenceValidator.
 *
 * Tests the full chain: sys_refindex lookup → HTML parsing → issue detection → SQL fix.
 */
class RteImageReferenceValidatorTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RteImageValidationImport.csv');
    }

    private function getSubject(): RteImageReferenceValidator
    {
        $subject = $this->get(RteImageReferenceValidator::class);
        self::assertInstanceOf(RteImageReferenceValidator::class, $subject);

        return $subject;
    }

    /**
     * Filter issues by type and return the first match.
     *
     * @param list<ValidationIssue> $issues
     */
    private function findFirstIssueOfType(array $issues, ValidationIssueType $type): ValidationIssue
    {
        $filtered = array_filter(
            $issues,
            static fn (ValidationIssue $issue): bool => $issue->type === $type,
        );
        self::assertNotEmpty($filtered, 'Expected at least one issue of type ' . $type->value);

        $first = reset($filtered);
        self::assertInstanceOf(ValidationIssue::class, $first);

        return $first;
    }

    #[Test]
    public function validateFindsProcessedImageSrc(): void
    {
        $result = $this->getSubject()->validate();
        $issue  = $this->findFirstIssueOfType($result->getIssues(), ValidationIssueType::ProcessedImageSrc);

        self::assertSame(1, $issue->uid);
        self::assertStringContainsString('_processed_', $issue->currentSrc ?? '');
    }

    #[Test]
    public function validateFindsSrcMismatch(): void
    {
        $result = $this->getSubject()->validate();
        $issue  = $this->findFirstIssueOfType($result->getIssues(), ValidationIssueType::SrcMismatch);

        self::assertSame(2, $issue->uid);
        self::assertSame('/fileadmin/old-location/photo.jpg', $issue->currentSrc);
    }

    #[Test]
    public function validateFindsOrphanedFileUid(): void
    {
        $result = $this->getSubject()->validate();
        $issue  = $this->findFirstIssueOfType($result->getIssues(), ValidationIssueType::OrphanedFileUid);

        self::assertSame(4, $issue->uid);
        self::assertSame(999, $issue->fileUid);
    }

    #[Test]
    public function validateReportsCleanRecordWithNoIssues(): void
    {
        $result = $this->getSubject()->validate();

        $record3Issues = array_filter(
            $result->getIssues(),
            static fn (ValidationIssue $issue): bool => $issue->uid === 3,
        );

        self::assertCount(0, $record3Issues);
    }

    #[Test]
    public function validateFindsBrokenSrc(): void
    {
        $result = $this->getSubject()->validate();
        $issue  = $this->findFirstIssueOfType($result->getIssues(), ValidationIssueType::BrokenSrc);

        self::assertSame(5, $issue->uid);
        self::assertSame(2, $issue->fileUid);
        self::assertSame('', $issue->currentSrc);
        self::assertTrue($issue->isFixable());
    }

    #[Test]
    public function validateReportsScanCounts(): void
    {
        $result = $this->getSubject()->validate();

        self::assertSame(5, $result->getScannedRecords());
        self::assertSame(5, $result->getScannedImages());
        self::assertGreaterThanOrEqual(4, count($result->getIssues()));
    }

    #[Test]
    public function validateLimitsToSpecificTable(): void
    {
        $result = $this->getSubject()->validate('nonexistent_table');

        self::assertFalse($result->hasIssues());
        self::assertSame(0, $result->getScannedRecords());
    }

    #[Test]
    public function fixUpdatesFixableRecords(): void
    {
        $validator = $this->getSubject();
        $result    = $validator->validate();

        self::assertTrue($result->hasIssues());

        $updatedCount = $validator->fix($result);

        // Records 1 (processed src), 2 (src mismatch), and 5 (broken src) should be fixed
        self::assertSame(3, $updatedCount);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/RteImageValidationFixResult.csv');
    }

    #[Test]
    public function fixPreservesCleanRecords(): void
    {
        $validator = $this->getSubject();
        $result    = $validator->validate();

        $validator->fix($result);

        // Re-validate: records 1, 2, and 5 should be clean now; record 4 (orphaned) still has issue
        $resultAfterFix = $validator->validate();
        $remainingTypes = array_map(
            static fn (ValidationIssue $issue): ValidationIssueType => $issue->type,
            $resultAfterFix->getIssues(),
        );

        self::assertNotContains(ValidationIssueType::ProcessedImageSrc, $remainingTypes);
        self::assertNotContains(ValidationIssueType::SrcMismatch, $remainingTypes);
        self::assertNotContains(ValidationIssueType::BrokenSrc, $remainingTypes);
        self::assertContains(ValidationIssueType::OrphanedFileUid, $remainingTypes);
    }

    #[Test]
    public function validateSkipsRefindexEntryForDeletedRecord(): void
    {
        // sys_refindex has hash07 pointing to tt_content uid=999, which doesn't exist.
        // fetchFieldValue() returns null → the record is skipped (not counted as scanned).
        $result = $this->getSubject()->validate();

        $record999Issues = array_filter(
            $result->getIssues(),
            static fn (ValidationIssue $issue): bool => $issue->uid === 999,
        );

        self::assertCount(0, $record999Issues);
        // uid=999 must not be counted as a scanned record
        self::assertSame(5, $result->getScannedRecords());
    }

    #[Test]
    public function validateSkipsRecordWithEmptyBodytext(): void
    {
        // tt_content uid=6 has empty bodytext; fetchFieldValue() returns '' → skipped.
        $result = $this->getSubject()->validate();

        $record6Issues = array_filter(
            $result->getIssues(),
            static fn (ValidationIssue $issue): bool => $issue->uid === 6,
        );

        self::assertCount(0, $record6Issues);
        // uid=6 must not be counted as a scanned record
        self::assertSame(5, $result->getScannedRecords());
    }

    #[Test]
    public function fixSkipsRecordDeletedBetweenValidateAndFix(): void
    {
        // Validate first to capture issues, then delete a record before fixing.
        $validator = $this->getSubject();
        $result    = $validator->validate();

        // Delete record uid=1 (processed src) from tt_content between validate and fix
        $connectionPool = $this->get(ConnectionPool::class);
        self::assertInstanceOf(ConnectionPool::class, $connectionPool);

        $connection = $connectionPool->getConnectionForTable('tt_content');
        $connection->delete('tt_content', ['uid' => 1]);

        $updatedCount = $validator->fix($result);

        // Only records 2 and 5 should be fixed; record 1 is gone (null), record 4 has no change
        self::assertSame(2, $updatedCount);
    }

    #[Test]
    public function fixSkipsOrphanedFileUidWithUnchangedHtml(): void
    {
        // Record uid=4 has OrphanedFileUid (fixable), but applyFixes() returns unchanged HTML
        // because expectedSrc is null, producing an empty fixMap.
        $validator = $this->getSubject();
        $result    = $validator->validate();

        // Verify the OrphanedFileUid issue exists and is fixable
        $orphanedIssues = array_filter(
            $result->getFixableIssues(),
            static fn (ValidationIssue $issue): bool => $issue->type === ValidationIssueType::OrphanedFileUid,
        );
        self::assertCount(1, $orphanedIssues);

        $updatedCount = $validator->fix($result);

        // Records 1, 2, 5 get fixed; record 4 (orphaned) goes through fix() but produces no change
        self::assertSame(3, $updatedCount);

        // Verify record 4's bodytext is unchanged
        $connectionPool = $this->get(ConnectionPool::class);
        self::assertInstanceOf(ConnectionPool::class, $connectionPool);

        $queryBuilder = $connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        $bodytext = $queryBuilder
            ->select('bodytext')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(4, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        self::assertSame(
            '<p><img data-htmlarea-file-table="sys_file" data-htmlarea-file-uid="999" src="/fileadmin/deleted.jpg" /></p>',
            $bodytext,
        );
    }
}
