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
 * @author  Netresearch DTT GmbH <info@netresearch.de>
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
}
