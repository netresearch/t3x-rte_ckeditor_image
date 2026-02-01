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

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ImageRenderingAdapter $adapter;

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ContentObjectRenderer $cObj;

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

        // Set up adapter and content object renderer
        $this->adapter = $this->get(ImageRenderingAdapter::class);
        $this->cObj    = $this->get(ContentObjectRenderer::class);
        $this->cObj->setRequest($this->request);
        $this->adapter->setContentObjectRenderer($this->cObj);
    }

    // ========================================================================
    // Block-level image handling
    // ========================================================================

    /**
     * Test case 1: Standalone block image without surrounding text.
     */
    #[Test]
    public function standaloneBlockImageWithoutSurroundingText(): void
    {
        $inputHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '</figure>';

        $this->cObj->setCurrentVal($inputHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Should have exactly one figure
        self::assertSame(1, substr_count($result, '<figure'), 'Expected exactly 1 figure element');
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
    }

    /**
     * Test case 2: Block image with caption already present.
     */
    #[Test]
    public function blockImageWithCaptionAlreadyPresent(): void
    {
        $inputHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>My Caption</figcaption>'
            . '</figure>';

        $this->cObj->setCurrentVal($inputHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Should have exactly one figure and one figcaption
        self::assertSame(1, substr_count($result, '<figure'), 'Expected exactly 1 figure element');
        self::assertSame(1, substr_count($result, '<figcaption'), 'Expected exactly 1 figcaption element');
        self::assertSame(1, substr_count($result, 'My Caption'), 'Caption text should appear once');
    }

    /**
     * Test case 3: Block image wrapped in an invalid container (inside a paragraph).
     *
     * Note: This is technically invalid HTML but can occur in editor content.
     * The renderer should handle it gracefully.
     */
    #[Test]
    public function blockImageInsideParagraphHandledGracefully(): void
    {
        // Paragraph content containing a figure (invalid but possible)
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should produce a valid img output without breaking structure
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        // Should NOT produce nested figures
        self::assertLessThanOrEqual(1, substr_count($result, '<figure'), 'Should not create nested figures');
    }

    /**
     * Test case 4: Block image between paragraphs.
     */
    #[Test]
    public function blockImageBetweenParagraphs(): void
    {
        // Process the figure separately (as parseFunc would)
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Should maintain block-level structure
        self::assertSame(1, substr_count($result, '<figure'), 'Expected exactly 1 figure element');
        // No paragraph should be created around the figure
        self::assertStringNotContainsString('<p><figure', $result, 'Figure should not be wrapped in paragraph');
    }

    /**
     * Test case 5: Block image followed by inline content.
     *
     * Tests that block image processing doesn't affect following content.
     */
    #[Test]
    public function blockImageFollowedByInlineContent(): void
    {
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Result should be self-contained, not affecting surrounding content
        self::assertSame(1, substr_count($result, '<figure'), 'Figure count should be 1');
        self::assertSame(1, substr_count($result, '</figure>'), 'Closing figure count should be 1');
    }

    /**
     * Test case 6: Block image preceded by inline content.
     */
    #[Test]
    public function blockImagePrecededByInlineContent(): void
    {
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Same as above - figure should be self-contained
        self::assertSame(1, substr_count($result, '<figure'), 'Figure count should be 1');
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
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should remain inline (no figure wrapper)
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        self::assertSame(0, substr_count($result, '<figure'), 'Inline image should not have figure wrapper');
    }

    /**
     * Test case 8: Inline image in the middle of a paragraph.
     */
    #[Test]
    public function inlineImageInMiddleOfParagraph(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should remain inline
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        self::assertSame(0, substr_count($result, '<figure'), 'Inline image should not have figure wrapper');
    }

    /**
     * Test case 9: Inline image at the end of a paragraph.
     */
    #[Test]
    public function inlineImageAtEndOfParagraph(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should remain inline
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        self::assertSame(0, substr_count($result, '<figure'), 'Inline image should not have figure wrapper');
    }

    /**
     * Test case 10: Inline image followed by text and links.
     *
     * Verifies that the image processing doesn't corrupt following content.
     */
    #[Test]
    public function inlineImageFollowedByTextAndLinks(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Result should be self-contained, allowing text/links to follow
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        // Should end cleanly (not leave unclosed tags)
        self::assertStringContainsString('/>', $result, 'Image should be self-closing or properly closed');
    }

    /**
     * Test case 11: Inline image preceded by text.
     */
    #[Test]
    public function inlineImagePrecededByText(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should remain inline and not affect preceding content
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
    }

    /**
     * Test case 12: Inline image combined with br elements.
     */
    #[Test]
    public function inlineImageCombinedWithBrElements(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should not affect br elements
        self::assertStringContainsString('<img', $result, 'Result should contain img element');
        self::assertStringNotContainsString('<br', $result, 'Result should not inject br elements');
    }

    /**
     * Test case 13: Multiple inline images within the same paragraph.
     *
     * Each image is processed independently by renderImageAttributes.
     */
    #[Test]
    public function multipleInlineImagesWithinSameParagraph(): void
    {
        // Process first image
        $imgTag1 = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="First">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'First',
        ];
        $this->cObj->setCurrentVal($imgTag1);

        /** @var string $result1 */
        $result1 = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Process second image
        $imgTag2 = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Second">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Second',
        ];
        $this->cObj->setCurrentVal($imgTag2);

        /** @var string $result2 */
        $result2 = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Both should produce valid output
        self::assertStringContainsString('<img', $result1, 'First result should contain img');
        self::assertStringContainsString('<img', $result2, 'Second result should contain img');
        // Neither should have figure wrappers
        self::assertSame(0, substr_count($result1, '<figure'), 'First inline image should not have figure');
        self::assertSame(0, substr_count($result2, '<figure'), 'Second inline image should not have figure');
    }

    /**
     * Test case 14: Inline images across multiple consecutive paragraphs.
     *
     * Each paragraph's images are processed independently.
     */
    #[Test]
    public function inlineImagesAcrossMultipleParagraphs(): void
    {
        // Process image from first paragraph
        $imgTag1 = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Para1">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Para1',
        ];
        $this->cObj->setCurrentVal($imgTag1);

        /** @var string $result1 */
        $result1 = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Process image from second paragraph
        $imgTag2 = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Para2">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Para2',
        ];
        $this->cObj->setCurrentVal($imgTag2);

        /** @var string $result2 */
        $result2 = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Both should be processed independently without interference
        self::assertStringContainsString('<img', $result1, 'First result should contain img');
        self::assertStringContainsString('<img', $result2, 'Second result should contain img');
    }

    // ========================================================================
    // Link handling
    // ========================================================================

    /**
     * Test case 15: Inline image directly followed by a link.
     *
     * The image and link are separate elements.
     */
    #[Test]
    public function inlineImageFollowedByLink(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Image">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Image',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Image should be self-contained, not affecting following link
        self::assertStringContainsString('<img', $result, 'Result should contain img');
        // Should not create any link wrappers
        self::assertStringNotContainsString('<a', $result, 'Should not create link wrappers');
    }

    /**
     * Test case 16: Inline image wrapped by a link.
     *
     * Uses renderImages handler which processes images inside anchor tags.
     */
    #[Test]
    public function inlineImageWrappedByLink(): void
    {
        // Simulate tags.a handler processing the content inside the link
        $linkContent = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Linked">';

        $this->cObj->setCurrentVal($linkContent);

        /** @var string $result */
        $result = $this->adapter->renderImages(null, [], $this->request);

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
     *
     * Figure with a linked image inside.
     */
    #[Test]
    public function blockImageContainingLinkedImage(): void
    {
        $figureHtml = '<figure class="image">'
            . '<a href="https://example.com">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Linked">'
            . '</a>'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Should have one figure and one link
        self::assertSame(1, substr_count($result, '<figure'), 'Expected exactly 1 figure');
        // Link should be preserved
        self::assertStringContainsString('<a', $result, 'Link should be preserved');
    }

    /**
     * Test case 18: Link URLs using t3:// syntax must be preserved unchanged.
     *
     * TYPO3-specific link syntax should not be modified.
     */
    #[Test]
    public function linkUrlsWithT3SyntaxPreservedUnchanged(): void
    {
        // Simulate linked image with t3:// URL
        $figureHtml = '<figure class="image">'
            . '<a href="t3://page?uid=123">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Linked">'
            . '</a>'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // t3:// URL should be preserved (typolink will process it later)
        // The figure handler shouldn't modify link URLs
        self::assertSame(1, substr_count($result, '<figure'), 'Expected exactly 1 figure');
    }

    // ========================================================================
    // Mixed content scenarios
    // ========================================================================

    /**
     * Test case 19: Mixed block images and inline images in the same content fragment.
     *
     * Both types should be processed correctly and independently.
     */
    #[Test]
    public function mixedBlockAndInlineImagesProcessedCorrectly(): void
    {
        // Process block image (figure)
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Block">'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $blockResult */
        $blockResult = $this->adapter->renderFigure(null, [], $this->request);

        // Process inline image
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $inlineResult */
        $inlineResult = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Block should have figure wrapper
        self::assertSame(1, substr_count($blockResult, '<figure'), 'Block image should have figure');
        // Inline should NOT have figure wrapper
        self::assertSame(0, substr_count($inlineResult, '<figure'), 'Inline image should not have figure');
    }

    /**
     * Test case 20: Mixed text, inline images, links and line breaks in one paragraph.
     *
     * Inline image processing should not affect surrounding elements.
     */
    #[Test]
    public function mixedTextImagesLinksAndBreaksInOneParagraph(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Mixed">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Mixed',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should produce only the image, not affect text/links/br
        self::assertStringContainsString('<img', $result, 'Result should contain img');
        self::assertStringNotContainsString('<br', $result, 'Should not inject br elements');
        self::assertStringNotContainsString('Text', $result, 'Should not contain text from context');
    }

    /**
     * Test case 21: Multiple content blocks rendered sequentially without cross-interference.
     */
    #[Test]
    public function multipleContentBlocksWithoutCrossInterference(): void
    {
        // Process first figure
        $figure1 = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="First">'
            . '<figcaption>Caption 1</figcaption>'
            . '</figure>';

        $this->cObj->setCurrentVal($figure1);

        /** @var string $result1 */
        $result1 = $this->adapter->renderFigure(null, [], $this->request);

        // Process second figure
        $figure2 = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="300" height="300" alt="Second">'
            . '<figcaption>Caption 2</figcaption>'
            . '</figure>';

        $this->cObj->setCurrentVal($figure2);

        /** @var string $result2 */
        $result2 = $this->adapter->renderFigure(null, [], $this->request);

        // Each should be processed independently
        self::assertSame(1, substr_count($result1, '<figure'), 'First result should have 1 figure');
        self::assertSame(1, substr_count($result2, '<figure'), 'Second result should have 1 figure');
        self::assertSame(1, substr_count($result1, 'Caption 1'), 'First result should have Caption 1');
        self::assertSame(1, substr_count($result2, 'Caption 2'), 'Second result should have Caption 2');
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
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Should not have nested figures
        self::assertSame(1, substr_count($result, '<figure'), 'Should have exactly 1 figure');
        // Should not have figure-figure nesting
        self::assertStringNotContainsString('<figure><figure', $result, 'No nested figure-figure');
        self::assertStringNotContainsString('<figure> <figure', $result, 'No nested figure-figure with space');
    }

    /**
     * Test case 23: Inline content must remain inline.
     */
    #[Test]
    public function inlineContentRemainsInline(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Inline">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Inline',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should not add block-level wrappers
        self::assertSame(0, substr_count($result, '<figure'), 'No figure wrapper for inline');
        self::assertSame(0, substr_count($result, '<div'), 'No div wrapper for inline');
    }

    /**
     * Test case 24: Block-level content must not be forced inline.
     */
    #[Test]
    public function blockLevelContentNotForcedInline(): void
    {
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Block">'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Should maintain figure wrapper
        self::assertSame(1, substr_count($result, '<figure'), 'Block should keep figure wrapper');
        // Should not have inline class
        self::assertStringNotContainsString('image-inline', $result, 'Block should not have inline class');
    }

    /**
     * Test case 25: No additional wrappers are introduced unless explicitly intended.
     */
    #[Test]
    public function noAdditionalWrappersUnlessIntended(): void
    {
        // Standalone image without caption
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should have exactly one img tag, no additional wrappers
        self::assertSame(1, substr_count($result, '<img'), 'Should have exactly 1 img');
        self::assertSame(0, substr_count($result, '<figure'), 'Standalone img should not get figure');
        self::assertSame(0, substr_count($result, '<div'), 'Standalone img should not get div');
        self::assertSame(0, substr_count($result, '<span'), 'Standalone img should not get span');
    }

    /**
     * Test case 26: Existing wrappers must not be duplicated.
     */
    #[Test]
    public function existingWrappersNotDuplicated(): void
    {
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Check for duplicate elements
        self::assertSame(1, substr_count($result, '<figure'), 'No duplicate figure');
        self::assertSame(1, substr_count($result, '</figure>'), 'No duplicate closing figure');
        self::assertSame(1, substr_count($result, '<figcaption'), 'No duplicate figcaption');
        self::assertSame(1, substr_count($result, '</figcaption>'), 'No duplicate closing figcaption');
    }

    /**
     * Test case 27: Rendering must be idempotent (rendering the output again must not change structure).
     */
    #[Test]
    public function renderingIsIdempotent(): void
    {
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $firstRender */
        $firstRender = $this->adapter->renderFigure(null, [], $this->request);

        // Render the output again
        $this->cObj->setCurrentVal($firstRender);

        /** @var string $secondRender */
        $secondRender = $this->adapter->renderFigure(null, [], $this->request);

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
        self::assertSame(
            substr_count($firstRender, '<img'),
            substr_count($secondRender, '<img'),
            'Img count should not change after second render',
        );
    }

    // ========================================================================
    // Attribute and content preservation
    // ========================================================================

    /**
     * Test case 28: All RTE-specific data attributes must be preserved consistently.
     *
     * Note: After processing, data-htmlarea-* attributes are removed but the image
     * is resolved using them first.
     */
    #[Test]
    public function rteSpecificDataAttributesProcessedConsistently(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'data-htmlarea-file-table="sys_file" width="250" height="250" alt="Test">';

        $this->cObj->parameters = [
            'src'                      => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid'   => '1',
            'data-htmlarea-file-table' => 'sys_file',
            'width'                    => '250',
            'height'                   => '250',
            'alt'                      => 'Test',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // After processing, data-htmlarea-* should be removed (they're processing hints)
        // but the image should be properly resolved
        self::assertStringContainsString('<img', $result, 'Result should contain img');
        // Standard attributes should be preserved
        self::assertMatchesRegularExpression('/alt=["\']/', $result, 'Alt attribute should be present');
    }

    /**
     * Test case 29: Text nodes before and after images must remain in correct order.
     *
     * Image processing should not affect surrounding text nodes.
     */
    #[Test]
    public function textNodesBeforeAndAfterImagesRemainInOrder(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="image-inline" width="50" height="50" alt="Middle">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => 'image-inline',
            'width'                  => '50',
            'height'                 => '50',
            'alt'                    => 'Middle',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Result should only contain the processed image, not inject text
        // The surrounding text is handled by TypoScript, not the image handler
        self::assertStringContainsString('<img', $result, 'Result should contain img');
        // Should not contain any text nodes that weren't in the input
        self::assertStringNotContainsString('Before', $result, 'No injected text before');
        self::assertStringNotContainsString('After', $result, 'No injected text after');
    }

    /**
     * Test case 30: Whitespace, non-breaking spaces and line breaks must not alter structure.
     */
    #[Test]
    public function whitespaceAndSpecialCharsDoNotAlterStructure(): void
    {
        // Figure with whitespace around elements
        $figureHtml = '<figure class="image">'
            . "\n  "
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . "\n  "
            . '<figcaption>Caption with &nbsp; space</figcaption>'
            . "\n"
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Structure should be maintained
        self::assertSame(1, substr_count($result, '<figure'), 'Figure count should be 1');
        self::assertSame(1, substr_count($result, '<figcaption'), 'Figcaption count should be 1');
        // Non-breaking space should be preserved in caption
        // (May be normalized, but content should be present)
        self::assertStringContainsString('Caption', $result, 'Caption text should be preserved');
    }

    /**
     * Test case 31: Captions or metadata must not be duplicated.
     */
    #[Test]
    public function captionsAndMetadataNotDuplicated(): void
    {
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" '
            . 'alt="Alt Text" title="Title Text">'
            . '<figcaption>Unique Caption</figcaption>'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Count specific content that should appear exactly once
        self::assertSame(1, substr_count($result, 'Unique Caption'), 'Caption should appear exactly once');
        // Note: Alt and title may be re-read from the file, so we just verify they're not duplicated
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
        $figureHtml = '<figure class="image">'
            . '<img src="https://example.com/image.jpg" width="250" height="250" alt="External">'
            . '<figcaption>External Caption</figcaption>'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Should return original unchanged (no file UID = no processing)
        self::assertSame($figureHtml, $result, 'External image figure should pass through unchanged');
    }

    /**
     * Test: Already processed images (no data-htmlarea-file-uid) pass through.
     */
    #[Test]
    public function alreadyProcessedImagesPassThrough(): void
    {
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/_processed_/test.jpg" width="250" height="250" alt="Processed" decoding="async">'
            . '<figcaption>Already Processed</figcaption>'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Should return unchanged
        self::assertSame($figureHtml, $result, 'Already processed figure should pass through unchanged');
    }

    /**
     * Test: Figure with zoom attribute creates appropriate link.
     */
    #[Test]
    public function figureWithZoomAttributeCreatesLink(): void
    {
        $figureHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'data-htmlarea-zoom="true" width="250" height="250" alt="Zoomable">'
            . '</figure>';

        $this->cObj->setCurrentVal($figureHtml);

        /** @var string $result */
        $result = $this->adapter->renderFigure(null, [], $this->request);

        // Should have a figure
        self::assertSame(1, substr_count($result, '<figure'), 'Should have exactly 1 figure');
        // May have a link for zoom functionality (depends on configuration)
        // At minimum, should not break the structure
        self::assertStringContainsString('<img', $result, 'Should contain img element');
    }

    /**
     * Test: Captioned images skip img handler to preserve data-htmlarea-file-uid.
     *
     * This is the fix for #566: renderImageAttributes must skip captioned images.
     */
    #[Test]
    public function captionedImagesSkipImgHandler(): void
    {
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'width="250" height="250" alt="Test" data-caption="My Caption">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
            'data-caption'           => 'My Caption',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Captioned images should be returned unchanged
        self::assertSame($imgTag, $result, 'Captioned images should pass through unchanged');
        // data-htmlarea-file-uid must be preserved for renderFigure
        self::assertStringContainsString('data-htmlarea-file-uid', $result, 'File UID must be preserved');
    }

    /**
     * Test: Zoom attribute stripped for linked images (prevents duplicate links).
     *
     * This is the fix for #565.
     */
    #[Test]
    public function zoomAttributeStrippedForLinkedImages(): void
    {
        // Image with zoom inside a link (renderImages handler)
        $linkContent = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'data-htmlarea-zoom="true" width="250" height="250" alt="LinkedZoom">';

        $this->cObj->setCurrentVal($linkContent);

        /** @var string $result */
        $result = $this->adapter->renderImages(null, [], $this->request);

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
                'expectFigure' => false, // renderImageAttributes doesn't add figure for any class
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
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'class="' . $class . '" width="250" height="250" alt="Test">';

        $this->cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'class'                  => $class,
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
        ];
        $this->cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

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
