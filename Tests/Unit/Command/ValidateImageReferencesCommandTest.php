<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Command;

use Netresearch\RteCKEditorImage\Command\ValidateImageReferencesCommand;
use Netresearch\RteCKEditorImage\Dto\ValidationIssue;
use Netresearch\RteCKEditorImage\Dto\ValidationIssueType;
use Netresearch\RteCKEditorImage\Dto\ValidationResult;
use Netresearch\RteCKEditorImage\Service\RteImageReferenceValidator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for ValidateImageReferencesCommand.
 *
 * Tests the command output and exit codes with a mocked validator.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ValidateImageReferencesCommand::class)]
final class ValidateImageReferencesCommandTest extends TestCase
{
    /** @var RteImageReferenceValidator&MockObject */
    private RteImageReferenceValidator $validator;

    private ValidateImageReferencesCommand $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->createMock(RteImageReferenceValidator::class);
        $this->command   = new ValidateImageReferencesCommand($this->validator);
    }

    private function createTester(): CommandTester
    {
        return new CommandTester($this->command);
    }

    private function createCleanResult(): ValidationResult
    {
        $result = new ValidationResult();
        $result->incrementScannedRecords();
        $result->incrementScannedRecords();
        $result->incrementScannedImages();
        $result->incrementScannedImages();
        $result->incrementScannedImages();

        return $result;
    }

    private function createResultWithFixableIssues(): ValidationResult
    {
        $result = new ValidationResult();
        $result->incrementScannedRecords();
        $result->incrementScannedImages();
        $result->incrementScannedImages();

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::SrcMismatch,
            table: 'tt_content',
            uid: 42,
            field: 'bodytext',
            fileUid: 1,
            currentSrc: '/fileadmin/old-location/image.jpg',
            expectedSrc: '/fileadmin/new-location/image.jpg',
            imgIndex: 0,
        ));

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::ProcessedImageSrc,
            table: 'tt_content',
            uid: 42,
            field: 'bodytext',
            fileUid: 2,
            currentSrc: '/fileadmin/_processed_/a/b/csm_photo_abc123.jpg',
            expectedSrc: '/fileadmin/photo.jpg',
            imgIndex: 1,
        ));

        return $result;
    }

    private function createResultWithOnlyNonFixableIssues(): ValidationResult
    {
        $result = new ValidationResult();
        $result->incrementScannedRecords();
        $result->incrementScannedImages();

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::MissingFileUid,
            table: 'tt_content',
            uid: 10,
            field: 'bodytext',
            fileUid: null,
            currentSrc: '/fileadmin/orphan.jpg',
            expectedSrc: null,
            imgIndex: 0,
        ));

        return $result;
    }

    #[Test]
    public function cleanRunReportsSuccessAndNoIssues(): void
    {
        $this->validator
            ->method('validate')
            ->with(null)
            ->willReturn($this->createCleanResult());

        $tester = $this->createTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No issues found', $output);
        self::assertStringContainsString('RTE Image Reference Validation', $output);
    }

    #[Test]
    public function cleanRunRendersScanSummary(): void
    {
        $result = $this->createCleanResult();

        $this->validator
            ->method('validate')
            ->willReturn($result);

        $tester = $this->createTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('Scanned records', $output);
        self::assertStringContainsString('2', $output);
        self::assertStringContainsString('Scanned images', $output);
        self::assertStringContainsString('3', $output);
        self::assertStringContainsString('Issues found', $output);
        self::assertStringContainsString('0', $output);
    }

    #[Test]
    public function dryRunWithIssuesReportsFailureAndIssueTable(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn($this->createResultWithFixableIssues());

        $tester = $this->createTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Dry-run mode', $output);
        self::assertStringContainsString('2 fixable issue(s)', $output);
        self::assertStringContainsString('src_mismatch', $output);
        self::assertStringContainsString('processed_image_src', $output);
        self::assertStringContainsString('tt_content', $output);
        self::assertStringContainsString('42', $output);
    }

    #[Test]
    public function fixModeWithFixableIssuesReportsSuccess(): void
    {
        $result = $this->createResultWithFixableIssues();

        $this->validator
            ->method('validate')
            ->willReturn($result);

        $this->validator
            ->method('fix')
            ->with($result)
            ->willReturn(1);

        $tester = $this->createTester();
        $tester->execute(['--fix' => true]);

        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Fixed 1 record(s)', $output);
        self::assertStringContainsString('2 fixable issues', $output);
    }

    #[Test]
    public function fixModeWithOnlyNonFixableIssuesReportsFailure(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn($this->createResultWithOnlyNonFixableIssues());

        $tester = $this->createTester();
        $tester->execute(['--fix' => true]);

        $output = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('No fixable issues found', $output);
    }

    #[Test]
    public function tableOptionLimitsScope(): void
    {
        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with('tx_news_domain_model_news')
            ->willReturn($this->createCleanResult());

        $tester = $this->createTester();
        $tester->execute(['--table' => 'tx_news_domain_model_news']);

        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Limiting scan to table: tx_news_domain_model_news', $output);
    }

    #[Test]
    public function issueTableRendersFileUidAndFixableColumn(): void
    {
        $result = new ValidationResult();
        $result->incrementScannedRecords();
        $result->incrementScannedImages();

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::OrphanedFileUid,
            table: 'tt_content',
            uid: 7,
            field: 'bodytext',
            fileUid: 999,
            currentSrc: '/fileadmin/gone.jpg',
            expectedSrc: null,
            imgIndex: 0,
        ));

        $this->validator
            ->method('validate')
            ->willReturn($result);

        $tester = $this->createTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('999', $output);
        self::assertStringContainsString('yes', $output);
        self::assertStringContainsString('orphaned_file_uid', $output);
    }

    #[Test]
    public function issueTableShowsDashForNullFileUid(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn($this->createResultWithOnlyNonFixableIssues());

        $tester = $this->createTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        // MissingFileUid issues have null fileUid, rendered as "-"
        self::assertStringContainsString('missing_file_uid', $output);
        self::assertStringContainsString('no', $output);
    }

    #[Test]
    public function issueTableTruncatesLongSrcValues(): void
    {
        $result = new ValidationResult();
        $result->incrementScannedRecords();
        $result->incrementScannedImages();

        $longSrc = '/fileadmin/user_upload/very/deep/nested/directory/structure/with/many/levels/image_final_v2.jpg';

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::SrcMismatch,
            table: 'tt_content',
            uid: 1,
            field: 'bodytext',
            fileUid: 1,
            currentSrc: $longSrc,
            expectedSrc: '/fileadmin/short.jpg',
            imgIndex: 0,
        ));

        $this->validator
            ->method('validate')
            ->willReturn($result);

        $tester = $this->createTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        // Long src (>50 chars) should be truncated with "..."
        self::assertStringContainsString('...', $output);
        // Full long path should NOT appear
        self::assertStringNotContainsString($longSrc, $output);
    }

    #[Test]
    public function issueTableShowsDashForNullExpectedSrc(): void
    {
        $result = new ValidationResult();
        $result->incrementScannedRecords();
        $result->incrementScannedImages();

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::OrphanedFileUid,
            table: 'tt_content',
            uid: 3,
            field: 'bodytext',
            fileUid: 99,
            currentSrc: null,
            expectedSrc: null,
            imgIndex: 0,
        ));

        $this->validator
            ->method('validate')
            ->willReturn($result);

        $tester = $this->createTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        // null currentSrc and expectedSrc rendered as "-"
        self::assertStringContainsString('orphaned_file_uid', $output);
    }

    #[Test]
    public function fixModeCallsValidatorFix(): void
    {
        $result = $this->createResultWithFixableIssues();

        $this->validator
            ->method('validate')
            ->willReturn($result);

        $this->validator
            ->expects(self::once())
            ->method('fix')
            ->with($result)
            ->willReturn(2);

        $tester = $this->createTester();
        $tester->execute(['--fix' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    #[Test]
    public function dryRunDoesNotCallFix(): void
    {
        $this->validator
            ->method('validate')
            ->willReturn($this->createResultWithFixableIssues());

        $this->validator
            ->expects(self::never())
            ->method('fix');

        $tester = $this->createTester();
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
    }
}
