<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

    private Site $site;

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

    // ========================================================================
    // Issue #718 Tests - tags.a must not clear default parseFunc config
    // ========================================================================

    /**
     * Test that prepareInlineLinkContent strips nested <a> wrappers in integration context.
     *
     * Verifies the preUserFunc handler works correctly with a real ContentObjectRenderer,
     * stripping nested link wrappers from historical double-wrapped data.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/718
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/667
     */
    #[Test]
    public function prepareInlineLinkContentStripsNestedLinksInIntegrationContext(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // Simulate historical double-wrapped data: parseFunc passes inner <a><img>
        $nestedContent = '<a class="image image-inline" href="t3://page?uid=1" target="_blank">'
            . '<img class="image-inline" src="/fileadmin/test.jpg" data-htmlarea-file-uid="1">';

        /** @var string $result */
        $result = $adapter->prepareInlineLinkContent($nestedContent, [], $this->request);

        // Nested <a> should be stripped, only <img> remains
        self::assertStringContainsString('<img', $result, 'Image tag should be preserved');
        self::assertStringNotContainsString('<a ', $result, 'Nested <a> wrapper should be stripped');
    }

    /**
     * Test that prepareInlineLinkContent passes through regular content unchanged.
     *
     * When content does not contain a nested <a><img></a> pattern, it should
     * pass through unmodified for the default typolink handler to process.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/718
     */
    #[Test]
    public function prepareInlineLinkContentPreservesRegularLinkContent(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // Regular link content (text, processed image) — should pass through unchanged
        $regularContent = 'Click here to visit our '
            . '<img src="/fileadmin/_processed_/test.jpg" class="image image-inline" width="24" height="24" alt="icon">'
            . ' page';

        /** @var string $result */
        $result = $adapter->prepareInlineLinkContent($regularContent, [], $this->request);

        self::assertSame($regularContent, $result, 'Regular content should pass through unchanged');
    }

    /**
     * Test full parseFunc pipeline with merged tags.a config.
     *
     * Simulates the TypoScript configuration that results from loading both
     * fluid_styled_content (provides tags.a = TEXT with typolink) and our
     * extension (adds tags.a.preUserFunc). Tests that the combined config
     * produces correct output: links with target, rel="noreferrer", etc.
     *
     * This test exists because the E2E tests (which cover this in a real TYPO3
     * instance with DDEV) do not run in CI.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/718
     */
    #[Test]
    public function parseFuncPipelinePreservesLinkAttributesWithMergedConfig(): void
    {
        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);

        // Build the merged parseFunc config as it would exist when both
        // fluid_styled_content and our extension are loaded:
        // - fluid_styled_content sets tags.a = TEXT with typolink config
        // - our extension adds tags.a.preUserFunc
        $parseFuncConfig = [
            'allowTags' => 'a,img,figure,figcaption',
            'tags.'     => [
                // fluid_styled_content's default tags.a config
                'a'  => 'TEXT',
                'a.' => [
                    'current' => '1',
                    // Our extension adds this preUserFunc
                    'preUserFunc' => ImageRenderingAdapter::class . '->prepareInlineLinkContent',
                    'typolink.'   => [
                        'parameter.'  => ['data' => 'parameters:href'],
                        'title.'      => ['data' => 'parameters:title'],
                        'ATagParams.' => ['data' => 'parameters:allParams'],
                        'target.'     => [
                            'ifEmpty.' => ['data' => 'parameters:target'],
                        ],
                        'extTarget.' => [
                            'ifEmpty'   => '_blank',
                            'override.' => ['data' => 'parameters:target'],
                        ],
                    ],
                ],
            ],
            'htmlSanitize' => '0',
        ];

        // External link — typolink should add target="_blank" (from extTarget)
        // and rel="noreferrer" (automatic for target="_blank")
        $html   = '<a href="https://github.com/netresearch">Visit GitHub</a>';
        $result = $cObj->parseFunc($html, $parseFuncConfig);

        self::assertStringContainsString(
            'href="https://github.com/netresearch"',
            $result,
            'External link href should be preserved. Result: ' . $result,
        );

        self::assertStringContainsString(
            'target="_blank"',
            $result,
            'External link should have target="_blank" from extTarget config. Result: ' . $result,
        );

        self::assertStringContainsString(
            'noreferrer',
            $result,
            'External link with target="_blank" should have rel containing "noreferrer". Result: ' . $result,
        );

        self::assertStringContainsString(
            'Visit GitHub',
            $result,
            'Link text should be preserved. Result: ' . $result,
        );
    }

    /**
     * Test parseFunc pipeline strips nested links while preserving typolink output.
     *
     * Verifies that our preUserFunc (prepareInlineLinkContent) correctly strips
     * nested link wrappers from historical double-wrapped data, while the default
     * typolink handler still produces correct link output.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/718
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/667
     */
    #[Test]
    public function parseFuncPipelineStripsNestedLinksWithMergedConfig(): void
    {
        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);

        $parseFuncConfig = [
            'allowTags' => 'a,img,figure,figcaption',
            'tags.'     => [
                'a'  => 'TEXT',
                'a.' => [
                    'current'     => '1',
                    'preUserFunc' => ImageRenderingAdapter::class . '->prepareInlineLinkContent',
                    'typolink.'   => [
                        'parameter.'  => ['data' => 'parameters:href'],
                        'title.'      => ['data' => 'parameters:title'],
                        'ATagParams.' => ['data' => 'parameters:allParams'],
                        'target.'     => [
                            'ifEmpty.' => ['data' => 'parameters:target'],
                        ],
                        'extTarget.' => [
                            'ifEmpty'   => '_blank',
                            'override.' => ['data' => 'parameters:target'],
                        ],
                    ],
                ],
            ],
            'htmlSanitize' => '0',
        ];

        // Historical double-wrapped data: <a><a><img></a></a>
        // Our preUserFunc should strip the inner <a>, typolink wraps the result
        $html = '<a href="https://github.com/netresearch">'
            . '<a class="image" href="https://github.com/netresearch">'
            . '<img src="/fileadmin/test.jpg" class="image-inline" width="100" height="100">'
            . '</a></a>';

        $result = $cObj->parseFunc($html, $parseFuncConfig);

        // Should NOT contain nested <a> tags — our preUserFunc strips them
        $anchorCount = substr_count(strtolower($result), '<a ');
        self::assertSame(
            1,
            $anchorCount,
            'Should have exactly 1 <a> tag (no nesting). Got ' . $anchorCount . '. Result: ' . $result,
        );

        // Image should be preserved
        self::assertStringContainsString(
            '<img',
            $result,
            'Image tag should be preserved. Result: ' . $result,
        );
    }

    /**
     * Test that the TypoScript setup only adds preUserFunc without clearing tags.a.
     *
     * Regression test: verifies our TypoScript does NOT contain 'tags.a >'
     * which would clear the default configuration from fluid_styled_content.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/718
     */
    #[Test]
    public function typoscriptDoesNotClearDefaultTagsAConfig(): void
    {
        $setupTyposcript = file_get_contents(
            __DIR__ . '/../../../Configuration/TypoScript/ImageRendering/setup.typoscript',
        );

        self::assertIsString($setupTyposcript, 'setup.typoscript should be readable');

        // Must NOT contain 'tags.a >' which clears the default config
        self::assertStringNotContainsString(
            'tags.a >',
            $setupTyposcript,
            'TypoScript must NOT clear tags.a — this removes default link processing from fluid_styled_content (#718)',
        );

        // Must contain prepareInlineLinkContent as the preUserFunc
        self::assertStringContainsString(
            'prepareInlineLinkContent',
            $setupTyposcript,
            'TypoScript should reference prepareInlineLinkContent as tags.a.preUserFunc',
        );
    }
}
