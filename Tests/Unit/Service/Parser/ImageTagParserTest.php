<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service\Parser;

use Netresearch\RteCKEditorImage\Service\Parser\ImageTagParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for ImageTagParser.
 */
#[CoversClass(ImageTagParser::class)]
final class ImageTagParserTest extends UnitTestCase
{
    private ImageTagParser $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ImageTagParser(new HtmlParser());
    }

    #[Test]
    public function splitByImageTagsReturnsArrayWithImgTagsAtOddIndices(): void
    {
        $html = '<p>Before</p><img src="test.jpg" /><p>After</p>';

        $result = $this->subject->splitByImageTags($html);

        self::assertCount(3, $result);
        self::assertSame('<p>Before</p>', $result[0]);
        self::assertStringContainsString('<img', $result[1]);
        self::assertSame('<p>After</p>', $result[2]);
    }

    #[Test]
    public function splitByImageTagsHandlesMultipleImages(): void
    {
        $html = '<img src="first.jpg" /><p>Middle</p><img src="second.jpg" />';

        $result = $this->subject->splitByImageTags($html);

        self::assertCount(5, $result);
        // Odd indices contain img tags
        self::assertStringContainsString('first.jpg', $result[1]);
        self::assertStringContainsString('second.jpg', $result[3]);
    }

    #[Test]
    public function splitByImageTagsReturnsOriginalHtmlWhenNoImages(): void
    {
        $html = '<p>No images here</p>';

        $result = $this->subject->splitByImageTags($html);

        self::assertCount(1, $result);
        self::assertSame($html, $result[0]);
    }

    #[Test]
    public function extractAttributesReturnsEmptyArrayForEmptyString(): void
    {
        $result = $this->subject->extractAttributes('');

        self::assertSame([], $result);
    }

    #[Test]
    public function extractAttributesParsesImgTagAttributes(): void
    {
        $imgTag = '<img src="test.jpg" alt="Test Image" width="300" height="200" />';

        $result = $this->subject->extractAttributes($imgTag);

        self::assertSame('test.jpg', $result['src']);
        self::assertSame('Test Image', $result['alt']);
        self::assertSame('300', $result['width']);
        self::assertSame('200', $result['height']);
    }

    #[Test]
    public function extractAttributesHandlesDataAttributes(): void
    {
        $imgTag = '<img src="test.jpg" data-htmlarea-file-uid="123" data-caption="My Caption" />';

        $result = $this->subject->extractAttributes($imgTag);

        self::assertSame('123', $result['data-htmlarea-file-uid']);
        self::assertSame('My Caption', $result['data-caption']);
    }

    #[Test]
    public function getDimensionExtractsWidthFromAttribute(): void
    {
        $attributes = ['width' => '300', 'height' => '200'];

        $result = $this->subject->getDimension($attributes, 'width');

        self::assertSame(300, $result);
    }

    #[Test]
    public function getDimensionExtractsHeightFromAttribute(): void
    {
        $attributes = ['width' => '300', 'height' => '200'];

        $result = $this->subject->getDimension($attributes, 'height');

        self::assertSame(200, $result);
    }

    #[Test]
    public function getDimensionPrefersStyleOverAttribute(): void
    {
        $attributes = [
            'width' => '100',
            'style' => 'width: 200px;',
        ];

        $result = $this->subject->getDimension($attributes, 'width');

        self::assertSame(200, $result, 'Style attribute value should take precedence');
    }

    #[Test]
    public function getDimensionReturnsZeroWhenNotFound(): void
    {
        $attributes = ['alt' => 'Image'];

        $result = $this->subject->getDimension($attributes, 'width');

        self::assertSame(0, $result);
    }

    #[Test]
    public function getDimensionHandlesStyleWithSpaces(): void
    {
        $attributes = ['style' => 'width  :   250  px;'];

        $result = $this->subject->getDimension($attributes, 'width');

        self::assertSame(250, $result);
    }

    #[Test]
    public function getDimensionIsCaseInsensitive(): void
    {
        $attributes = ['style' => 'WIDTH: 150px;'];

        $result = $this->subject->getDimension($attributes, 'width');

        self::assertSame(150, $result);
    }

    #[Test]
    public function normalizeImageSrcReturnsUnchangedForAbsoluteUrl(): void
    {
        $src     = 'https://example.com/image.jpg';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->normalizeImageSrc($src, $siteUrl, '');

        self::assertSame($src, $result);
    }

    #[Test]
    public function normalizeImageSrcMakesRelativePathAbsolute(): void
    {
        $src      = '/~user/fileadmin/image.jpg';
        $siteUrl  = 'https://mysite.com/~user/';
        $sitePath = '/~user/';

        $result = $this->subject->normalizeImageSrc($src, $siteUrl, $sitePath);

        self::assertSame('https://mysite.com/~user/fileadmin/image.jpg', $result);
    }

    #[Test]
    public function normalizeImageSrcTrimsWhitespace(): void
    {
        $src     = '  fileadmin/image.jpg  ';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->normalizeImageSrc($src, $siteUrl, '');

        self::assertSame('fileadmin/image.jpg', $result);
    }

    #[Test]
    public function calculateSitePathReturnsEmptyForEmptyRequestHost(): void
    {
        $result = $this->subject->calculateSitePath('https://mysite.com/subpath/', '');

        self::assertSame('', $result);
    }

    #[Test]
    public function calculateSitePathReturnsSitePath(): void
    {
        $siteUrl     = 'https://mysite.com/~user/';
        $requestHost = 'https://mysite.com';

        $result = $this->subject->calculateSitePath($siteUrl, $requestHost);

        self::assertSame('/~user/', $result);
    }

    #[Test]
    public function calculateSitePathReturnsEmptyForRootPath(): void
    {
        $siteUrl     = 'https://mysite.com/';
        $requestHost = 'https://mysite.com/';

        $result = $this->subject->calculateSitePath($siteUrl, $requestHost);

        self::assertSame('', $result);
    }
}
