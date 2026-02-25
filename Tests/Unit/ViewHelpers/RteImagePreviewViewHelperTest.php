<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\ViewHelpers;

use Netresearch\RteCKEditorImage\ViewHelpers\RteImagePreviewViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test case for RteImagePreviewViewHelper.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
#[CoversClass(RteImagePreviewViewHelper::class)]
class RteImagePreviewViewHelperTest extends TestCase
{
    // ========================================================================
    // Basic tag stripping
    // ========================================================================

    #[Test]
    public function stripsHtmlToImgAndPTagsByDefault(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<div><p>Text</p><span>Span</span><script>alert("xss")</script><img src="test.jpg" /></div>',
        );

        self::assertStringContainsString('<p>Text</p>', $result);
        self::assertStringContainsString('<img', $result);
        self::assertStringNotContainsString('<div>', $result);
        self::assertStringNotContainsString('<span>', $result);
        self::assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function preservesImgAttributes(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<img src="image.jpg" alt="Test" width="800" height="600" /><p>Text</p>',
        );

        self::assertStringContainsString('<img src="image.jpg" alt="Test"', $result);
        self::assertStringContainsString('<p>Text</p>', $result);
    }

    #[Test]
    public function stripsAnchorTags(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<p><a href="http://example.com">Link</a></p>',
        );

        self::assertStringNotContainsString('<a', $result);
        self::assertStringContainsString('Link', $result);
    }

    // ========================================================================
    // Truncation
    // ========================================================================

    #[Test]
    public function truncatesAtDefaultMaxLength(): void
    {
        $input = '<p>' . str_repeat('Lorem ipsum dolor sit amet ', 100) . '</p>';

        $result = RteImagePreviewViewHelper::processHtml($input);

        self::assertStringContainsString('...', $result);
    }

    #[Test]
    public function customMaxLengthIsRespected(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<p>' . str_repeat('a', 100) . '</p>',
            50,
        );

        self::assertStringContainsString('...', $result);
    }

    #[Test]
    public function noTruncationWhenUnderLimit(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<p>Short text</p>',
        );

        self::assertStringContainsString('<p>Short text</p>', $result);
        self::assertStringNotContainsString('...', $result);
    }

    // ========================================================================
    // DOM-aware truncation preserves structure
    // ========================================================================

    #[Test]
    public function truncationPreservesHtmlStructure(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<p>Start <img src="test.jpg" alt="Image" /> Middle text</p>',
            100,
        );

        self::assertStringContainsString('<img', $result);
        self::assertStringContainsString('</p>', $result);
    }

    #[Test]
    public function removesNodesExceedingLimit(): void
    {
        $html = '<p>' . str_repeat('x', 100) . '</p><p>This should be removed</p>';

        $result = RteImagePreviewViewHelper::processHtml($html, 50);

        self::assertStringContainsString('...', $result);
        self::assertStringNotContainsString('This should be removed', $result);
    }

    // ========================================================================
    // Multi-byte UTF-8 handling
    // ========================================================================

    #[Test]
    public function handlesMultiByteCharacters(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<p>Müller Öffentlich Überprüfung</p>',
            20,
        );

        self::assertStringContainsString('...', $result);
    }

    #[Test]
    public function handlesUnicodeEmojis(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<p>Hello 👋 World 🌍</p>',
            100,
        );

        // DOMDocument may encode emojis as HTML entities
        self::assertTrue(
            str_contains($result, '👋') || str_contains($result, '&#128075;'),
            'Result should contain wave emoji or its HTML entity',
        );
    }

    // ========================================================================
    // Empty/null input
    // ========================================================================

    #[Test]
    public function emptyStringReturnsEmpty(): void
    {
        $result = RteImagePreviewViewHelper::processHtml('');

        self::assertSame('', $result);
    }

    #[Test]
    public function nullInputReturnsEmpty(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(null);

        self::assertSame('', $result);
    }

    #[Test]
    public function integerInputReturnsEmpty(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(42);

        self::assertSame('', $result);
    }

    // ========================================================================
    // Control character sanitization
    // ========================================================================

    #[Test]
    public function sanitizesControlCharacters(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            "<p>Text\x00with\x08control\x1Fchars</p>",
        );

        self::assertStringNotContainsString("\x00", $result);
        self::assertStringNotContainsString("\x08", $result);
        self::assertStringNotContainsString("\x1F", $result);
    }

    #[Test]
    public function sanitizesUtf16Surrogates(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            "<p>Text\xED\xA0\x80with surrogates</p>",
        );

        // Surrogates should be replaced with U+FFFD (DOMDocument encodes as &#65533;)
        self::assertTrue(
            str_contains($result, "\xEF\xBF\xBD") || str_contains($result, '&#65533;'),
            'Result should contain U+FFFD replacement character or its HTML entity',
        );
    }

    // ========================================================================
    // Custom allowedTags
    // ========================================================================

    #[Test]
    public function customAllowedTagsPreservesFigure(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<figure class="image"><img src="test.jpg" /><figcaption>Caption</figcaption></figure><p>Text</p>',
            1500,
            '<img><p><figure><figcaption>',
        );

        self::assertStringContainsString('<figure', $result);
        self::assertStringContainsString('<figcaption>', $result);
        self::assertStringContainsString('Caption', $result);
    }

    // ========================================================================
    // State reset between invocations (stateless static method)
    // ========================================================================

    #[Test]
    public function stateResetBetweenConsecutiveCalls(): void
    {
        $result1 = RteImagePreviewViewHelper::processHtml(
            '<p>' . str_repeat('a', 100) . '</p>',
            50,
        );
        self::assertStringContainsString('...', $result1);

        $result2 = RteImagePreviewViewHelper::processHtml(
            '<p>Short</p>',
            1000,
        );
        self::assertStringNotContainsString('...', $result2);
        self::assertStringContainsString('Short', $result2);
    }

    // ========================================================================
    // Data Provider Tests
    // ========================================================================

    /**
     * @return array<string, array{string, string}>
     */
    public static function tagStrippingDataProvider(): array
    {
        return [
            'div tag'    => ['<div>Content</div>', 'div'],
            'span tag'   => ['<span>Content</span>', 'span'],
            'script tag' => ['<script>alert("xss")</script>', 'script'],
            'style tag'  => ['<style>body{color:red}</style>', 'style'],
            'a tag'      => ['<a href="#">Link</a>', '<a'],
            'strong tag' => ['<strong>Bold</strong>', 'strong'],
            'table tag'  => ['<table><tr><td>Cell</td></tr></table>', 'table'],
        ];
    }

    #[Test]
    #[DataProvider('tagStrippingDataProvider')]
    public function stripsSpecificTags(string $input, string $tagToCheck): void
    {
        $result = RteImagePreviewViewHelper::processHtml($input);

        self::assertStringNotContainsString($tagToCheck, $result);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function truncationLengthDataProvider(): array
    {
        return [
            'very short text' => ['<p>Hi</p>', 100],
            'exact limit'     => ['<p>' . str_repeat('a', 50) . '</p>', 50],
            'one over limit'  => ['<p>' . str_repeat('a', 51) . '</p>', 50],
            'double limit'    => ['<p>' . str_repeat('a', 100) . '</p>', 50],
        ];
    }

    #[Test]
    #[DataProvider('truncationLengthDataProvider')]
    public function truncateHandlesDifferentLengths(string $html, int $length): void
    {
        $result = RteImagePreviewViewHelper::processHtml($html, $length);

        $plainTextLength = mb_strlen(strip_tags($html));

        if ($plainTextLength > $length) {
            self::assertStringContainsString('...', $result);
        } else {
            self::assertNotEmpty($result);
        }
    }

    // ========================================================================
    // Integration test
    // ========================================================================

    #[Test]
    public function integrationTestWithComplexContent(): void
    {
        $complexContent = <<<'HTML'
            <div class="container">
                <p>Introduction paragraph</p>
                <img src="header.jpg" alt="Header Image" />
                <p>Content with <strong>bold</strong> and <em>italic</em></p>
                <ul>
                    <li>Item 1</li>
                    <li>Item 2</li>
                </ul>
                <p>Final paragraph</p>
            </div>
            HTML;

        $result = RteImagePreviewViewHelper::processHtml($complexContent);

        self::assertStringContainsString('<p>', $result);
        self::assertStringContainsString('<img', $result);
        self::assertStringNotContainsString('<div', $result);
        self::assertStringNotContainsString('<strong', $result);
        self::assertStringNotContainsString('<ul', $result);
    }

    #[Test]
    public function multipleParagraphsWithImages(): void
    {
        $html = <<<'HTML'
            <p>First paragraph</p>
            <img src="image1.jpg" alt="Image 1" />
            <p>Second paragraph</p>
            <img src="image2.jpg" alt="Image 2" />
            <p>Third paragraph</p>
            HTML;

        $result = RteImagePreviewViewHelper::processHtml($html, 1000);

        self::assertStringContainsString('First paragraph', $result);
        self::assertStringContainsString('image1.jpg', $result);
    }

    // ========================================================================
    // Output sanitization (no XML PI leakage)
    // ========================================================================

    #[Test]
    public function outputDoesNotContainXmlProcessingInstruction(): void
    {
        $result = RteImagePreviewViewHelper::processHtml('<p>Test content</p>');

        self::assertStringNotContainsString('<?xml', $result);

        // Also verify after truncation (exercises the full DOMDocument round-trip)
        $truncated = RteImagePreviewViewHelper::processHtml(
            '<p>' . str_repeat('a', 100) . '</p>',
            50,
        );

        self::assertStringNotContainsString('<?xml', $truncated);
    }

    // ========================================================================
    // Edge cases: maxLength and allowedTags type coercion
    // ========================================================================

    #[Test]
    public function zeroMaxLengthTruncatesImmediately(): void
    {
        $result = RteImagePreviewViewHelper::processHtml('<p>Hello</p>', 0);

        self::assertStringContainsString('...', $result);
    }

    #[Test]
    public function numericStringMaxLengthIsAccepted(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<p>' . str_repeat('a', 100) . '</p>',
            '50',
        );

        self::assertStringContainsString('...', $result);
    }

    #[Test]
    public function nonStringAllowedTagsDefaultsToImgAndP(): void
    {
        $result = RteImagePreviewViewHelper::processHtml(
            '<div><p>Text</p><img src="test.jpg" /></div>',
            1500,
            null,
        );

        self::assertStringContainsString('<p>Text</p>', $result);
        self::assertStringContainsString('<img', $result);
        self::assertStringNotContainsString('<div>', $result);
    }
}
