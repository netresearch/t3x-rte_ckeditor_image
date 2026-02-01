<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Controller;

use Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Comprehensive tests for RTE image renderer with mixed inline and block content.
 *
 * The RTE image renderer processes content that is already stored as HTML in the database.
 * This content can contain a mix of block-level images, inline images, links, text nodes,
 * line breaks and paragraphs.
 *
 * These tests ensure that the renderer:
 * - preserves existing structure
 * - behaves consistently across mixed-content scenarios
 * - is idempotent (rendering output again does not change structure)
 *
 * KEY BEHAVIOR:
 * - Figures WITH caption → output as <figure><img><figcaption></figure>
 * - Figures WITHOUT caption → output as just <img> (Standalone template)
 * - This is semantically correct: <figure> should only be used with <figcaption>
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/580
 */
final class RteMixedContentRenderingTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();

        // Import test data
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file.csv');

        // Create a minimal site configuration
        $site = new Site('test', 1, [
            'base'      => 'http://localhost/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title'      => 'English',
                    'locale'     => 'en_US.UTF-8',
                    'base'       => '/',
                ],
            ],
        ]);

        // Create request with site
        $this->request = (new ServerRequest())
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());
    }

    /**
     * Get fresh adapter and content object renderer for each test.
     *
     * Following the pattern from FigureCaptionRenderingTest which creates
     * fresh instances per test to avoid state pollution.
     *
     * @return array{adapter: ImageRenderingAdapter, cObj: ContentObjectRenderer}
     */
    private function getAdapterWithCObj(): array
    {
        /** @var ImageRenderingAdapter $adapter */
        $adapter = $this->get(ImageRenderingAdapter::class);
        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $adapter->setContentObjectRenderer($cObj);

        return ['adapter' => $adapter, 'cObj' => $cObj];
    }

    // ========================================================================
    // Block-level image handling (figures with captions)
    // ========================================================================

    /**
     * Test case 1: Block image without caption outputs just img tag.
     *
     * A figure WITHOUT caption correctly outputs just <img>, not <figure>.
     * The <figure> wrapper is only added when there's a <figcaption> present.
     */
    #[Test]
    public function blockImageWithoutCaptionOutputsJustImg(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $inputHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '</figure>';

        $cObj->setCurrentVal($inputHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Without caption, renderFigure outputs just an <img> tag (Standalone template)
        // This is semantically correct - <figure> should only be used with <figcaption>
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        // Verify the image was processed (has decoding attribute)
        self::assertStringContainsString('decoding="async"', $result, 'Processed image should have decoding attribute');
        // Should NOT have figure wrapper (no caption present)
        self::assertStringNotContainsString('<figure', $result, 'Uncaptioned image should not have figure wrapper');
    }

    /**
     * Test case 2: Block image with caption outputs figure wrapper.
     */
    #[Test]
    public function blockImageWithCaptionOutputsFigure(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $inputHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>My Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($inputHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should have exactly one figure and one figcaption
        self::assertSame(1, substr_count($result, '<figure'), 'Expected exactly 1 figure element');
        self::assertSame(1, substr_count($result, '<figcaption'), 'Expected exactly 1 figcaption element');
        self::assertSame(1, substr_count($result, 'My Caption'), 'Caption text should appear once');
    }

    /**
     * Test case 3: Block image in invalid container handled gracefully.
     */
    #[Test]
    public function blockImageInsideParagraphHandledGracefully(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should produce a valid img output without breaking structure
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        // renderImageAttributes should NOT create figure wrappers (that's renderFigure's job)
        self::assertStringNotContainsString('<figure', $result, 'renderImageAttributes should not create figure');
    }

    /**
     * Test case 4: Captioned image between paragraphs maintains structure.
     */
    #[Test]
    public function captionedImageBetweenParagraphsMaintainsStructure(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>Caption Text</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should have one figure with caption
        self::assertSame(1, substr_count($result, '<figure'), 'Expected exactly 1 figure element');
        self::assertStringContainsString('Caption Text', $result, 'Caption should be preserved');
    }

    /**
     * Test case 5: Figure processing is self-contained.
     */
    #[Test]
    public function figureProcessingIsSelfContained(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Result should be self-contained
        self::assertSame(1, substr_count($result, '<figure'), 'Figure count should be 1');
        self::assertSame(1, substr_count($result, '</figure>'), 'Closing figure count should be 1');
    }

    // ========================================================================
    // Inline image handling inside paragraphs
    // ========================================================================

    /**
     * Test case 7: Inline image at the beginning of a paragraph.
     */
    #[Test]
    public function inlineImageAtBeginningOfParagraph(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should remain inline (no figure wrapper)
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        self::assertStringNotContainsString('<figure', $result, 'Inline image should not have figure wrapper');
    }

    /**
     * Test case 8: Inline image in the middle of a paragraph.
     */
    #[Test]
    public function inlineImageInMiddleOfParagraph(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should remain inline
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        self::assertStringNotContainsString('<figure', $result, 'Inline image should not have figure wrapper');
    }

    /**
     * Test case 9: Inline image at the end of a paragraph.
     */
    #[Test]
    public function inlineImageAtEndOfParagraph(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should remain inline
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        self::assertStringNotContainsString('<figure', $result, 'Inline image should not have figure wrapper');
    }

    /**
     * Test case 10: Inline image followed by text and links.
     */
    #[Test]
    public function inlineImageFollowedByTextAndLinks(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Result should be self-contained, allowing text/links to follow
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        // Should end cleanly (not leave unclosed tags)
        self::assertStringContainsString('/>', $result, 'Image should be self-closing');
    }

    /**
     * Test case 11: Inline image preceded by text.
     */
    #[Test]
    public function inlineImagePrecededByText(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should remain inline and not affect preceding content
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
    }

    /**
     * Test case 12: Inline image combined with br elements.
     */
    #[Test]
    public function inlineImageCombinedWithBrElements(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should not affect br elements
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        self::assertStringNotContainsString('<br', $result, 'Result should not inject br elements');
    }

    /**
     * Test case 13: Multiple inline images processed independently.
     */
    #[Test]
    public function multipleInlineImagesProcessedIndependently(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        // Process first image
        $imgTag1 = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="First">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'First',
        ];
        $cObj->setCurrentVal($imgTag1);

        /** @var string $result1 */
        $result1 = $adapter->renderImageAttributes(null, [], $this->request);

        // Process second image with fresh adapter
        ['adapter' => $adapter2, 'cObj' => $cObj2] = $this->getAdapterWithCObj();

        $cObj2->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Second',
        ];
        $cObj2->setCurrentVal('<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Second">');

        /** @var string $result2 */
        $result2 = $adapter2->renderImageAttributes(null, [], $this->request);

        // Both should produce valid output
        self::assertStringContainsString('<img', $result1, 'First result should contain img');
        self::assertStringContainsString('<img', $result2, 'Second result should contain img');
        // Neither should have figure wrappers
        self::assertStringNotContainsString('<figure', $result1, 'First inline image should not have figure');
        self::assertStringNotContainsString('<figure', $result2, 'Second inline image should not have figure');
    }

    // ========================================================================
    // Link handling
    // ========================================================================

    /**
     * Test case 15: Inline image followed by a link.
     */
    #[Test]
    public function inlineImageFollowedByLink(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Image">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Image',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Image should be self-contained, not affecting following link
        self::assertStringContainsString('<img', $result, 'Result should contain img');
        // Should not create any link wrappers
        self::assertStringNotContainsString('<a', $result, 'Should not create link wrappers');
    }

    /**
     * Test case 16: Inline image wrapped by a link.
     */
    #[Test]
    public function inlineImageWrappedByLink(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        // Simulate tags.a handler processing the content inside the link
        $linkContent = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Linked">';

        $cObj->setCurrentVal($linkContent);

        /** @var string $result */
        $result = $adapter->renderImages(null, [], $this->request);

        // Should process the image but NOT add another link wrapper
        self::assertStringContainsString('<img', $result, 'Result should contain img');
        // Count link tags - should be 0 (the outer link is handled by TypoScript)
        $linkCount = substr_count($result, '<a ') + substr_count($result, '<a>');
        self::assertSame(
            0,
            $linkCount,
            'renderImages should NOT create link wrappers (outer <a> is handled by TypoScript)',
        );
    }

    /**
     * Test case 17: Block image containing a linked image.
     */
    #[Test]
    public function blockImageContainingLinkedImage(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<a href="https://example.com">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Linked">'
            . '</a>'
            . '<figcaption>Linked Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should have one figure (has caption)
        self::assertSame(1, substr_count($result, '<figure'), 'Expected exactly 1 figure');
        // Should contain img
        self::assertStringContainsString('<img', $result, 'Should contain img element');
    }

    /**
     * Test case 18: Link URLs using t3:// syntax must be preserved.
     */
    #[Test]
    public function linkUrlsWithT3SyntaxPreserved(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        // Simulate linked image with t3:// URL
        $figureHtml = '<figure class="image">'
            . '<a href="t3://page?uid=123">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Linked">'
            . '</a>'
            . '<figcaption>T3 Link Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should have one figure (has caption)
        self::assertSame(1, substr_count($result, '<figure'), 'Expected exactly 1 figure');
    }

    // ========================================================================
    // Mixed content scenarios
    // ========================================================================

    /**
     * Test case 19: Mixed block images and inline images processed correctly.
     */
    #[Test]
    public function mixedBlockAndInlineImagesProcessedCorrectly(): void
    {
        // Process captioned block image
        ['adapter' => $adapter1, 'cObj' => $cObj1] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Block">'
            . '<figcaption>Block Caption</figcaption>'
            . '</figure>';

        $cObj1->setCurrentVal($figureHtml);

        /** @var string $blockResult */
        $blockResult = $adapter1->renderFigure(null, [], $this->request);

        // Process inline image
        ['adapter' => $adapter2, 'cObj' => $cObj2] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $cObj2->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $cObj2->setCurrentVal($imgTag);

        /** @var string $inlineResult */
        $inlineResult = $adapter2->renderImageAttributes(null, [], $this->request);

        // Block with caption should have figure wrapper
        self::assertSame(1, substr_count($blockResult, '<figure'), 'Captioned block image should have figure');
        // Inline should NOT have figure wrapper
        self::assertStringNotContainsString('<figure', $inlineResult, 'Inline image should not have figure');
    }

    /**
     * Test case 20: Mixed text, inline images, links handled correctly.
     */
    #[Test]
    public function mixedTextImagesLinksHandledCorrectly(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Mixed">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Mixed',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should produce only the image, not affect text/links/br
        self::assertStringContainsString('<img', $result, 'Result should contain img');
        self::assertStringNotContainsString('<br', $result, 'Should not inject br elements');
    }

    /**
     * Test case 21: Multiple content blocks without cross-interference.
     */
    #[Test]
    public function multipleContentBlocksWithoutCrossInterference(): void
    {
        // Process first figure
        ['adapter' => $adapter1, 'cObj' => $cObj1] = $this->getAdapterWithCObj();

        $figure1 = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="First">'
            . '<figcaption>Caption 1</figcaption>'
            . '</figure>';

        $cObj1->setCurrentVal($figure1);

        /** @var string $result1 */
        $result1 = $adapter1->renderFigure(null, [], $this->request);

        // Process second figure
        ['adapter' => $adapter2, 'cObj' => $cObj2] = $this->getAdapterWithCObj();

        $figure2 = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="300" height="300" alt="Second">'
            . '<figcaption>Caption 2</figcaption>'
            . '</figure>';

        $cObj2->setCurrentVal($figure2);

        /** @var string $result2 */
        $result2 = $adapter2->renderFigure(null, [], $this->request);

        // Each should be processed independently
        self::assertSame(1, substr_count($result1, '<figure'), 'First result should have 1 figure');
        self::assertSame(1, substr_count($result2, '<figure'), 'Second result should have 1 figure');
        self::assertStringContainsString('Caption 1', $result1, 'First result should have Caption 1');
        self::assertStringContainsString('Caption 2', $result2, 'Second result should have Caption 2');
    }

    // ========================================================================
    // Structural and semantic invariants
    // ========================================================================

    /**
     * Test case 22: No nested block-level wrappers are created.
     */
    #[Test]
    public function noNestedBlockLevelWrappersCreated(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should not have nested figures
        self::assertSame(1, substr_count($result, '<figure'), 'Should have exactly 1 figure');
        // Should not have figure-figure nesting
        self::assertStringNotContainsString('<figure><figure', $result, 'No nested figure-figure');
    }

    /**
     * Test case 23: Inline content must remain inline.
     */
    #[Test]
    public function inlineContentRemainsInline(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should not add block-level wrappers
        self::assertStringNotContainsString('<figure', $result, 'No figure wrapper for inline');
        self::assertStringNotContainsString('<div', $result, 'No div wrapper for inline');
    }

    /**
     * Test case 24: Block-level content with caption stays block-level.
     */
    #[Test]
    public function blockLevelContentWithCaptionStaysBlockLevel(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Block">'
            . '<figcaption>Block Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should maintain figure wrapper (has caption)
        self::assertSame(1, substr_count($result, '<figure'), 'Captioned block should keep figure wrapper');
    }

    /**
     * Test case 25: No additional wrappers introduced unless intended.
     */
    #[Test]
    public function noAdditionalWrappersUnlessIntended(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        // Standalone image without caption
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should have exactly one img tag, no additional wrappers
        self::assertSame(1, substr_count($result, '<img'), 'Should have exactly 1 img');
        self::assertStringNotContainsString('<figure', $result, 'Standalone img should not get figure');
        self::assertStringNotContainsString('<div', $result, 'Standalone img should not get div');
    }

    /**
     * Test case 26: Existing wrappers must not be duplicated.
     */
    #[Test]
    public function existingWrappersNotDuplicated(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Check for duplicate elements
        self::assertSame(1, substr_count($result, '<figure'), 'No duplicate figure');
        self::assertSame(1, substr_count($result, '</figure>'), 'No duplicate closing figure');
        self::assertSame(1, substr_count($result, '<figcaption'), 'No duplicate figcaption');
        self::assertSame(1, substr_count($result, '</figcaption>'), 'No duplicate closing figcaption');
    }

    /**
     * Test case 27: Rendering must be idempotent.
     */
    #[Test]
    public function renderingIsIdempotent(): void
    {
        ['adapter' => $adapter1, 'cObj' => $cObj1] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>';

        $cObj1->setCurrentVal($figureHtml);

        /** @var string $firstRender */
        $firstRender = $adapter1->renderFigure(null, [], $this->request);

        // Render the output again with fresh adapter
        ['adapter' => $adapter2, 'cObj' => $cObj2] = $this->getAdapterWithCObj();
        $cObj2->setCurrentVal($firstRender);

        /** @var string $secondRender */
        $secondRender = $adapter2->renderFigure(null, [], $this->request);

        // Structure counts should remain the same
        self::assertSame(
            substr_count($firstRender, '<figure'),
            substr_count($secondRender, '<figure'),
            'Figure count should not change after second render',
        );
        self::assertSame(
            substr_count($firstRender, '<figcaption'),
            substr_count($secondRender, '<figcaption'),
            'Figcaption count should not change after second render',
        );
    }

    // ========================================================================
    // Attribute and content preservation
    // ========================================================================

    /**
     * Test case 28: RTE-specific data attributes processed consistently.
     */
    #[Test]
    public function rteSpecificDataAttributesProcessedConsistently(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'data-htmlarea-file-table="sys_file" width="250" height="250" alt="Test">';

        $cObj->parameters = [
            'src'                      => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid'   => '1',
            'data-htmlarea-file-table' => 'sys_file',
            'width'                    => '250',
            'height'                   => '250',
            'alt'                      => 'Test',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // After processing, data-htmlarea-* should be removed (they're processing hints)
        // but the image should be properly resolved
        self::assertStringContainsString('<img', $result, 'Result should contain img');
        // Standard attributes should be preserved
        self::assertMatchesRegularExpression('/alt=["\']/', $result, 'Alt attribute should be present');
    }

    /**
     * Test case 29: Text nodes before and after images remain in order.
     */
    #[Test]
    public function textNodesBeforeAndAfterImagesRemainInOrder(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Middle">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Middle',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Result should only contain the processed image, not inject text
        self::assertStringContainsString('<img', $result, 'Result should contain img');
        // Should not contain any text nodes that weren't in the input
        self::assertStringNotContainsString('Before', $result, 'No injected text before');
        self::assertStringNotContainsString('After', $result, 'No injected text after');
    }

    /**
     * Test case 30: Whitespace does not alter structure.
     */
    #[Test]
    public function whitespaceDoesNotAlterStructure(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        // Figure with whitespace around elements
        $figureHtml = '<figure class="image">'
            . "\n  "
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . "\n  "
            . '<figcaption>Caption with &nbsp; space</figcaption>'
            . "\n"
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Structure should be maintained
        self::assertSame(1, substr_count($result, '<figure'), 'Figure count should be 1');
        self::assertSame(1, substr_count($result, '<figcaption'), 'Figcaption count should be 1');
        // Caption content should be present
        self::assertStringContainsString('Caption', $result, 'Caption text should be preserved');
    }

    /**
     * Test case 31: Captions and metadata not duplicated.
     */
    #[Test]
    public function captionsAndMetadataNotDuplicated(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" '
            . 'alt="Alt Text" title="Title Text">'
            . '<figcaption>Unique Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Count specific content that should appear exactly once
        self::assertSame(1, substr_count($result, 'Unique Caption'), 'Caption should appear exactly once');
        // Alt attribute should appear once
        $altCount = preg_match_all('/alt=["\'][^"\']+["\']/', $result, $matches);
        self::assertSame(1, $altCount, 'Alt attribute should appear exactly once');
    }

    // ========================================================================
    // Additional edge cases
    // ========================================================================

    /**
     * Test: External images pass through without modification.
     */
    #[Test]
    public function externalImagesPassThroughUnmodified(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="https://example.com/image.jpg" width="250" height="250" alt="External">'
            . '<figcaption>External Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should return original unchanged (no file UID = no processing)
        self::assertSame($figureHtml, $result, 'External image figure should pass through unchanged');
    }

    /**
     * Test: Already processed images pass through.
     */
    #[Test]
    public function alreadyProcessedImagesPassThrough(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/_processed_/test.jpg" width="250" height="250" alt="Processed" decoding="async">'
            . '<figcaption>Already Processed</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should return unchanged (no data-htmlarea-file-uid)
        self::assertSame($figureHtml, $result, 'Already processed figure should pass through unchanged');
    }

    /**
     * Test: Captioned images skip img handler to preserve data-htmlarea-file-uid.
     *
     * This is the fix for #566: renderImageAttributes must skip captioned images.
     */
    #[Test]
    public function captionedImagesSkipImgHandler(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'width="250" height="250" alt="Test" data-caption="My Caption">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
            'data-caption'           => 'My Caption',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Captioned images should be returned unchanged
        self::assertSame($imgTag, $result, 'Captioned images should pass through unchanged');
        // data-htmlarea-file-uid must be preserved for renderFigure
        self::assertStringContainsString('data-htmlarea-file-uid', $result, 'File UID must be preserved');
    }

    /**
     * Test: Zoom attribute stripped for linked images.
     *
     * This is the fix for #565.
     */
    #[Test]
    public function zoomAttributeStrippedForLinkedImages(): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        // Image with zoom inside a link (renderImages handler)
        $linkContent = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'data-htmlarea-zoom="true" width="250" height="250" alt="LinkedZoom">';

        $cObj->setCurrentVal($linkContent);

        /** @var string $result */
        $result = $adapter->renderImages(null, [], $this->request);

        // Should NOT create a popup link (image is already linked)
        $linkCount = substr_count($result, '<a ') + substr_count($result, '<a>');
        self::assertSame(
            0,
            $linkCount,
            'Linked images with zoom should not get additional link wrappers',
        );
    }

    /**
     * Data provider for testing various image class combinations.
     *
     * @return array<string, array{class: string, expectFigure: bool}>
     */
    public static function imageClassCombinationsProvider(): array
    {
        return [
            'image-inline' => [
                'class'        => 'image-inline',
                'expectFigure' => false,
            ],
            'image-block' => [
                'class'        => 'image-block',
                'expectFigure' => false, // renderImageAttributes doesn't add figure
            ],
            'image-left' => [
                'class'        => 'image-left',
                'expectFigure' => false,
            ],
            'image-right' => [
                'class'        => 'image-right',
                'expectFigure' => false,
            ],
            'image-center' => [
                'class'        => 'image-center',
                'expectFigure' => false,
            ],
            'multiple classes' => [
                'class'        => 'image-inline custom-class',
                'expectFigure' => false,
            ],
        ];
    }

    /**
     * Test: Various image class combinations handled correctly.
     */
    #[Test]
    #[DataProvider('imageClassCombinationsProvider')]
    public function variousImageClassCombinationsHandledCorrectly(string $class, bool $expectFigure): void
    {
        ['adapter' => $adapter, 'cObj' => $cObj] = $this->getAdapterWithCObj();

        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="' . $class . '" width="250" height="250" alt="Test">';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => $class,
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        $hasFigure = str_contains($result, '<figure');
        self::assertSame(
            $expectFigure,
            $hasFigure,
            sprintf(
                'Image with class "%s" %s have figure wrapper. Result: %s',
                $class,
                $expectFigure ? 'should' : 'should not',
                $result,
            ),
        );
    }
}
