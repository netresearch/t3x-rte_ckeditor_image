<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Dto;

use Netresearch\RteCKEditorImage\Dto\ValidationIssueType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValidationIssueTypeTest extends TestCase
{
    #[Test]
    public function enumHasFiveCases(): void
    {
        self::assertCount(5, ValidationIssueType::cases());
    }

    #[Test]
    public function enumValuesAreSnakeCase(): void
    {
        foreach (ValidationIssueType::cases() as $case) {
            self::assertMatchesRegularExpression('/^[a-z_]+$/', $case->value);
        }
    }

    #[Test]
    public function processedImageSrcHasExpectedValue(): void
    {
        self::assertSame('processed_image_src', ValidationIssueType::ProcessedImageSrc->value);
    }

    #[Test]
    public function canBeCreatedFromStringValue(): void
    {
        $type = ValidationIssueType::from('orphaned_file_uid');
        self::assertSame(ValidationIssueType::OrphanedFileUid, $type);
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        $type = ValidationIssueType::tryFrom('nonexistent');
        self::assertNull($type);
    }
}
