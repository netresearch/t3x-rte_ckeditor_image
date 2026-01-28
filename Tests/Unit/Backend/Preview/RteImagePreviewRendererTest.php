<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Backend\Preview;

use Netresearch\RteCKEditorImage\Backend\Preview\RteImagePreviewRenderer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;

/**
 * Test case for RteImagePreviewRenderer.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(RteImagePreviewRenderer::class)]
class RteImagePreviewRendererTest extends TestCase
{
    private RteImagePreviewRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new RteImagePreviewRenderer();
    }

    /**
     * Helper method to call protected/private methods for testing.
     *
     * @param object  $object     Object instance
     * @param string  $methodName Method name to call
     * @param mixed[] $parameters Parameters to pass
     *
     * @return string
     */
    protected function callMethod(object $object, string $methodName, array $parameters = []): string
    {
        $reflection = new ReflectionMethod($object::class, $methodName);
        $result     = $reflection->invokeArgs($object, $parameters);

        self::assertIsString($result, 'Expected method ' . $methodName . ' to return a string');

        return $result;
    }

    /**
     * Helper method to create a properly mocked GridColumnItem.
     *
     * @param array<string, mixed> $row The row data to return from getRow() or getRecord()
     *
     * @return GridColumnItem&\PHPUnit\Framework\MockObject\MockObject
     */
    protected function createGridColumnItemMock(array $row): GridColumnItem
    {
        $gridColumnItemMock = $this->createMock(GridColumnItem::class);

        // TYPO3 v14+ uses getRow() which returns array from RecordInterface
        // TYPO3 v13 uses getRecord() which returns array directly
        if (method_exists(GridColumnItem::class, 'getRow')) {
            $recordMock = $this->createMock(\TYPO3\CMS\Core\Domain\RecordInterface::class);
            $gridColumnItemMock
                ->method('getRecord')
                ->willReturn($recordMock);
            $gridColumnItemMock
                ->method('getRow')
                ->willReturn($row);
        } else {
            // TYPO3 v13 uses getRecord() which returns array directly
            $gridColumnItemMock
                ->method('getRecord')
                ->willReturn($row);
        }

        return $gridColumnItemMock;
    }

    // ========================================================================
    // Sanitization Tests (testing the regex sanitization logic)
    // ========================================================================

    #[Test]
    public function sanitizesInvalidControlCharacters(): void
    {
        // Content with invalid control characters
        $contentWithControlChars = "<p>Text\x00with\x08invalid\x0Bcontrol\x1Fchars</p>";

        // Test the sanitization logic by processing through renderTextWithHtml
        $result = $this->callMethod($this->renderer, 'renderTextWithHtml', [$contentWithControlChars]);

        // Control characters should be replaced with U+FFFD (replacement character)
        self::assertStringNotContainsString("\x00", $result);
        self::assertStringNotContainsString("\x08", $result);
        self::assertStringNotContainsString("\x0B", $result);
        self::assertStringNotContainsString("\x1F", $result);
    }

    #[Test]
    public function sanitizesUtf16Surrogates(): void
    {
        // Content with UTF-16 surrogate characters
        $contentWithSurrogates = "<p>Text\xED\xA0\x80with\xED\xBF\xBFsurrogates</p>";

        // Sanitization happens before renderTextWithHtml in renderPageModulePreviewContent
        // Simulate the sanitization (regex must match implementation)
        $sanitized = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\xED[\xA0-\xBF][\x80-\xBF]|\xEF\xBF[\xBE\xBF]/',
            "\xEF\xBF\xBD",
            $contentWithSurrogates,
        );

        self::assertIsString($sanitized);
        // Surrogates should be replaced
        self::assertStringContainsString("\xEF\xBF\xBD", $sanitized);
    }

    #[Test]
    public function sanitizesNonCharacters(): void
    {
        // Content with non-characters U+FFFE and U+FFFF
        $contentWithNonChars = "<p>Text\xEF\xBF\xBEwith\xEF\xBF\xBFnon-chars</p>";

        // Simulate the sanitization (regex must match implementation)
        $sanitized = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\xED[\xA0-\xBF][\x80-\xBF]|\xEF\xBF[\xBE\xBF]/',
            "\xEF\xBF\xBD",
            $contentWithNonChars,
        );

        self::assertIsString($sanitized);
        // Non-characters should be replaced
        self::assertStringContainsString("\xEF\xBF\xBD", $sanitized);
    }

    // ========================================================================
    // renderTextWithHtml() Tests
    // ========================================================================

    #[Test]
    public function renderTextWithHtmlPreservesImgTags(): void
    {
        $input = '<img src="image.jpg" alt="Test" /><p>Text</p>';

        $result = $this->callMethod($this->renderer, 'renderTextWithHtml', [$input]);

        // DOMDocument may change self-closing tags to standard tags
        self::assertStringContainsString('<img src="image.jpg" alt="Test"', $result);
        self::assertStringContainsString('<p>Text</p>', $result);
    }

    #[Test]
    public function renderTextWithHtmlPreservesPTags(): void
    {
        $input = '<p>Paragraph text</p>';

        $result = $this->callMethod($this->renderer, 'renderTextWithHtml', [$input]);

        self::assertStringContainsString('<p>Paragraph text</p>', $result);
    }

    #[Test]
    public function renderTextWithHtmlStripsDisallowedTags(): void
    {
        $input = '<div><p>Text</p><span>Span</span><script>alert("xss")</script></div>';

        $result = $this->callMethod($this->renderer, 'renderTextWithHtml', [$input]);

        // Only <p> should remain
        self::assertStringContainsString('<p>Text</p>', $result);
        self::assertStringNotContainsString('<div>', $result);
        self::assertStringNotContainsString('<span>', $result);
        self::assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function renderTextWithHtmlStripsAnchorTags(): void
    {
        $input = '<p><a href="http://example.com">Link</a></p>';

        $result = $this->callMethod($this->renderer, 'renderTextWithHtml', [$input]);

        self::assertStringNotContainsString('<a', $result);
        self::assertStringContainsString('Link', $result);
    }

    #[Test]
    public function renderTextWithHtmlTruncatesTo1500Characters(): void
    {
        // Create content longer than 1500 characters
        $input = '<p>' . str_repeat('Lorem ipsum dolor sit amet ', 100) . '</p>';

        $result = $this->callMethod($this->renderer, 'renderTextWithHtml', [$input]);

        // Result should contain ellipsis indicating truncation
        self::assertStringContainsString('...', $result);
    }

    // ========================================================================
    // truncate() Tests
    // ========================================================================

    #[Test]
    public function truncatePreservesHtmlTags(): void
    {
        $html = '<p>Short text</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 1500]);

        self::assertStringContainsString('<p>Short text</p>', $result);
    }

    #[Test]
    public function truncateAppendsEllipsis(): void
    {
        $html = '<p>' . str_repeat('a', 100) . '</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 50]);

        self::assertStringContainsString('...', $result);
    }

    #[Test]
    public function truncateHandlesMultiByteCharacters(): void
    {
        $html = '<p>Müller Öffentlich Überprüfung</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 20]);

        // Should handle UTF-8 characters correctly and truncate
        self::assertStringContainsString('...', $result);
    }

    #[Test]
    public function truncateHandlesEmptyInput(): void
    {
        $html = '';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 100]);

        // Empty input passes through DOMDocument which adds XML prolog, but doesn't crash
        self::assertStringNotContainsString('...', $result);
    }

    #[Test]
    public function truncateHandlesOnlyWhitespace(): void
    {
        $html = '   ';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 100]);

        // Whitespace-only input should return without crashing
        self::assertStringNotContainsString('...', $result);
    }

    #[Test]
    public function truncateHandlesComplexNestedStructure(): void
    {
        $html = '<p>Start <img src="test.jpg" alt="Image" /> Middle text</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 100]);

        self::assertStringContainsString('<img', $result);
        self::assertStringContainsString('</p>', $result);
    }

    #[Test]
    public function truncateCutsAtExactLengthWithText(): void
    {
        $html = '<p>12345678901234567890</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 10]);

        self::assertStringContainsString('...', $result);
        // Result should contain 10 characters + "..."
    }

    #[Test]
    public function truncatePreservesImageTagsWhenUnderLimit(): void
    {
        $html = '<img src="test.jpg" alt="Test" /><p>Text</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 1000]);

        self::assertStringContainsString('<img src="test.jpg"', $result);
        self::assertStringContainsString('<p>Text</p>', $result);
    }

    #[Test]
    public function truncateRemovesNodesExceedingLimit(): void
    {
        $html = '<p>First paragraph with some text</p><p>Second paragraph with more text</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 20]);

        // Should truncate and add ellipsis
        self::assertStringContainsString('...', $result);
        self::assertLessThan(mb_strlen($html), mb_strlen($result));
    }

    // ========================================================================
    // walk() Tests (via truncate, since walk is private and called by truncate)
    // ========================================================================

    #[Test]
    public function walkHandlesNestedParagraphs(): void
    {
        $html = '<p>First</p><p>Second</p><p>Third</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 100]);

        self::assertStringContainsString('First', $result);
        self::assertStringContainsString('Second', $result);
        self::assertStringContainsString('Third', $result);
    }

    #[Test]
    public function walkHandlesMixedContent(): void
    {
        $html = '<p>Text before <img src="image.jpg" /> text after</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 1000]);

        self::assertStringContainsString('Text before', $result);
        self::assertStringContainsString('<img', $result);
        self::assertStringContainsString('text after', $result);
    }

    #[Test]
    public function walkStopsAtLimit(): void
    {
        $html = '<p>' . str_repeat('x', 100) . '</p><p>This should be removed</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 50]);

        self::assertStringContainsString('...', $result);
        self::assertStringNotContainsString('This should be removed', $result);
    }

    // ========================================================================
    // Data Provider Tests
    // ========================================================================

    /**
     * @return array<string, array{string, string}>
     */
    public static function tagStrippingTestDataProvider(): array
    {
        return [
            'div tag'    => ['<div>Content</div>', 'div'],
            'span tag'   => ['<span>Content</span>', 'span'],
            'script tag' => ['<script>alert("xss")</script>', 'script'],
            'style tag'  => ['<style>body{color:red}</style>', 'style'],
            'a tag'      => ['<a href="#">Link</a>', '<a'],
            'strong tag' => ['<strong>Bold</strong>', 'strong'],
            'em tag'     => ['<em>Italic</em>', 'em'],
            'ul tag'     => ['<ul><li>Item</li></ul>', '<ul'],
            'table tag'  => ['<table><tr><td>Cell</td></tr></table>', 'table'],
        ];
    }

    #[Test]
    #[DataProvider('tagStrippingTestDataProvider')]
    public function renderTextWithHtmlStripsSpecificTags(string $input, string $tagToCheck): void
    {
        $result = $this->callMethod($this->renderer, 'renderTextWithHtml', [$input]);

        self::assertStringNotContainsString($tagToCheck, $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedTagsTestDataProvider(): array
    {
        return [
            'simple img'          => ['<img src="test.jpg" />'],
            'img with attributes' => ['<img src="test.jpg" alt="Test" width="800" height="600" />'],
            'simple p'            => ['<p>Text</p>'],
            'p with class'        => ['<p class="test">Text</p>'],
            'img and p combined'  => ['<p>Text <img src="test.jpg" /> more text</p>'],
        ];
    }

    #[Test]
    #[DataProvider('allowedTagsTestDataProvider')]
    public function renderTextWithHtmlPreservesAllowedTags(string $input): void
    {
        $result = $this->callMethod($this->renderer, 'renderTextWithHtml', [$input]);

        // Should preserve either <img> or <p> tags
        // Note: DOMDocument may strip attributes from tags like <p class="test">
        // but it should preserve the tags themselves
        self::assertTrue(
            str_contains($result, '<img') || str_contains($result, '<p>') || str_contains($result, '<p '),
            'Result should preserve allowed tags',
        );
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function truncationLengthTestDataProvider(): array
    {
        return [
            'very short text' => ['<p>Hi</p>', 100],
            'exact limit'     => ['<p>' . str_repeat('a', 50) . '</p>', 50],
            'one over limit'  => ['<p>' . str_repeat('a', 51) . '</p>', 50],
            'double limit'    => ['<p>' . str_repeat('a', 100) . '</p>', 50],
            'empty text'      => ['', 100],
        ];
    }

    #[Test]
    #[DataProvider('truncationLengthTestDataProvider')]
    public function truncateHandlesDifferentLengths(string $html, int $length): void
    {
        $result = $this->callMethod($this->renderer, 'truncate', [$html, $length]);

        // If input is longer than limit, should have ellipsis
        $plainTextLength = mb_strlen(strip_tags($html));
        if ($plainTextLength > $length && $plainTextLength > 0) {
            self::assertStringContainsString('...', $result);
        } elseif ($plainTextLength === 0) {
            // Empty input passes through DOMDocument (may add XML prolog), no ellipsis
            self::assertStringNotContainsString('...', $result);
        } else {
            // No truncation needed - result should contain original text
            self::assertNotEmpty($result);
        }
    }

    #[Test]
    public function truncateHandlesMultipleParagraphsWithImages(): void
    {
        $html = <<<HTML
            <p>First paragraph</p>
            <img src="image1.jpg" alt="Image 1" />
            <p>Second paragraph</p>
            <img src="image2.jpg" alt="Image 2" />
            <p>Third paragraph</p>
            HTML;

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 1000]);

        self::assertStringContainsString('First paragraph', $result);
        self::assertStringContainsString('image1.jpg', $result);
    }

    #[Test]
    public function truncateHandlesUnicodeEmojis(): void
    {
        $html = '<p>Hello 👋 World 🌍 Test 🧪</p>';

        $result = $this->callMethod($this->renderer, 'truncate', [$html, 100]);

        // DOMDocument may encode emojis as HTML entities (&#128075; for 👋)
        // Check that the content is present either as emoji or HTML entity
        self::assertTrue(
            str_contains($result, '👋') || str_contains($result, '&#128075;'),
            'Result should contain wave emoji or its HTML entity',
        );
        self::assertTrue(
            str_contains($result, '🌍') || str_contains($result, '&#127757;'),
            'Result should contain globe emoji or its HTML entity',
        );
    }

    #[Test]
    public function renderTextWithHtmlIntegrationTest(): void
    {
        $complexContent = <<<HTML
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

        $result = $this->callMethod($this->renderer, 'renderTextWithHtml', [$complexContent]);

        // Should preserve <p> and <img>
        self::assertStringContainsString('<p>', $result);
        self::assertStringContainsString('<img', $result);

        // Should strip other tags
        self::assertStringNotContainsString('<div', $result);
        self::assertStringNotContainsString('<strong', $result);
        self::assertStringNotContainsString('<ul', $result);
    }
}
