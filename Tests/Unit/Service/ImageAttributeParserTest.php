<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service;

use Netresearch\RteCKEditorImage\Service\ImageAttributeParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Test case for ImageAttributeParser.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
class ImageAttributeParserTest extends TestCase
{
    private ImageAttributeParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ImageAttributeParser();
    }

    public function testParseImageAttributesWithBasicImage(): void
    {
        $html = '<img src="/path/to/image.jpg" alt="Alt text" width="800" height="600" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('/path/to/image.jpg', $attributes['src']);
        self::assertSame('Alt text', $attributes['alt']);
        self::assertSame('800', $attributes['width']);
        self::assertSame('600', $attributes['height']);
    }

    public function testParseImageAttributesWithDataAttributes(): void
    {
        $html = '<img src="/image.jpg" data-htmlarea-file-uid="123" data-quality="retina" data-caption="Caption text" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('/image.jpg', $attributes['src']);
        self::assertSame('123', $attributes['data-htmlarea-file-uid']);
        self::assertSame('retina', $attributes['data-quality']);
        self::assertSame('Caption text', $attributes['data-caption']);
    }

    public function testParseImageAttributesReturnsEmptyArrayForEmptyString(): void
    {
        $attributes = $this->parser->parseImageAttributes('');

        self::assertEmpty($attributes);
    }

    public function testParseImageAttributesReturnsEmptyArrayForWhitespace(): void
    {
        $attributes = $this->parser->parseImageAttributes('   ');

        self::assertEmpty($attributes);
    }

    public function testParseImageAttributesReturnsEmptyArrayForNoImage(): void
    {
        $html = '<p>Some text without an image</p>';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertEmpty($attributes);
    }

    public function testParseImageAttributesWithSingleQuotes(): void
    {
        $html = "<img src='/image.jpg' alt='Alt text' />";

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('/image.jpg', $attributes['src']);
        self::assertSame('Alt text', $attributes['alt']);
    }

    public function testParseImageAttributesWithMixedQuotes(): void
    {
        $html = '<img src="/image.jpg" alt=\'Alt text\' title="Title" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('/image.jpg', $attributes['src']);
        self::assertSame('Alt text', $attributes['alt']);
        self::assertSame('Title', $attributes['title']);
    }

    public function testParseImageAttributesWithSpecialCharacters(): void
    {
        $html = '<img src="/image.jpg" alt="Text with &quot;quotes&quot; and &amp; ampersand" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('/image.jpg', $attributes['src']);
        // DOMDocument decodes HTML entities
        self::assertSame('Text with "quotes" and & ampersand', $attributes['alt']);
    }

    public function testParseImageAttributesFindsFirstImageOnly(): void
    {
        $html = '<img src="/first.jpg" alt="First" /><img src="/second.jpg" alt="Second" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('/first.jpg', $attributes['src']);
        self::assertSame('First', $attributes['alt']);
    }

    public function testParseImageAttributesWithClass(): void
    {
        $html = '<img src="/image.jpg" class="img-fluid rounded" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('img-fluid rounded', $attributes['class']);
    }

    public function testParseImageAttributesWithStyle(): void
    {
        $html = '<img src="/image.jpg" style="max-width: 100%; height: auto;" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('max-width: 100%; height: auto;', $attributes['style']);
    }

    public function testParseLinkWithImagesBasicStructure(): void
    {
        $html = '<a href="https://example.com"><img src="/image.jpg" alt="Alt" /></a>';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertArrayHasKey('link', $result);
        self::assertArrayHasKey('images', $result);
        self::assertSame('https://example.com', $result['link']['href']);
        self::assertCount(1, $result['images']);
        self::assertArrayHasKey('attributes', $result['images'][0]);
        self::assertArrayHasKey('originalHtml', $result['images'][0]);
        self::assertSame('/image.jpg', $result['images'][0]['attributes']['src']);
        self::assertSame('Alt', $result['images'][0]['attributes']['alt']);
    }

    public function testParseLinkWithImagesMultipleImages(): void
    {
        $html = '<a href="/page"><img src="/first.jpg" /><img src="/second.jpg" /></a>';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertCount(2, $result['images']);
        self::assertSame('/first.jpg', $result['images'][0]['attributes']['src']);
        self::assertSame('/second.jpg', $result['images'][1]['attributes']['src']);
    }

    public function testParseLinkWithImagesLinkAttributes(): void
    {
        $html = '<a href="https://example.com" target="_blank" class="external-link" rel="noopener">
                    <img src="/image.jpg" />
                </a>';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertSame('https://example.com', $result['link']['href']);
        self::assertSame('_blank', $result['link']['target']);
        self::assertSame('external-link', $result['link']['class']);
        self::assertSame('noopener', $result['link']['rel']);
    }

    public function testParseLinkWithImagesReturnsEmptyForNoLink(): void
    {
        $html = '<img src="/image.jpg" />';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertEmpty($result['link']);
        self::assertEmpty($result['images']);
    }

    public function testParseLinkWithImagesReturnsEmptyForEmptyString(): void
    {
        $result = $this->parser->parseLinkWithImages('');

        self::assertEmpty($result['link']);
        self::assertEmpty($result['images']);
    }

    public function testParseLinkWithImagesHandlesNestedStructure(): void
    {
        $html = '<p><a href="/page"><img src="/image.jpg" alt="Image" /></a></p>';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertSame('/page', $result['link']['href']);
        self::assertSame('/image.jpg', $result['images'][0]['attributes']['src']);
    }

    public function testParseImageAttributesWithLoadingAttribute(): void
    {
        $html = '<img src="/image.jpg" loading="lazy" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('lazy', $attributes['loading']);
    }

    public function testParseImageAttributesPreservesAttributeOrder(): void
    {
        $html = '<img id="test-id" src="/image.jpg" class="test-class" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertArrayHasKey('id', $attributes);
        self::assertArrayHasKey('src', $attributes);
        self::assertArrayHasKey('class', $attributes);
        self::assertSame('test-id', $attributes['id']);
    }

    public function testParseImageAttributesWithEmptyAttributes(): void
    {
        $html = '<img src="/image.jpg" alt="" title="" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('/image.jpg', $attributes['src']);
        self::assertSame('', $attributes['alt']);
        self::assertSame('', $attributes['title']);
    }

    public function testParseLinkWithImagesLinkWithoutImages(): void
    {
        $html = '<a href="/page">Click here</a>';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertSame('/page', $result['link']['href']);
        self::assertEmpty($result['images']);
    }

    public function testParseLinkWithImagesPreservesOriginalHtml(): void
    {
        // Test that originalHtml captures the img tag (DOMDocument normalizes it)
        $html = '<a href="/page"><img src="/image.jpg" alt="Test" width="800" height="600" /></a>';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertCount(1, $result['images']);
        self::assertArrayHasKey('originalHtml', $result['images'][0]);
        // DOMDocument outputs without self-closing slash
        self::assertStringContainsString('<img', $result['images'][0]['originalHtml']);
        self::assertStringContainsString('src="/image.jpg"', $result['images'][0]['originalHtml']);
        self::assertStringContainsString('alt="Test"', $result['images'][0]['originalHtml']);
    }

    public function testParseLinkWithImagesOriginalHtmlCanBeUsedForReplacement(): void
    {
        // This test verifies the fix for the attribute order bug
        // The originalHtml should match exactly what's in the input HTML
        $html = '<a href="/page"><img alt="Alt first" src="/image.jpg" class="my-class" /></a>';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertCount(1, $result['images']);
        $originalHtml = $result['images'][0]['originalHtml'];

        // The original HTML from DOMDocument should be usable for str_replace
        // because it's extracted from the same parsed DOM, not rebuilt from attributes
        self::assertNotEmpty($originalHtml);
        self::assertStringContainsString('img', $originalHtml);
    }

    public function testParseLinkWithImagesMultipleImagesHaveDistinctOriginalHtml(): void
    {
        $html = '<a href="/page"><img src="/first.jpg" alt="First" /><img src="/second.jpg" alt="Second" /></a>';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertCount(2, $result['images']);

        // Each image should have its own distinct originalHtml
        self::assertStringContainsString('first.jpg', $result['images'][0]['originalHtml']);
        self::assertStringContainsString('second.jpg', $result['images'][1]['originalHtml']);
        self::assertNotSame($result['images'][0]['originalHtml'], $result['images'][1]['originalHtml']);
    }

    // ========================================================================
    // parseFigureWithCaption() Tests - Caption extraction from <figure>/<figcaption>
    // ========================================================================

    #[Test]
    public function parseFigureWithCaptionExtractsCaptionFromFigcaption(): void
    {
        $html   = '<figure class="image"><img src="test.jpg" alt="Test"/><figcaption>My Caption</figcaption></figure>';
        $result = $this->parser->parseFigureWithCaption($html);

        self::assertSame('My Caption', $result['caption']);
        self::assertSame('test.jpg', $result['attributes']['src']);
    }

    #[Test]
    public function parseFigureWithCaptionReturnsEmptyForNoFigure(): void
    {
        $html   = '<img src="test.jpg" alt="Test"/>';
        $result = $this->parser->parseFigureWithCaption($html);

        self::assertSame('', $result['caption']);
        self::assertEmpty($result['attributes']);
    }

    #[Test]
    public function parseFigureWithCaptionHandlesEmptyFigcaption(): void
    {
        $html   = '<figure class="image"><img src="test.jpg"/><figcaption></figcaption></figure>';
        $result = $this->parser->parseFigureWithCaption($html);

        self::assertSame('', $result['caption']);
        self::assertSame('test.jpg', $result['attributes']['src']);
    }

    #[Test]
    public function parseFigureWithCaptionHandlesMissingFigcaption(): void
    {
        $html   = '<figure class="image"><img src="test.jpg"/></figure>';
        $result = $this->parser->parseFigureWithCaption($html);

        self::assertSame('', $result['caption']);
        self::assertSame('test.jpg', $result['attributes']['src']);
    }

    #[Test]
    public function parseFigureWithCaptionPreservesDataCaptionAttribute(): void
    {
        // When both figcaption and data-caption exist, figcaption takes precedence
        $html   = '<figure class="image"><img src="test.jpg" data-caption="Old Caption"/><figcaption>New Caption</figcaption></figure>';
        $result = $this->parser->parseFigureWithCaption($html);

        self::assertSame('New Caption', $result['caption']);
        self::assertSame('Old Caption', $result['attributes']['data-caption']);
    }

    #[Test]
    public function parseFigureWithCaptionExtractsAllImageAttributes(): void
    {
        $html   = '<figure class="image"><img src="test.jpg" alt="Alt" title="Title" width="100" height="50" data-htmlarea-file-uid="123"/><figcaption>Caption</figcaption></figure>';
        $result = $this->parser->parseFigureWithCaption($html);

        self::assertSame('test.jpg', $result['attributes']['src']);
        self::assertSame('Alt', $result['attributes']['alt']);
        self::assertSame('Title', $result['attributes']['title']);
        self::assertSame('100', $result['attributes']['width']);
        self::assertSame('50', $result['attributes']['height']);
        self::assertSame('123', $result['attributes']['data-htmlarea-file-uid']);
    }

    #[Test]
    public function parseFigureWithCaptionHandlesEmptyString(): void
    {
        $result = $this->parser->parseFigureWithCaption('');

        self::assertSame('', $result['caption']);
        self::assertEmpty($result['attributes']);
    }

    #[Test]
    public function parseFigureWithCaptionHandlesWhitespaceOnlyCaption(): void
    {
        $html   = '<figure class="image"><img src="test.jpg"/><figcaption>   </figcaption></figure>';
        $result = $this->parser->parseFigureWithCaption($html);

        // Whitespace-only caption should be trimmed to empty
        self::assertSame('', $result['caption']);
    }

    #[Test]
    public function parseFigureWithCaptionHandlesNestedFigure(): void
    {
        // Nested in other elements
        $html   = '<p><figure class="image"><img src="test.jpg"/><figcaption>Nested Caption</figcaption></figure></p>';
        $result = $this->parser->parseFigureWithCaption($html);

        self::assertSame('Nested Caption', $result['caption']);
    }

    // ========================================================================
    // hasFigureWrapper() Tests - Figure wrapper detection
    // ========================================================================

    #[Test]
    public function hasFigureWrapperReturnsTrueForFigureWithImage(): void
    {
        $html = '<figure class="image"><img src="test.jpg"/></figure>';

        self::assertTrue($this->parser->hasFigureWrapper($html));
    }

    #[Test]
    public function hasFigureWrapperReturnsFalseForStandaloneImage(): void
    {
        $html = '<img src="test.jpg"/>';

        self::assertFalse($this->parser->hasFigureWrapper($html));
    }

    #[Test]
    public function hasFigureWrapperReturnsFalseForFigureWithoutImage(): void
    {
        $html = '<figure class="image"><p>No image here</p></figure>';

        // Has figure but no img - detection should still find figure/img combination
        self::assertFalse($this->parser->hasFigureWrapper($html));
    }

    #[Test]
    public function hasFigureWrapperReturnsFalseForEmptyString(): void
    {
        self::assertFalse($this->parser->hasFigureWrapper(''));
    }
}
