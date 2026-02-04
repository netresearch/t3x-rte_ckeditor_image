<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Controller;

use Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter;
use Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto;
use Netresearch\RteCKEditorImage\Domain\Model\LinkDto;
use Netresearch\RteCKEditorImage\Service\ImageAttributeParser;
use Netresearch\RteCKEditorImage\Service\ImageRenderingService;
use Netresearch\RteCKEditorImage\Service\ImageResolverService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Unit tests for ImageRenderingAdapter.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ImageRenderingAdapter::class)]
final class ImageRenderingAdapterTest extends TestCase
{
    private ImageRenderingAdapter $adapter;

    /** @var MockObject&ImageResolverService */
    private MockObject $resolverService;

    /** @var MockObject&ImageRenderingService */
    private MockObject $renderingService;

    /** @var MockObject&ImageAttributeParser */
    private MockObject $attributeParser;

    /** @var MockObject&ContentObjectRenderer */
    private MockObject $contentObjectRenderer;

    /** @var MockObject&ServerRequestInterface */
    private MockObject $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolverService       = $this->createMock(ImageResolverService::class);
        $this->renderingService      = $this->createMock(ImageRenderingService::class);
        $this->attributeParser       = $this->createMock(ImageAttributeParser::class);
        $this->contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $this->request               = $this->createMock(ServerRequestInterface::class);

