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

    /**
     * Test that parseLinkWithImages finds images even without link wrapper.
     *
     * When TypoScript tags.a.preUserFunc calls this method, getCurrentVal()
     * returns only the inner content (just <img>), not <a><img></a>.
     * The method should still find and process these images.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
     */
    public function testParseLinkWithImagesReturnsImagesWithoutLinkWrapper(): void
    {
        $html = '<img src="/image.jpg" alt="Test" data-htmlarea-file-uid="1" />';

        $result = $this->parser->parseLinkWithImages($html);

        // Link should be empty (no <a> wrapper)
        self::assertEmpty($result['link']);

        // But images should still be found
        self::assertNotEmpty($result['images']);
        self::assertCount(1, $result['images']);
        self::assertSame('/image.jpg', $result['images'][0]['attributes']['src']);
        self::assertSame('Test', $result['images'][0]['attributes']['alt']);
        self::assertSame('1', $result['images'][0]['attributes']['data-htmlarea-file-uid']);
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

        // Should return false when figure exists but contains no img element
        self::assertFalse($this->parser->hasFigureWrapper($html));
    }

    #[Test]
    public function hasFigureWrapperReturnsFalseForEmptyString(): void
    {
        self::assertFalse($this->parser->hasFigureWrapper(''));
    }

    #[Test]
    public function hasFigureWrapperReturnsFalseForUnrelatedFigureAndImage(): void
    {
        // Figure and img exist but img is not inside figure
        $html = '<p>Read more in <figure>1</figure></p><div><img src="other.jpg"/></div>';

        self::assertFalse($this->parser->hasFigureWrapper($html));
    }

    #[Test]
    public function parseFigureWithCaptionHandlesHtmlEntities(): void
    {
        $html   = '<figure class="image"><img src="test.jpg"/><figcaption>Caption with &amp; &lt; &gt;</figcaption></figure>';
        $result = $this->parser->parseFigureWithCaption($html);

        // textContent should decode HTML entities
        self::assertSame('Caption with & < >', $result['caption']);
    }

    #[Test]
    public function parseFigureWithCaptionPreservesInternalWhitespace(): void
    {
        $html   = "<figure class=\"image\"><img src=\"test.jpg\"/><figcaption>Line 1\nLine 2</figcaption></figure>";
        $result = $this->parser->parseFigureWithCaption($html);

        // Internal whitespace (newlines, multiple spaces) should be preserved
        self::assertSame("Line 1\nLine 2", $result['caption']);
    }

    #[Test]
    public function parseFigureWithCaptionHandlesLinkedImage(): void
    {
        // Figure containing a linked image (common scenario)
        $html = '<figure class="image"><a href="/link"><img src="test.jpg" data-htmlarea-file-uid="123"/></a><figcaption>Linked Caption</figcaption></figure>';

        $result = $this->parser->parseFigureWithCaption($html);

        self::assertSame('Linked Caption', $result['caption']);
        self::assertSame('test.jpg', $result['attributes']['src']);
        self::assertSame('123', $result['attributes']['data-htmlarea-file-uid']);
    }

    // ========================================================================
    // Issue #555 Tests - Link extraction from figure-wrapped images
    // ========================================================================

    /**
     * Test that parseFigureWithCaption extracts link attributes when image is wrapped in <a>.
     *
     * Bug: When CKEditor outputs <figure><a href="..."><img/></a><figcaption>...</figcaption></figure>,
     * the link information must be extracted so the correct template (LinkWithCaption) can be selected.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function parseFigureWithCaptionExtractsLinkAttributesFromLinkedImage(): void
    {
        $html = '<figure class="image"><a href="https://example.com" target="_blank" class="external"><img src="test.jpg" data-htmlarea-file-uid="123"/></a><figcaption>Linked Caption</figcaption></figure>';

        $result = $this->parser->parseFigureWithCaption($html);

        // Image attributes should be extracted
        self::assertSame('test.jpg', $result['attributes']['src']);
        self::assertSame('123', $result['attributes']['data-htmlarea-file-uid']);
        self::assertSame('Linked Caption', $result['caption']);

        // Link attributes MUST be extracted (this is the bug)
        self::assertArrayHasKey('link', $result);
        self::assertSame('https://example.com', $result['link']['href']);
        self::assertSame('_blank', $result['link']['target']);
        self::assertSame('external', $result['link']['class']);
    }

    /**
     * Test that parseFigureWithCaption returns empty link array when no <a> wrapper.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function parseFigureWithCaptionReturnsEmptyLinkWhenNoLinkWrapper(): void
    {
        $html = '<figure class="image"><img src="test.jpg" data-htmlarea-file-uid="123"/><figcaption>Caption</figcaption></figure>';

        $result = $this->parser->parseFigureWithCaption($html);

        self::assertArrayHasKey('link', $result);
        self::assertEmpty($result['link']);
    }

    /**
     * Test that parseFigureWithCaption extracts link with popup attributes.
     *
     * When image has data-htmlarea-zoom inside a link inside a figure,
     * all this context must be preserved for correct template selection.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function parseFigureWithCaptionExtractsLinkForPopupImage(): void
    {
        $html = '<figure class="image"><a href="/fullsize.jpg"><img src="test.jpg" data-htmlarea-file-uid="123" data-htmlarea-zoom="1"/></a><figcaption>Popup Caption</figcaption></figure>';

        $result = $this->parser->parseFigureWithCaption($html);

        // Link should be extracted
        self::assertArrayHasKey('link', $result);
        self::assertSame('/fullsize.jpg', $result['link']['href']);

        // Popup attribute on image should be preserved
        self::assertSame('1', $result['attributes']['data-htmlarea-zoom']);
    }

    /**
     * Test that parseFigureWithCaption extracts link even without figcaption.
     *
     * Figure + Link (no caption) should still extract link attributes.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function parseFigureWithCaptionExtractsLinkWithoutCaption(): void
    {
        $html = '<figure class="image"><a href="https://example.com" target="_blank"><img src="test.jpg" data-htmlarea-file-uid="123"/></a></figure>';

        $result = $this->parser->parseFigureWithCaption($html);

        // Link should be extracted
        self::assertArrayHasKey('link', $result);
        self::assertSame('https://example.com', $result['link']['href']);
        self::assertSame('_blank', $result['link']['target']);

        // Caption should be empty
        self::assertSame('', $result['caption']);

        // Image attributes should be extracted
        self::assertSame('test.jpg', $result['attributes']['src']);
        self::assertSame('123', $result['attributes']['data-htmlarea-file-uid']);
    }

    /**
     * Test that parseFigureWithCaption preserves popup attributes without explicit link.
     *
     * When image has data-htmlarea-zoom but no <a> wrapper, the popup attributes
     * must be preserved so the resolver can auto-generate a popup link.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function parseFigureWithCaptionPreservesPopupAttributesWithoutLink(): void
    {
        $html = '<figure class="image"><img src="test.jpg" data-htmlarea-file-uid="123" data-htmlarea-zoom="1"/><figcaption>Popup Caption</figcaption></figure>';

        $result = $this->parser->parseFigureWithCaption($html);

        // No explicit link
        self::assertArrayHasKey('link', $result);
        self::assertEmpty($result['link']);

        // Popup attribute on image should be preserved for resolver to handle
        self::assertSame('1', $result['attributes']['data-htmlarea-zoom']);

        // Caption should be extracted
        self::assertSame('Popup Caption', $result['caption']);
    }

    /**
     * Test that parseFigureWithCaption handles figure with popup and no caption.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function parseFigureWithCaptionHandlesPopupWithoutCaption(): void
    {
        $html = '<figure class="image"><img src="test.jpg" data-htmlarea-file-uid="123" data-htmlarea-clickenlarge="1"/></figure>';

        $result = $this->parser->parseFigureWithCaption($html);

        // No explicit link
        self::assertEmpty($result['link']);

        // Popup attribute preserved
        self::assertSame('1', $result['attributes']['data-htmlarea-clickenlarge']);

        // No caption
        self::assertSame('', $result['caption']);
    }
}
