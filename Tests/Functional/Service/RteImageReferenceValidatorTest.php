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
}
