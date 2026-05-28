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
        // Canonical storage form is leading-slash site-root-absolute (#778, #837).
        // A slashless result would be a broken relative URL in rendered HTML.
        $src     = 'https://mysite.com/fileadmin/image.jpg';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('/fileadmin/image.jpg', $result);
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
        // Subpath installs (e.g. /~user/) store the site-root-relative form
        // ("/fileadmin/...") and rely on config.absRefPrefix to prepend the
        // subpath at render time. This keeps storage canonical across root
        // and subpath installs and aligns with TYPO3 dropping <base href>.
        $src     = 'https://mysite.com/~user/fileadmin/image.jpg';
        $siteUrl = 'https://mysite.com/~user/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('/fileadmin/image.jpg', $result);
    }

    #[Test]
    public function makeRelativeSrcNormalizesSlashlessLocalPath(): void
    {
        // A slashless src that never matched siteUrl is still a broken relative
        // URL — defensive normalization at this boundary catches values that
        // bypassed urlToRelative() in the editor JS or arrived via an import.
        $src     = 'fileadmin/image.jpg';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('/fileadmin/image.jpg', $result);
    }

    #[Test]
    public function makeRelativeSrcLeavesProtocolRelativeUrlUnchanged(): void
    {
        // Protocol-relative URLs (//cdn.example.com/...) are external references
        // and must not be coerced to site-root-relative.
        $src     = '//cdn.example.com/image.jpg';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('//cdn.example.com/image.jpg', $result);
    }

    #[Test]
    public function makeRelativeSrcLeavesDataUriUnchanged(): void
    {
        // Inline data: URIs are external — leave the scheme intact.
        $src     = 'data:image/gif;base64,R0lGODlhAQABAAAAACw=';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('data:image/gif;base64,R0lGODlhAQABAAAAACw=', $result);
    }

    #[Test]
    public function makeRelativeSrcCollapsesDoubleSlashAfterStrip(): void
    {
        // Defensive: an accidental "//" in the absolute URL (e.g. siteUrl ends
        // in "/" and the path also starts with "/") must not survive as a
        // protocol-relative URL in the stored src — that would silently turn a
        // same-site path into a cross-origin reference.
        $src     = 'https://mysite.com//fileadmin/image.jpg';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('/fileadmin/image.jpg', $result);
    }

    #[Test]
    public function makeRelativeSrcRejectsLeadingWhitespaceSmuggling(): void
    {
        // CWE-20/CWE-176 hardening: a leading-space + protocol-relative payload
        // (" //evil.com/x") would bypass the scheme-grammar guard because
        // "^//" cannot match past the space. Browsers strip ASCII whitespace
        // from <img src=""> per WHATWG URL, so the rendered HTML would resolve
        // "//evil.com/x" as a cross-origin reference. Trim defensively.
        $src     = ' //evil.com/x.jpg';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('//evil.com/x.jpg', $result, 'Trimmed input must be classified as protocol-relative external');
    }

    #[Test]
    public function makeRelativeSrcReturnsRootForExactSiteUrlMatch(): void
    {
        // Edge: when the src is exactly the siteUrl, the strip leaves an empty
        // path. The canonical site-root reference is "/" — not the slashless
        // empty string which would render broken.
        $src     = 'https://mysite.com/';
        $siteUrl = 'https://mysite.com/';

        $result = $this->subject->makeRelativeSrc($src, $siteUrl);

        self::assertSame('/', $result);
    }
}
