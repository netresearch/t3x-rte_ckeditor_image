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
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for standalone img tag rendering.
 *
 * Tests to verify that the tags.img handler (renderImageAttributes) skips
 * processing for captioned images to preserve data-htmlarea-file-uid for
 * the subsequent tags.figure handler (renderFigure).
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566
 */
final class ImageTagRenderingTest extends FunctionalTestCase
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
     * Test that renderImageAttributes skips processing for captioned images.
     *
     * This is the fix for bug #546 and #566: When an img tag has data-caption attribute,
     * the tags.img handler (renderImageAttributes) must skip processing entirely to
     * preserve the data-htmlarea-file-uid attribute for the subsequent tags.figure
     * handler (renderFigure).
     *
     * FIX: renderImageAttributes should return original content unchanged for captioned
     * images, preserving data-htmlarea-file-uid for renderFigure to use.
     *
     * Current CKEditor output for captioned images:
     * <figure class="image">
     *   <img src="..." data-htmlarea-file-uid="1" data-caption="Caption">
     *   <figcaption>Caption</figcaption>
     * </figure>
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566
     */
    #[Test]
    public function renderImageAttributesDoesNotCreateFigureWrapperForCaptionedImage(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // The original img tag as it appears in the content
        $originalImgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'width="250" height="250" alt="Test" data-caption="My Caption" />';

        // Simulate tags.img handler receiving an img with data-caption
        // parseFunc sets both parameters (parsed attributes) and currentVal (original tag)
        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
            'data-caption'           => 'My Caption',
        ];
        $cObj->setCurrentVal($originalImgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // CRITICAL: The original content should be returned unchanged
        // This preserves data-htmlarea-file-uid for renderFigure to use
        self::assertSame(
            $originalImgTag,
            $result,
            'Captioned images should be returned unchanged to preserve data-htmlarea-file-uid. '
            . 'Result: ' . $result,
        );

        // Verify data-htmlarea-file-uid is preserved (this is critical for #566)
        self::assertStringContainsString(
            'data-htmlarea-file-uid',
            $result,
            'data-htmlarea-file-uid must be preserved for renderFigure to resolve the file.',
        );

        // Should NOT contain figure wrapper (that's renderFigure's job)
        self::assertStringNotContainsString(
            '<figure',
            $result,
            'renderImageAttributes should NOT create figure wrappers.',
        );
    }

    /**
     * Test that renderImageAttributes handles img WITHOUT caption correctly.
     *
     * Images without data-caption should be processed normally without figure wrapper.
     */
    #[Test]
    public function renderImageAttributesHandlesImageWithoutCaption(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // Simulate tags.img handler receiving an img WITHOUT data-caption
        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
        ];

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Should output just an img tag, no figure wrapper
        $figureCount = substr_count($result, '<figure');
        self::assertSame(
            0,
            $figureCount,
            'Uncaptioned images should not have figure wrappers. Result: ' . $result,
        );

        self::assertStringContainsString('<img', $result, 'Output should contain img element');
    }

    // ========================================================================
    // Issue #565 Tests - Linked Images (duplicate <a> tag prevention)
    // ========================================================================

    /**
     * Test that renderImages does not create duplicate link wrappers.
     *
     * When an image is already wrapped in a link (<a><img/></a>), the tags.a
     * handler (renderImages) processes the inner content. It should NOT add
     * another link wrapper - the original <a> is preserved by TypoScript.
     *
     * Bug #565: Users reported <a><a><img/></a></a> appearing after editing
     * linked images in CKEditor.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/565
     */
    #[Test]
    public function renderImagesDoesNotCreateDuplicateLinkWrapper(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // Simulate tags.a handler: currentVal is the INNER content of the <a> tag
        // The outer <a href="https://example.com"> wrapper is handled by TypoScript
        $linkContent = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'data-htmlarea-file-table="sys_file" width="250" height="250" alt="Test">';

        $cObj->setCurrentVal($linkContent);

        /** @var string $result */
        $result = $adapter->renderImages(null, [], $this->request);

        // Count <a> tags in the result - should be ZERO
        // The renderImages handler processes images INSIDE links, it should NOT
        // create additional link wrappers
        $linkCount = substr_count($result, '<a ');
        $linkCount += substr_count($result, '<a>');

        self::assertSame(
            0,
            $linkCount,
            'renderImages should NOT create link wrappers (the outer <a> is handled by TypoScript). '
            . 'Found ' . $linkCount . ' <a> tags. Result: ' . $result,
        );

        // Should still contain the processed img
        self::assertStringContainsString(
            '<img',
            $result,
            'Result should contain the processed img element. Result: ' . $result,
        );
    }

    /**
     * Test that linked images with zoom attribute do not get popup links added.
     *
     * When an image is already inside a link, the zoom/clickenlarge feature
     * should be disabled - the image is already linked, no popup needed.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/565
     */
    #[Test]
    public function renderImagesStripsZoomAttributeFromLinkedImages(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // Image with zoom attribute inside a link
        $linkContent = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'data-htmlarea-file-table="sys_file" width="250" height="250" alt="Test" '
            . 'data-htmlarea-zoom="true">';

        $cObj->setCurrentVal($linkContent);

        /** @var string $result */
        $result = $adapter->renderImages(null, [], $this->request);

        // Should NOT contain any link wrapper (zoom should be stripped for linked images)
        // Count both <a > and <a> to catch all anchor variations
        $linkCount = substr_count($result, '<a ');
        $linkCount += substr_count($result, '<a>');

        self::assertSame(
            0,
            $linkCount,
            'Linked images should not get popup links from zoom attribute. '
            . 'Found ' . $linkCount . ' <a> tags. Result: ' . $result,
        );
    }

    /**
     * Test simulation of parseFunc processing order with proper handling.
     *
     * This test demonstrates the fix: when parseFunc processes content with
     * a captioned image inside a figure, renderImageAttributes skips the captioned
     * image (preserving data-htmlarea-file-uid), and renderFigure processes the
     * complete figure structure.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566
     */
    #[Test]
    public function combinedHandlersDoNotCreateNestedFigures(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // The original img tag with data-caption (as it appears in CKEditor output)
        $originalImgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'width="250" height="250" alt="Test" data-caption="Caption Text">';

        // Step 1: Simulate tags.img handler processing
        // parseFunc sets both parameters and currentVal
        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
            'data-caption'           => 'Caption Text',
        ];
        $cObj->setCurrentVal($originalImgTag);

        /** @var string $imgResult */
        $imgResult = $adapter->renderImageAttributes(null, [], $this->request);

        // With the fix, imgResult should be the original img tag (unchanged)
        // This preserves data-htmlarea-file-uid for renderFigure
        self::assertStringContainsString(
            'data-htmlarea-file-uid',
            $imgResult,
            'After tags.img handler, data-htmlarea-file-uid should be preserved.',
        );

        // Step 2: Build what the figure would look like after img processing
        // This simulates what parseFunc produces after tags.img runs
        $figureWithProcessedImg = '<figure class="image">'
            . $imgResult
            . '<figcaption>Caption Text</figcaption>'
            . '</figure>';

        // Step 3: Now simulate tags.figure handler processing this
        $cObj->setCurrentVal($figureWithProcessedImg);

        /** @var string $figureResult */
        $figureResult = $adapter->renderFigure(null, [], $this->request);

        // CRITICAL: After both handlers run, we should have exactly 1 figure
        $figureCount = substr_count($figureResult, '<figure');

        self::assertSame(
            1,
            $figureCount,
            'Combined handlers should produce exactly 1 figure, not nested figures. '
            . 'Got ' . $figureCount . ' figures. Result: ' . $figureResult,
        );

        // Should have exactly 1 figcaption
        $figcaptionCount = substr_count($figureResult, '<figcaption');

        self::assertSame(
            1,
            $figcaptionCount,
            'Combined handlers should produce exactly 1 figcaption, not duplicates. '
            . 'Got ' . $figcaptionCount . ' figcaptions. Result: ' . $figureResult,
        );
    }
}
