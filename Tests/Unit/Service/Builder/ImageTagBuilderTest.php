<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service\Builder;

use Netresearch\RteCKEditorImage\Service\Builder\ImageTagBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for ImageTagBuilder.
 */
#[CoversClass(ImageTagBuilder::class)]
final class ImageTagBuilderTest extends UnitTestCase
{
    private ImageTagBuilder $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ImageTagBuilder();
    }

    #[Test]
    public function buildCreatesImgTagWithAttributes(): void
    {
        $attributes = [
            'src'    => 'test.jpg',
            'alt'    => 'Test Image',
            'width'  => 300,
            'height' => 200,
        ];

        $result = $this->subject->build($attributes);

        self::assertStringContainsString('<img', $result);
        self::assertStringContainsString('src="test.jpg"', $result);
        self::assertStringContainsString('alt="Test Image"', $result);
        self::assertStringContainsString('width="300"', $result);
        self::assertStringContainsString('height="200"', $result);
        self::assertStringContainsString('/>', $result);
    }

    #[Test]
    public function buildAddsEmptyAltAttributeIfNotPresent(): void
    {
        $attributes = ['src' => 'test.jpg'];

        $result = $this->subject->build($attributes);

        self::assertStringContainsString('alt=""', $result);
    }

    #[Test]
    public function buildPreservesExistingAltAttribute(): void
    {
        $attributes = [
            'src' => 'test.jpg',
            'alt' => 'Custom Alt',
        ];

        $result = $this->subject->build($attributes);

        self::assertStringContainsString('alt="Custom Alt"', $result);
    }

    #[Test]
    public function buildRemovesWidthAndHeightFromStyle(): void
    {
        $attributes = [
            'src'   => 'test.jpg',
            'style' => 'width: 300px; height: 200px; border: 1px solid red;',
        ];

        $result = $this->subject->build($attributes);

        self::assertStringNotContainsString('width: 300px', $result);
        self::assertStringNotContainsString('height: 200px', $result);
        // Should still contain other style properties
        self::assertStringContainsString('border: 1px solid red', $result);
    }

    #[Test]
    public function buildRemovesEmptyStyleAttribute(): void
    {
        $attributes = [
            'src'   => 'test.jpg',
            'style' => 'width: 300px;',
        ];

        $result = $this->subject->build($attributes);

        // After removing width, style should be empty and thus removed
        self::assertStringNotContainsString('style=', $result);
    }

    #[Test]
    public function withProcessedImageUpdatesAttributes(): void
    {
        $attributes = [
            'src'    => 'original.jpg',
            'alt'    => 'Test',
            'width'  => 100,
            'height' => 100,
        ];

        $result = $this->subject->withProcessedImage(
            $attributes,
            300,
            200,
            'processed.jpg',
        );

        self::assertSame(300, $result['width']);
        self::assertSame(200, $result['height']);
        self::assertSame('processed.jpg', $result['src']);
        self::assertSame('Test', $result['alt']);
    }

    #[Test]
    public function withProcessedImageSetsFileUidWhenProvided(): void
    {
        $attributes = ['src' => 'test.jpg'];

        $result = $this->subject->withProcessedImage(
            $attributes,
            300,
            200,
            'processed.jpg',
            123,
        );

        self::assertSame(123, $result['data-htmlarea-file-uid']);
    }

    #[Test]
    public function withProcessedImageDoesNotSetFileUidWhenNull(): void
    {
        $attributes = ['src' => 'test.jpg'];

        $result = $this->subject->withProcessedImage(
            $attributes,
            300,
            200,
            'processed.jpg',
            null,
        );

        self::assertArrayNotHasKey('data-htmlarea-file-uid', $result);
    }

    #[Test]
    public function makeRelativeSrcRemovesSiteUrl(): void
    {
        $src     = 'https://mysite.com/fileadmin/image.jpg';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('fileadmin/image.jpg', $result);
    }

    #[Test]
    public function makeRelativeSrcReturnsUnchangedForExternalUrl(): void
    {
        $src     = 'https://example.com/image.jpg';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame($src, $result);
    }

    #[Test]
    public function makeRelativeSrcReturnsUnchangedForEmptySiteUrl(): void
    {
        $src     = 'fileadmin/image.jpg';
        $siteUrl = '';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame($src, $result);
    }

    #[Test]
    public function makeRelativeSrcHandlesSubpaths(): void
    {
        $src     = 'https://mysite.com/~user/fileadmin/image.jpg';
        $siteUrl = 'https://mysite.com/~user/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('fileadmin/image.jpg', $result);
    }
}
