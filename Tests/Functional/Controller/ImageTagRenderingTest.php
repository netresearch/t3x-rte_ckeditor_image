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
 * Tests to verify that the tags.img handler (renderImageAttributes) does NOT
 * create figure wrappers for captioned images, preventing nested figure
 * structures when both tags.img and tags.figure handlers process content.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
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
     * Test that renderImageAttributes does NOT create figure wrappers for captioned images.
     *
     * This is the root cause of bug #546: When an img tag has data-caption attribute,
     * the tags.img handler (renderImageAttributes) was creating a figure wrapper.
     * When this image is inside a figure (as CKEditor outputs captioned images),
     * both handlers create figure wrappers, resulting in nested figures.
     *
     * FIX: renderImageAttributes should NEVER create figure wrappers. Only
     * renderFigure (tags.figure handler) should create figure wrappers.
     *
     * Current CKEditor output for captioned images:
     * <figure class="image">
     *   <img src="..." data-htmlarea-file-uid="1" data-caption="Caption">
     *   <figcaption>Caption</figcaption>
     * </figure>
     */
    #[Test]
    public function renderImageAttributesDoesNotCreateFigureWrapperForCaptionedImage(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // Simulate tags.img handler receiving an img with data-caption
        // This is what parseFunc passes to the handler for standalone img tags
        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
            'data-caption'           => 'My Caption',
        ];

        /** @var string $result */
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // CRITICAL ASSERTION: The output should NOT contain figure wrapper
        // If it does, we'll get nested figures when this img is inside a figure
        $figureCount = substr_count($result, '<figure');

        self::assertSame(
            0,
            $figureCount,
            'renderImageAttributes should NOT create figure wrappers for captioned images. '
            . 'Found ' . $figureCount . ' figure element(s). Result: ' . $result,
        );

        // The output should be just an img tag (possibly with wrapper span for styling)
        self::assertStringContainsString('<img', $result, 'Output should contain img element');

        // Should NOT contain figcaption (that's renderFigure's job)
        self::assertStringNotContainsString(
            '<figcaption',
            $result,
            'renderImageAttributes should NOT create figcaption. That is renderFigure\'s responsibility.',
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

    /**
     * Test simulation of parseFunc processing order causing nested figures.
     *
     * This test demonstrates the bug: when parseFunc processes content with
     * a captioned image inside a figure, both handlers create figure wrappers.
     */
    #[Test]
    public function combinedHandlersDoNotCreateNestedFigures(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);
        $adapter->setContentObjectRenderer($cObj);

        // Step 1: Simulate what tags.img handler produces
        $cObj->parameters = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'width'                  => '250',
            'height'                 => '250',
            'alt'                    => 'Test',
            'data-caption'           => 'Caption Text',
        ];

        /** @var string $imgResult */
        $imgResult = $adapter->renderImageAttributes(null, [], $this->request);

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
