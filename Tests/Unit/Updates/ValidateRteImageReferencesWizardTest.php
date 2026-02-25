<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Updates;

use Netresearch\RteCKEditorImage\Dto\ValidationIssue;
use Netresearch\RteCKEditorImage\Dto\ValidationIssueType;
use Netresearch\RteCKEditorImage\Dto\ValidationResult;
use Netresearch\RteCKEditorImage\Service\RteImageReferenceValidator;
use Netresearch\RteCKEditorImage\Updates\ValidateRteImageReferencesWizard;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;

/**
 * Unit tests for ValidateRteImageReferencesWizard.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ValidateRteImageReferencesWizard::class)]
final class ValidateRteImageReferencesWizardTest extends TestCase
{
    /** @var RteImageReferenceValidator&MockObject */
    private RteImageReferenceValidator $validator;

    private ValidateRteImageReferencesWizard $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->createMock(RteImageReferenceValidator::class);
        $this->subject   = new ValidateRteImageReferencesWizard($this->validator);
    }

    #[Test]
    public function getTitleReturnsNonEmptyString(): void
    {
        self::assertNotEmpty($this->subject->getTitle());
        self::assertSame('Validate RTE image references', $this->subject->getTitle());
    }

    #[Test]
    public function getDescriptionReturnsNonEmptyString(): void
    {
        $description = $this->subject->getDescription();

        self::assertNotEmpty($description);
        self::assertStringContainsString('image references', $description);
    }

    #[Test]
    public function updateNecessaryReturnsTrueWhenIssuesExist(): void
    {
        $result = new ValidationResult();
        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::SrcMismatch,
            table: 'tt_content',
            uid: 1,
            field: 'bodytext',
            fileUid: 1,
            currentSrc: '/old.jpg',
            expectedSrc: '/new.jpg',
            imgIndex: 0,
        ));

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->willReturn($result);

        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function updateNecessaryReturnsFalseWhenNoIssues(): void
    {
        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(new ValidationResult());

        self::assertFalse($this->subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateReturnsTrueAndCallsFixWhenIssuesExist(): void
    {
        $result = new ValidationResult();
        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::ProcessedImageSrc,
            table: 'tt_content',
            uid: 42,
            field: 'bodytext',
            fileUid: 5,
            currentSrc: '/fileadmin/_processed_/a/b/csm_photo.jpg',
            expectedSrc: '/fileadmin/photo.jpg',
            imgIndex: 0,
        ));

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->willReturn($result);

        $this->validator
            ->expects(self::once())
            ->method('fix')
            ->with($result)
            ->willReturn(1);

        self::assertTrue($this->subject->executeUpdate());
    }

    #[Test]
    public function executeUpdateReturnsTrueWithoutCallingFixWhenNoIssues(): void
    {
        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(new ValidationResult());

        $this->validator
            ->expects(self::never())
            ->method('fix');

        self::assertTrue($this->subject->executeUpdate());
    }

    #[Test]
    public function getPrerequisitesReturnsDatabaseUpdatedPrerequisite(): void
    {
        $prerequisites = $this->subject->getPrerequisites();

        self::assertCount(1, $prerequisites);
        self::assertSame(DatabaseUpdatedPrerequisite::class, $prerequisites[0]);
    }
}
