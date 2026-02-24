<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\TypoScript;

use Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Integration tests for parseFunc_RTE TypoScript processing.
 *
 * Tests the TypoScript preUserFunc behavior to verify that figure elements
 * are processed without creating nested structures.
 *
 * These tests verify the renderFigure method is idempotent - calling it
 * multiple times on the same content should not create nested structures.
 * This simulates what would happen if parseFunc_RTE processed content
 * that already went through the image rendering pipeline.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
 */
final class ParseFuncIntegrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
        'typo3/cms-frontend',
    ];

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private Site $site;

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ServerRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        // Import test data
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/sys_file.csv');

        // Create site and request
        $this->site = new Site('test', 1, [
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

        $this->request = (new ServerRequest())
            ->withAttribute('site', $this->site)
            ->withAttribute('language', $this->site->getDefaultLanguage());
    }

    /**
     * Test that renderFigure output does not create nested figures when processed again.
     *
     * This tests idempotency: passing renderFigure output through renderFigure again
     * should not create additional nesting. This simulates scenarios where content
     * might be processed multiple times.
     *
     * Reproduces issue #546: Double figure wrapping in frontend output
     */
    #[Test]
    public function renderFigureOutputIsIdempotent(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // First render - CKEditor input
        $ckeditorOutput = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>My Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($ckeditorOutput);

        /** @var string $firstRender */
        $firstRender = $adapter->renderFigure(null, [], $this->request);

        // Second render - simulate parseFunc processing the output again
        $cObj->setCurrentVal($firstRender);

        /** @var string $secondRender */
        $secondRender = $adapter->renderFigure(null, [], $this->request);

        // Count figures in first render
        $firstFigureCount = substr_count($firstRender, '<figure');
        self::assertSame(
            1,
            $firstFigureCount,
            'First render should have exactly 1 figure. Got ' . $firstFigureCount . '. Result: ' . $firstRender,
        );

        // Count figures in second render - should still be 1, not 2
        $secondFigureCount = substr_count($secondRender, '<figure');
        self::assertSame(
            1,
            $secondFigureCount,
            'Second render should still have exactly 1 figure (idempotent). '
            . 'Got ' . $secondFigureCount . '. Result: ' . $secondRender,
        );

        // Count figcaptions - should be exactly 1 in both
        $firstCaptionCount  = substr_count($firstRender, '<figcaption');
        $secondCaptionCount = substr_count($secondRender, '<figcaption');

        self::assertSame(1, $firstCaptionCount, 'First render should have 1 figcaption');
        self::assertSame(1, $secondCaptionCount, 'Second render should have 1 figcaption');
    }

    /**
     * Test that already-rendered figure HTML is detected and not re-wrapped.
     *
     * When renderFigure receives HTML that was already processed (no data-htmlarea-file-uid),
     * it should return the content unchanged to prevent nesting.
     */
    #[Test]
    public function alreadyProcessedFigureIsNotReWrapped(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // Simulate already-rendered figure (no data-htmlarea-file-uid)
        $renderedFigure = '<figure class="image">'
            . '<img src="/fileadmin/_processed_/test.jpg" width="250" height="250" alt="Test" decoding="async">'
            . '<figcaption>My Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($renderedFigure);
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should return original unchanged (no file UID = no processing)
        self::assertSame($renderedFigure, $result, 'Already processed figure should not be modified');

        // Verify no nesting occurred
        $figureCount = substr_count($result, '<figure');
        self::assertSame(1, $figureCount, 'Should have exactly 1 figure');
    }

    /**
     * Test figure rendering with external images does not create nesting.
     *
     * External images (no file UID) should pass through unchanged.
     */
    #[Test]
    public function externalImageFigurePassesThroughUnchanged(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        $externalFigure = '<figure class="image">'
            . '<img src="https://example.com/image.jpg" width="250" height="250" alt="External">'
            . '<figcaption>External Caption</figcaption>'
            . '</figure>';

        $cObj->setCurrentVal($externalFigure);
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should return original unchanged
        self::assertSame($externalFigure, $result);
    }

    // ========================================================================
    // Issue #566 Tests - Full parseFunc flow simulation
    // ========================================================================

    /**
     * Test that captioned images use WithCaption template when processed through full flow.
     *
     * Reproduces issue #566: Template selection not working correctly for images with captions.
     *
     * When parseFunc processes <figure><img data-caption="..."><figcaption>...</figcaption></figure>:
     * 1. tags.img handler (renderImageAttributes) runs FIRST on the inner <img>
     * 2. tags.figure handler (renderFigure) runs SECOND on the outer <figure>
     *
     * The bug: renderImageAttributes was processing the img and stripping data-htmlarea-file-uid,
     * causing renderFigure to fail file resolution and abort, leaving the Standalone template output.
     *
     * The fix: renderImageAttributes must skip processing when image has data-caption,
     * returning original content so renderFigure can handle the whole figure.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566
     */
    #[Test]
    public function captionedImageUsesWithCaptionTemplateInFullParseFuncFlow(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // CKEditor output: figure with captioned image
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'width="250" height="250" alt="Test" data-caption="My Caption" />';

        $figureHtml = '<figure class="image">'
            . $imgTag
            . '<figcaption>My Caption</figcaption>'
            . '</figure>';

        // Step 1: Simulate tags.img handler calling renderImageAttributes
        // In real parseFunc, this is called on the <img> element inside the figure
        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
            'data-caption'           => 'My Caption',
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $imgResult */
        $imgResult = $adapter->renderImageAttributes(null, [], $this->request);

        // CRITICAL: The img should be returned UNCHANGED (with data-htmlarea-file-uid preserved)
        // This allows renderFigure to resolve the file later
        self::assertStringContainsString(
            'data-htmlarea-file-uid',
            $imgResult,
            'renderImageAttributes must preserve data-htmlarea-file-uid for captioned images. Result: ' . $imgResult,
        );

        // Step 2: Simulate tags.figure handler calling renderFigure
        // The figure content should still have the original img with data-htmlarea-file-uid
        $cObj->setCurrentVal($figureHtml);

        /** @var string $figureResult */
        $figureResult = $adapter->renderFigure(null, [], $this->request);

        // Should have exactly one figure
        $figureCount = substr_count($figureResult, '<figure');
        self::assertSame(
            1,
            $figureCount,
            'Expected exactly 1 figure element. Result: ' . $figureResult,
        );

        // Should have exactly one figcaption
        $figcaptionCount = substr_count($figureResult, '<figcaption');
        self::assertSame(
            1,
            $figcaptionCount,
            'Expected exactly 1 figcaption element. Result: ' . $figureResult,
        );

        // Caption text should appear in the output
        self::assertStringContainsString(
            'My Caption',
            $figureResult,
            'Caption text should appear in output. Result: ' . $figureResult,
        );
    }

    /**
     * Test that standalone images (no caption) are still processed normally.
     *
     * Regression test: The fix for issue #566 should not affect standalone images.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566
     */
    #[Test]
    public function standaloneImageIsStillProcessedByRenderImageAttributes(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // Standalone image without caption
        $imgTag = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" '
            . 'width="250" height="250" alt="Test" />';

        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
            // No data-caption
        ];
        $cObj->setCurrentVal($imgTag);

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // Standalone images SHOULD be processed (src changed to processed path)
        // The data-htmlarea-file-uid should be removed after processing
        self::assertStringNotContainsString(
            'data-htmlarea-file-uid',
            $result,
            'Standalone images should be processed and have data-* attributes removed. Result: ' . $result,
        );

        // Should have a processed image src
        self::assertStringContainsString(
            '<img',
            $result,
            'Result should contain img tag. Result: ' . $result,
        );
    }
}
