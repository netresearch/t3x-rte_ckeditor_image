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
 * Functional tests for figure/caption rendering.
 *
 * Tests to reproduce and verify fix for issue #546:
 * - Double figure/figcaption wrapping in frontend output
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
 */
final class FigureCaptionRenderingTest extends FunctionalTestCase
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
     * Test that renderFigure does not create nested figure elements.
     *
     * Reproduces issue #546: Frontend shows double figure/figcaption wrapping
     *
     * Input (from CKEditor):
     * <figure class="image">
     *   <img data-htmlarea-file-uid="1" ...>
     *   <figcaption>My Caption</figcaption>
     * </figure>
     *
     * Expected output: Single figure with single figcaption
     * Bug output: Nested figures with duplicate figcaptions
     */
    #[Test]
    public function renderFigureDoesNotCreateNestedFigures(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $contentObjectRenderer */
        $contentObjectRenderer = $this->get(ContentObjectRenderer::class);

        // Simulate CKEditor output with figure and caption
        $inputHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>My Caption</figcaption>'
            . '</figure>';

        // Set the current value as TypoScript would
        $contentObjectRenderer->setCurrentVal($inputHtml);
        $adapter->setContentObjectRenderer($contentObjectRenderer);

        // Call renderFigure as TypoScript preUserFunc would
        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Assert: No nested figures
        $figureCount = substr_count($result, '<figure');
        self::assertSame(
            1,
            $figureCount,
            'Expected exactly 1 figure element, got ' . $figureCount . '. Result: ' . $result,
        );

        // Assert: No nested figcaptions
        $figcaptionCount = substr_count($result, '<figcaption');
        self::assertSame(
            1,
            $figcaptionCount,
            'Expected exactly 1 figcaption element, got ' . $figcaptionCount . '. Result: ' . $result,
        );

        // Assert: Caption text appears only once
        $captionTextCount = substr_count($result, 'My Caption');
        self::assertSame(
            1,
            $captionTextCount,
            'Expected caption text to appear exactly once, got ' . $captionTextCount . '. Result: ' . $result,
        );
    }

    /**
     * Test that the output is idempotent - rendering the output again should not change structure.
     *
     * This is test case 27 from @prdt3e's specification.
     */
    #[Test]
    public function renderingIsIdempotent(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $contentObjectRenderer */
        $contentObjectRenderer = $this->get(ContentObjectRenderer::class);

        // First render
        $inputHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>';

        $contentObjectRenderer->setCurrentVal($inputHtml);
        $adapter->setContentObjectRenderer($contentObjectRenderer);

        /** @var string $firstRender */
        $firstRender = $adapter->renderFigure(null, [], $this->request);

        // Second render - using the output from the first render
        $contentObjectRenderer->setCurrentVal($firstRender);

        /** @var string $secondRender */
        $secondRender = $adapter->renderFigure(null, [], $this->request);

        // Structure should remain the same (still 1 figure, 1 figcaption)
        $figureCount = substr_count($secondRender, '<figure');
        self::assertSame(
            1,
            $figureCount,
            'Idempotency check: Expected 1 figure after second render, got ' . $figureCount . '. Result: ' . $secondRender,
        );
    }

    /**
     * Test that figure without file UID returns original content unchanged.
     */
    #[Test]
    public function renderFigureReturnsOriginalForExternalImages(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $contentObjectRenderer */
        $contentObjectRenderer = $this->get(ContentObjectRenderer::class);

        // External image without file UID
        $inputHtml = '<figure class="image">'
            . '<img src="https://example.com/external.jpg" width="250" height="250" alt="External">'
            . '<figcaption>External Caption</figcaption>'
            . '</figure>';

        $contentObjectRenderer->setCurrentVal($inputHtml);
        $adapter->setContentObjectRenderer($contentObjectRenderer);

        $result = $adapter->renderFigure(null, [], $this->request);

        // Should return original unchanged
        self::assertSame($inputHtml, $result);
    }

    /**
     * Test that figure without figcaption but with data-caption works correctly.
     */
    #[Test]
    public function renderFigureHandlesDataCaptionAttribute(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $contentObjectRenderer */
        $contentObjectRenderer = $this->get(ContentObjectRenderer::class);

        // Figure with data-caption but no figcaption element
        $inputHtml = '<figure class="image">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test" data-caption="Data Caption">'
            . '</figure>';

        $contentObjectRenderer->setCurrentVal($inputHtml);
        $adapter->setContentObjectRenderer($contentObjectRenderer);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        // Should have exactly one figure
        $figureCount = substr_count($result, '<figure');
        self::assertSame(1, $figureCount, 'Expected 1 figure. Result: ' . $result);
    }
}
