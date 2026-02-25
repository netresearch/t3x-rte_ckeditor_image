<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Dto;

use Netresearch\RteCKEditorImage\Dto\ValidationIssueType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValidationIssueTypeTest extends TestCase
{
    #[Test]
    public function enumHasSixCases(): void
    {
        self::assertCount(6, ValidationIssueType::cases());
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