        $this->adapter = new ImageRenderingAdapter(
            $this->resolverService,
            $this->renderingService,
            $this->attributeParser,
        );
    }

    #[Test]
    public function setContentObjectRendererSetsRenderer(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);

        // Verify by calling renderImageAttributes - it should use the cObj
        $this->contentObjectRenderer->parameters = [];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('');

        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        self::assertSame('', $result);
    }

    #[Test]
    public function renderImageAttributesReturnsEmptyStringWhenNoAttributes(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = [];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('original');

        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        self::assertSame('original', $result);
    }

    #[Test]
    public function renderImageAttributesReturnsOriginalWhenResolutionFails(): void
    {
        $attributes = ['src' => '/image.jpg', 'data-htmlarea-file-uid' => '999'];

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = $attributes;
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('<img src="/image.jpg" />');

        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(null); // Resolution fails

        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        self::assertSame('<img src="/image.jpg" />', $result);
    }

    #[Test]
    public function renderImageAttributesRendersViaServiceWhenResolutionSucceeds(): void
    {
        $attributes = [
            'src'                    => '/image.jpg',
            'width'                  => '800',
            'height'                 => '600',
            'data-htmlarea-file-uid' => '1',
        ];

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = $attributes;

        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->with($attributes, [], $this->request)
            ->willReturn($dto);

        $this->renderingService
            ->expects(self::once())
            ->method('render')
            ->with($dto, $this->request, [])
            ->willReturn('<img src="/processed.jpg" width="800" height="600" alt="Test" />');

        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        self::assertSame('<img src="/processed.jpg" width="800" height="600" alt="Test" />', $result);
    }

    #[Test]
    public function renderImageAttributesPassesConfigToRenderingService(): void
    {
        $attributes = [
            'src'                    => '/image.jpg',
            'data-htmlarea-file-uid' => '1',
        ];

        $config = [
            'templateRootPaths.' => ['10' => 'EXT:my_ext/Resources/Private/Templates/'],
        ];

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = $attributes;

        $this->resolverService
            ->method('resolve')
            ->willReturn($dto);

        $this->renderingService
            ->expects(self::once())
            ->method('render')
            ->with($dto, $this->request, $config)
            ->willReturn('<img src="/processed.jpg" />');

        $result = $this->adapter->renderImageAttributes(null, $config, $this->request);

        self::assertSame('<img src="/processed.jpg" />', $result);
    }

    #[Test]
    public function renderImagesReturnsEmptyStringWhenNoLinkContent(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn(null);

        $result = $this->adapter->renderImages(null, [], $this->request);

        self::assertSame('', $result);
    }

    #[Test]
    public function renderImagesReturnsOriginalWhenNoImagesFoundAndNoLink(): void
    {
        $linkContent = '<span>Text only, no images</span>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = []; // No link attributes
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkContent);

        $this->attributeParser
            ->expects(self::once())
            ->method('parseLinkWithImages')
            ->with($linkContent)
            ->willReturn(['link' => [], 'images' => []]);

        $result = $this->adapter->renderImages(null, [], $this->request);

        // No href in parameters means content is returned as-is (not wrapped)
        self::assertSame($linkContent, $result);
    }

    #[Test]
    public function renderImagesWrapsContentInLinkWhenNoImagesButLinkExists(): void
    {
        $linkContent = '<span>Text only, no images</span>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        // Link attributes are in cObj->parameters (populated by parseFunc for tags.a)
        $this->contentObjectRenderer->parameters = [
            'href'   => '/page',
            'target' => '_blank',
        ];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkContent);

        $this->attributeParser
            ->expects(self::once())
            ->method('parseLinkWithImages')
            ->with($linkContent)
            ->willReturn(['link' => [], 'images' => []]);

        $result = $this->adapter->renderImages(null, [], $this->request);

        // Content should be wrapped in <a> tag with attributes from parameters
        self::assertStringContainsString('<a href="/page"', $result);
        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString($linkContent, $result);
        self::assertStringContainsString('</a>', $result);
    }

    #[Test]
    public function renderImagesSkipsImagesWithoutFileUidButWrapsInLink(): void
    {
        $linkContent = '<img src="/external.jpg" />';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        // Link attributes from cObj->parameters
        $this->contentObjectRenderer->parameters = ['href' => '/page'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkContent);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => '/page'],
                'images' => [
                    ['src' => '/external.jpg'], // No file UID
                ],
            ]);

        $this->resolverService
            ->expects(self::never())
            ->method('resolve');

        $result = $this->adapter->renderImages(null, [], $this->request);

        // Content should still be wrapped in link even though image wasn't processed
        self::assertStringContainsString('<a href="/page"', $result);
        self::assertStringContainsString($linkContent, $result);
        self::assertStringContainsString('</a>', $result);
    }

    #[Test]
    public function renderImagesRendersInlineImagesWithFileUidAndPassesConfig(): void
    {
        // Inline images have class="image image-inline" (CKEditor output)
        $originalImg = '<img src="/image.jpg" data-htmlarea-file-uid="1" class="image image-inline" />';
        $linkContent = $originalImg;

        $config = [
            'templateRootPaths.' => ['10' => 'EXT:my_ext/Resources/Private/Templates/'],
        ];

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: ['class' => 'image image-inline'],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        // Link attributes from cObj->parameters
        $this->contentObjectRenderer->parameters = ['href' => '/page'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkContent);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => '/page'],
                'images' => [
                    [
                        'attributes' => [
                            'src'                    => '/image.jpg',
                            'data-htmlarea-file-uid' => '1',
                            'class'                  => 'image image-inline',
                        ],
                        'originalHtml' => $originalImg,
                    ],
                ],
            ]);

        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn($dto);

        $this->renderingService
            ->expects(self::once())
            ->method('render')
            ->with($dto, $this->request, $config)
            ->willReturn('<img src="/processed.jpg" width="800" height="600" alt="Test" class="image image-inline" />');

        $result = $this->adapter->renderImages(null, $config, $this->request);

        // Result should be wrapped in <a> tag (reconstructed from cObj->parameters)
        self::assertStringContainsString('<a href="/page"', $result);
        self::assertStringContainsString('<img src="/processed.jpg"', $result);
        self::assertStringContainsString('</a>', $result);
    }

    /**
     * Test that renderImages strips caption and zoom attributes before resolving.
     *
     * Images inside links should not create figure wrappers or popup links,
     * so data-caption, data-htmlarea-zoom, and data-htmlarea-clickenlarge
     * must be removed before passing to the resolver.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
     */
    #[Test]
    public function renderImagesStripsAttributesThatWouldCreateWrappers(): void
    {
        // Inline image with attributes that should be stripped
        $originalImg = '<img src="/image.jpg" data-htmlarea-file-uid="1" data-caption="Caption" '
            . 'data-htmlarea-zoom="1" data-htmlarea-clickenlarge="1" class="image image-inline" />';
        $linkContent = $originalImg;

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: ['class' => 'image image-inline'],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = []; // No link attributes
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkContent);

        // Parser returns attributes including ones that should be stripped
        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => [],
                'images' => [
                    [
                        'attributes' => [
                            'src'                        => '/image.jpg',
                            'data-htmlarea-file-uid'     => '1',
                            'data-caption'               => 'Caption',
                            'data-htmlarea-zoom'         => '1',
                            'data-htmlarea-clickenlarge' => '1',
                            'class'                      => 'image image-inline',
                        ],
                        'originalHtml' => $originalImg,
                    ],
                ],
            ]);

        // Verify resolve() receives attributes WITHOUT caption/zoom/clickenlarge
        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->with(
                self::callback(static function (array $attributes): bool {
                    // These attributes MUST be stripped
                    if (array_key_exists('data-caption', $attributes)) {
                        return false;
                    }

                    if (array_key_exists('data-htmlarea-zoom', $attributes)) {
                        return false;
                    }

                    if (array_key_exists('data-htmlarea-clickenlarge', $attributes)) {
                        return false;
                    }

                    // File UID must still be present
                    return ($attributes['data-htmlarea-file-uid'] ?? '') === '1';
                }),
                self::anything(),
                self::anything(),
            )
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<img src="/processed.jpg" />');

        $result = $this->adapter->renderImages(null, [], $this->request);

        // Verify the rendered image replaces the original
        self::assertSame('<img src="/processed.jpg" />', $result);
    }

    #[Test]
    public function prefixIdAndExtKeyAreSet(): void
    {
        self::assertSame('ImageRenderingAdapter', $this->adapter->prefixId);
        self::assertSame('rte_ckeditor_image', $this->adapter->extKey);
        self::assertSame('Classes/Controller/ImageRenderingAdapter.php', $this->adapter->scriptRelPath);
    }


    // ========================================================================
    // Issue #566 Tests - renderImageAttributes must skip captioned images
    // ========================================================================

    /**
     * Test that renderImageAttributes skips processing when image has data-caption.
     *
     * CRITICAL for issue #566: Images with data-caption are part of a <figure>
     * structure and MUST be processed by renderFigure() instead. If we process
     * here, we strip the data-htmlarea-file-uid attribute, preventing renderFigure()
     * from resolving the file later.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566
     */
    #[Test]
    public function renderImageAttributesSkipsProcessingWhenImageHasCaption(): void
    {
        $originalImg = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" data-caption="My Caption" />';

        $attributes = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'data-caption'           => 'My Caption',
        ];

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = $attributes;
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($originalImg);

        // Resolver should NOT be called - processing should be skipped entirely
        $this->resolverService
            ->expects(self::never())
            ->method('resolve');

        // Rendering service should NOT be called
        $this->renderingService
            ->expects(self::never())
            ->method('render');

        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        // Should return original content unchanged to preserve data-htmlarea-file-uid
        self::assertSame($originalImg, $result);
    }

    /**
     * Test that renderImageAttributes processes images WITHOUT data-caption normally.
     *
     * Standalone images (no caption) should be processed by renderImageAttributes
     * as before - only captioned images are skipped.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566
     */
    #[Test]
    public function renderImageAttributesProcessesImagesWithoutCaption(): void
    {
        $attributes = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            // No data-caption
        ];

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = $attributes;

        // Resolver SHOULD be called for images without caption
        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn($dto);

        $this->renderingService
            ->expects(self::once())
            ->method('render')
            ->willReturn('<img src="/processed.jpg" />');

        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        self::assertSame('<img src="/processed.jpg" />', $result);
    }

    /**
     * Test that renderImageAttributes processes images with empty caption.
     *
     * Edge case: data-caption="" (empty string) should NOT skip processing,
     * only non-empty captions indicate a figure structure.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566
     */
    #[Test]
    public function renderImageAttributesProcessesImagesWithEmptyCaption(): void
    {
        $attributes = [
            'src'                    => '/fileadmin/test.jpg',
            'data-htmlarea-file-uid' => '1',
            'data-caption'           => '', // Empty caption
        ];

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = $attributes;

        // Resolver SHOULD be called for images with empty caption
        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn($dto);

        $this->renderingService
            ->expects(self::once())
            ->method('render')
            ->willReturn('<img src="/processed.jpg" />');

        $this->adapter->renderImageAttributes(null, [], $this->request);
    }
    // ========================================================================
    // renderFigure() Tests
    // ========================================================================

    #[Test]
    public function renderFigureReturnsEmptyStringWhenNoContent(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn(null);

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertSame('', $result);
    }

    #[Test]
    public function renderFigureReturnsOriginalWhenNoFigureWrapper(): void
    {
        $html = '<img src="test.jpg" />';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $this->attributeParser
            ->expects(self::once())
            ->method('hasFigureWrapper')
            ->with($html)
            ->willReturn(false);

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertSame($html, $result);
    }

    #[Test]
    public function renderFigureReturnsOriginalWhenNoFileUid(): void
    {
        $html = '<figure><img src="external.jpg" /><figcaption>Caption</figcaption></figure>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $this->attributeParser
            ->method('hasFigureWrapper')
            ->willReturn(true);

        $this->attributeParser
            ->method('parseFigureWithCaption')
            ->willReturn([
                'attributes' => ['src' => 'external.jpg'], // No data-htmlarea-file-uid
                'caption'    => 'Caption',
                'link'       => [],
            ]);

        $this->resolverService
            ->expects(self::never())
            ->method('resolve');

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertSame($html, $result);
    }

    #[Test]
    public function renderFigureRendersViaServiceWhenResolutionSucceeds(): void
    {
        $html = '<figure><img src="test.jpg" data-htmlarea-file-uid="1" /><figcaption>My Caption</figcaption></figure>';

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: 'My Caption',
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $this->attributeParser
            ->method('hasFigureWrapper')
            ->willReturn(true);

        $this->attributeParser
            ->method('parseFigureWithCaption')
            ->willReturn([
                'attributes' => [
                    'src'                    => 'test.jpg',
                    'data-htmlarea-file-uid' => '1',
                ],
                'caption' => 'My Caption',
                'link'    => [],
            ]);

        // Caption from figcaption should be added to attributes
        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->with(
                self::callback(static function (array $attributes): bool {
                    return $attributes['data-caption'] === 'My Caption'
                        && $attributes['data-htmlarea-file-uid'] === '1';
                }),
                [],
                $this->request,
                null, // No link attributes (empty link array from parser)
            )
            ->willReturn($dto);

        $this->renderingService
            ->expects(self::once())
            ->method('render')
            ->with($dto, $this->request, [])
            ->willReturn('<figure><img /><figcaption>My Caption</figcaption></figure>');

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertSame('<figure><img /><figcaption>My Caption</figcaption></figure>', $result);
    }

    #[Test]
    public function renderFigureFigcaptionOverridesDataCaption(): void
    {
        $html = '<figure><img src="test.jpg" data-htmlarea-file-uid="1" data-caption="Old" /><figcaption>New</figcaption></figure>';

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: 'New',
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $this->attributeParser
            ->method('hasFigureWrapper')
            ->willReturn(true);

        $this->attributeParser
            ->method('parseFigureWithCaption')
            ->willReturn([
                'attributes' => [
                    'src'                    => 'test.jpg',
                    'data-htmlarea-file-uid' => '1',
                    'data-caption'           => 'Old', // Existing data-caption
                ],
                'caption' => 'New', // Figcaption should override
                'link'    => [],
            ]);

        // Figcaption caption should override data-caption
        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->with(
                self::callback(static function (array $attributes): bool {
                    return $attributes['data-caption'] === 'New'; // Must be overridden
                }),
                [],
                $this->request,
                null, // No link attributes (empty link array from parser)
            )
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<figure><img /><figcaption>New</figcaption></figure>');

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertStringContainsString('New', $result);
    }

    #[Test]
    public function renderFigureReturnsOriginalWhenResolutionFails(): void
    {
        $html = '<figure><img src="test.jpg" data-htmlarea-file-uid="999" /></figure>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $this->attributeParser
            ->method('hasFigureWrapper')
            ->willReturn(true);

        $this->attributeParser
            ->method('parseFigureWithCaption')
            ->willReturn([
                'attributes' => [
                    'src'                    => 'test.jpg',
                    'data-htmlarea-file-uid' => '999',
                ],
                'caption' => '',
                'link'    => [],
            ]);

        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(null); // Resolution fails

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertSame($html, $result);
    }

    // ========================================================================
    // Issue #555 Tests - renderFigure must pass link attributes to resolver
    // ========================================================================

    /**
     * Test that renderFigure passes link attributes to resolver when image is inside <a>.
     *
     * Bug: When CKEditor outputs <figure><a href="..."><img/></a><figcaption>...</figcaption></figure>,
     * the link attributes must be passed to the resolver so LinkWithCaption template is selected.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function renderFigurePassesLinkAttributesToResolverForLinkedImage(): void
    {
        $html = '<figure><a href="https://example.com" target="_blank"><img src="test.jpg" data-htmlarea-file-uid="1" /></a><figcaption>Caption</figcaption></figure>';

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: 'Caption',
            link: new LinkDto(
                url: 'https://example.com',
                target: '_blank',
                class: null,
                params: null,
                isPopup: false,
                jsConfig: null,
            ),
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $this->attributeParser
            ->method('hasFigureWrapper')
            ->willReturn(true);

        // Parser returns link attributes (after fix)
        $this->attributeParser
            ->method('parseFigureWithCaption')
            ->willReturn([
                'attributes' => [
                    'src'                    => 'test.jpg',
                    'data-htmlarea-file-uid' => '1',
                ],
                'caption' => 'Caption',
                'link'    => [
                    'href'   => 'https://example.com',
                    'target' => '_blank',
                ],
            ]);

        // Resolver MUST receive link attributes as 4th parameter
        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->with(
                self::callback(static function (array $attributes): bool {
                    return $attributes['data-htmlarea-file-uid'] === '1'
                        && $attributes['data-caption'] === 'Caption';
                }),
                [],
                $this->request,
                self::callback(static function (?array $linkAttributes): bool {
                    // Link attributes MUST be passed (this is the bug - currently null)
                    return is_array($linkAttributes)
                        && $linkAttributes['href'] === 'https://example.com'
                        && $linkAttributes['target'] === '_blank';
                }),
            )
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<figure><a href="https://example.com"><img /></a><figcaption>Caption</figcaption></figure>');

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertStringContainsString('figure', $result);
    }

    /**
     * Test that renderFigure passes null for link attributes when no link wrapper.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function renderFigurePassesNullLinkAttributesWhenNoLinkWrapper(): void
    {
        $html = '<figure><img src="test.jpg" data-htmlarea-file-uid="1" /><figcaption>Caption</figcaption></figure>';

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: 'Caption',
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $this->attributeParser
            ->method('hasFigureWrapper')
            ->willReturn(true);

        // Parser returns empty link array (no <a> wrapper)
        $this->attributeParser
            ->method('parseFigureWithCaption')
            ->willReturn([
                'attributes' => [
                    'src'                    => 'test.jpg',
                    'data-htmlarea-file-uid' => '1',
                ],
                'caption' => 'Caption',
                'link'    => [], // Empty = no link
            ]);

        // Resolver should receive null for link attributes when no link
        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->with(
                self::anything(),
                [],
                $this->request,
                null, // No link attributes
            )
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<figure><img /><figcaption>Caption</figcaption></figure>');

        $this->adapter->renderFigure(null, [], $this->request);
    }

    /**
     * Test that renderFigure passes link attributes for linked image without caption.
     *
     * Figure + Link (no caption) should still pass link attributes to resolver,
     * resulting in Link template selection.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function renderFigurePassesLinkAttributesForLinkedImageWithoutCaption(): void
    {
        $html = '<figure><a href="https://example.com"><img src="test.jpg" data-htmlarea-file-uid="1" /></a></figure>';

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: null, // No caption
            link: new LinkDto(
                url: 'https://example.com',
                target: null,
                class: null,
                params: null,
                isPopup: false,
                jsConfig: null,
            ),
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $this->attributeParser
            ->method('hasFigureWrapper')
            ->willReturn(true);

        // Parser returns link attributes but no caption
        $this->attributeParser
            ->method('parseFigureWithCaption')
            ->willReturn([
                'attributes' => [
                    'src'                    => 'test.jpg',
                    'data-htmlarea-file-uid' => '1',
                ],
                'caption' => '', // No caption
                'link'    => [
                    'href' => 'https://example.com',
                ],
            ]);

        // Resolver MUST receive link attributes even without caption
        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->with(
                self::callback(static function (array $attributes): bool {
                    // No data-caption should be added when caption is empty
                    return !array_key_exists('data-caption', $attributes)
                        && $attributes['data-htmlarea-file-uid'] === '1';
                }),
                [],
                $this->request,
                self::callback(static function (?array $linkAttributes): bool {
                    return is_array($linkAttributes)
                        && $linkAttributes['href'] === 'https://example.com';
                }),
            )
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<a href="https://example.com"><img /></a>');

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertStringContainsString('href', $result);
    }

    /**
     * Test that renderFigure preserves popup attributes for resolver to handle.
     *
     * When figure contains image with data-htmlarea-zoom but no explicit link,
     * the popup attributes should be preserved in the image attributes passed
     * to the resolver, allowing it to auto-generate a popup link.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[Test]
    public function renderFigurePreservesPopupAttributesForResolverAutoLink(): void
    {
        $html = '<figure><img src="test.jpg" data-htmlarea-file-uid="1" data-htmlarea-zoom="1" /><figcaption>Popup Caption</figcaption></figure>';

        // The resolver will create a popup link because of data-htmlarea-zoom
        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: null,
            htmlAttributes: [],
            caption: 'Popup Caption',
            link: new LinkDto(
                url: '/fullsize.jpg', // Auto-generated by resolver
                target: '_blank',
                class: 'popup-link',
                params: null,
                isPopup: true,
                jsConfig: null,
            ),
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $this->attributeParser
            ->method('hasFigureWrapper')
            ->willReturn(true);

        // Parser returns popup attributes on image, no explicit link
        $this->attributeParser
            ->method('parseFigureWithCaption')
            ->willReturn([
                'attributes' => [
                    'src'                    => 'test.jpg',
                    'data-htmlarea-file-uid' => '1',
                    'data-htmlarea-zoom'     => '1', // Popup attribute
                ],
                'caption' => 'Popup Caption',
                'link'    => [], // No explicit link
            ]);

        // Resolver receives null for link attributes, but image attributes include zoom
        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->with(
                self::callback(static function (array $attributes): bool {
                    // Popup attribute MUST be preserved
                    return $attributes['data-htmlarea-zoom'] === '1'
                        && $attributes['data-caption'] === 'Popup Caption';
                }),
                [],
                $this->request,
                null, // No explicit link attributes
            )
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<figure><a href="/fullsize.jpg" data-popup="true"><img /></a><figcaption>Popup Caption</figcaption></figure>');

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertStringContainsString('Popup Caption', $result);
    }
}
