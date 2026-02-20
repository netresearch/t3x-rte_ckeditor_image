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

    // ========================================================================
    // renderLink() Tests - externalBlocks.a handler
    // ========================================================================

    /**
     * Test that renderLink returns empty string when no content.
     */
    #[Test]
    public function renderLinkReturnsEmptyStringWhenNoContent(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn(null);

        $result = $this->adapter->renderLink(null, [], $this->request);

        self::assertSame('', $result);
    }

    /**
     * Test that renderLink returns original content when not a link.
     */
    #[Test]
    public function renderLinkReturnsOriginalWhenNotALink(): void
    {
        $html = '<p>Just some text</p>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($html);

        $result = $this->adapter->renderLink($html, [], $this->request);

        self::assertSame($html, $result);
    }

    /**
     * Test that renderLink returns original when no images found.
     */
    #[Test]
    public function renderLinkReturnsOriginalWhenNoImagesFound(): void
    {
        $linkHtml = '<a href="/page">Just text, no images</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->expects(self::once())
            ->method('parseLinkWithImages')
            ->with($linkHtml)
            ->willReturn([
                'link'   => ['href' => '/page'],
                'images' => [],
            ]);

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        self::assertSame($linkHtml, $result);
    }

    /**
     * Test that renderLink processes inline images and reconstructs link.
     *
     * This is the key test for the "links spanning text and inline images" fix.
     * Input: <a href="...">Click here <img class="image-inline"...> to visit</a>
     * Output: Same structure with processed <img> tag.
     */
    #[Test]
    public function renderLinkProcessesInlineImagesAndReconstructsLink(): void
    {
        $originalImg = '<img src="/image.jpg" data-htmlarea-file-uid="1" class="image image-inline" />';
        $linkHtml    = '<a href="/page" target="_blank">Click here ' . $originalImg . ' to visit</a>';

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 50,
            height: 50,
            alt: 'Icon',
            title: null,
            htmlAttributes: ['class' => 'image image-inline'],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => '/page', 'target' => '_blank'],
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
            ->willReturn('<img src="/processed.jpg" class="image image-inline" />');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        // Should have reconstructed link with processed image
        self::assertStringContainsString('<a href="/page"', $result);
        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString('<img src="/processed.jpg"', $result);
        self::assertStringContainsString('Click here', $result);
        self::assertStringContainsString('to visit', $result);
        self::assertStringContainsString('</a>', $result);
    }

    /**
     * Test that renderLink skips block images (only processes inline).
     *
     * Block images inside <a> tags within <figure> elements should be
     * processed by renderFigure() instead.
     */
    #[Test]
    public function renderLinkSkipsBlockImages(): void
    {
        // Block image (no "image-inline" class)
        $originalImg = '<img src="/image.jpg" data-htmlarea-file-uid="1" class="image" />';
        $linkHtml    = '<a href="/page">' . $originalImg . '</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => '/page'],
                'images' => [
                    [
                        'attributes' => [
                            'src'                    => '/image.jpg',
                            'data-htmlarea-file-uid' => '1',
                            'class'                  => 'image', // No image-inline
                        ],
                        'originalHtml' => $originalImg,
                    ],
                ],
            ]);

        // Resolver should NOT be called for block images
        $this->resolverService
            ->expects(self::never())
            ->method('resolve');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        // Should reconstruct link with original image (unprocessed)
        self::assertStringContainsString('<a href="/page"', $result);
        self::assertStringContainsString($originalImg, $result);
        self::assertStringContainsString('</a>', $result);
    }

    /**
     * Test that renderLink skips images without file UID.
     */
    #[Test]
    public function renderLinkSkipsImagesWithoutFileUid(): void
    {
        $originalImg = '<img src="/external.jpg" class="image image-inline" />';
        $linkHtml    = '<a href="/page">' . $originalImg . '</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => '/page'],
                'images' => [
                    [
                        'attributes' => [
                            'src'   => '/external.jpg',
                            'class' => 'image image-inline',
                            // No data-htmlarea-file-uid
                        ],
                        'originalHtml' => $originalImg,
                    ],
                ],
            ]);

        // Resolver should NOT be called for external images
        $this->resolverService
            ->expects(self::never())
            ->method('resolve');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        // Should reconstruct link with original content
        self::assertStringContainsString('<a href="/page"', $result);
        self::assertStringContainsString($originalImg, $result);
    }

    /**
     * Test that renderLink strips caption/zoom attributes from inline images.
     *
     * Images inside links should not create figure wrappers or popup links.
     */
    #[Test]
    public function renderLinkStripsAttributesThatWouldCreateWrappers(): void
    {
        $originalImg = '<img src="/image.jpg" data-htmlarea-file-uid="1" class="image image-inline" '
            . 'data-caption="Caption" data-htmlarea-zoom="1" />';
        $linkHtml = '<a href="/page">' . $originalImg . '</a>';

        $dto = new ImageRenderingDto(
            src: '/processed.jpg',
            width: 50,
            height: 50,
            alt: 'Test',
            title: null,
            htmlAttributes: ['class' => 'image image-inline'],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => '/page'],
                'images' => [
                    [
                        'attributes' => [
                            'src'                        => '/image.jpg',
                            'data-htmlarea-file-uid'     => '1',
                            'class'                      => 'image image-inline',
                            'data-caption'               => 'Caption',
                            'data-htmlarea-zoom'         => '1',
                            'data-htmlarea-clickenlarge' => '1',
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
                    return !array_key_exists('data-caption', $attributes)
                        && !array_key_exists('data-htmlarea-zoom', $attributes)
                        && !array_key_exists('data-htmlarea-clickenlarge', $attributes)
                        && ($attributes['data-htmlarea-file-uid'] ?? '') === '1';
                }),
                self::anything(),
                self::anything(),
            )
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<img src="/processed.jpg" />');

        $this->adapter->renderLink($linkHtml, [], $this->request);
    }

    /**
     * Test that renderLink handles multiple inline images in one link.
     */
    #[Test]
    public function renderLinkHandlesMultipleInlineImages(): void
    {
        $img1     = '<img src="/a.jpg" data-htmlarea-file-uid="1" class="image image-inline" />';
        $img2     = '<img src="/b.jpg" data-htmlarea-file-uid="2" class="image image-inline" />';
        $linkHtml = '<a href="/page">First: ' . $img1 . ' Second: ' . $img2 . '</a>';

        $dto1 = new ImageRenderingDto(
            src: '/processed-a.jpg',
            width: 50,
            height: 50,
            alt: 'A',
            title: null,
            htmlAttributes: ['class' => 'image image-inline'],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $dto2 = new ImageRenderingDto(
            src: '/processed-b.jpg',
            width: 50,
            height: 50,
            alt: 'B',
            title: null,
            htmlAttributes: ['class' => 'image image-inline'],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => '/page'],
                'images' => [
                    [
                        'attributes' => [
                            'src'                    => '/a.jpg',
                            'data-htmlarea-file-uid' => '1',
                            'class'                  => 'image image-inline',
                        ],
                        'originalHtml' => $img1,
                    ],
                    [
                        'attributes' => [
                            'src'                    => '/b.jpg',
                            'data-htmlarea-file-uid' => '2',
                            'class'                  => 'image image-inline',
                        ],
                        'originalHtml' => $img2,
                    ],
                ],
            ]);

        $this->resolverService
            ->expects(self::exactly(2))
            ->method('resolve')
            ->willReturnOnConsecutiveCalls($dto1, $dto2);

        $this->renderingService
            ->expects(self::exactly(2))
            ->method('render')
            ->willReturnOnConsecutiveCalls(
                '<img src="/processed-a.jpg" class="image image-inline" />',
                '<img src="/processed-b.jpg" class="image image-inline" />',
            );

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        // Both images should be processed
        self::assertStringContainsString('<img src="/processed-a.jpg"', $result);
        self::assertStringContainsString('<img src="/processed-b.jpg"', $result);
        self::assertStringContainsString('First:', $result);
        self::assertStringContainsString('Second:', $result);
    }

    /**
     * Test that renderLink accepts content from first parameter.
     *
     * externalBlocks passes content as first parameter, not just via cObj.
     */
    #[Test]
    public function renderLinkAcceptsContentFromFirstParameter(): void
    {
        $linkHtml = '<a href="/page">Text only</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        // cObj returns nothing, but content is passed as first param
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn(null);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->with($linkHtml)
            ->willReturn([
                'link'   => ['href' => '/page'],
                'images' => [],
            ]);

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        self::assertSame($linkHtml, $result);
    }

    /**
     * Test renderLink with text before and after inline image.
     *
     * Common pattern: "Click here <img> to visit TYPO3"
     */
    #[Test]
    public function renderLinkWithTextBeforeAndAfterImage(): void
    {
        $originalImg = '<img src="/icon.jpg" data-htmlarea-file-uid="1" class="image image-inline" />';
        $linkHtml    = '<a href="https://typo3.org" target="_blank">Click here ' . $originalImg . ' to visit TYPO3</a>';

        $dto = new ImageRenderingDto(
            src: '/processed-icon.jpg',
            width: 24,
            height: 24,
            alt: 'TYPO3 Logo',
            title: null,
            htmlAttributes: ['class' => 'image image-inline'],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => 'https://typo3.org', 'target' => '_blank'],
                'images' => [
                    [
                        'attributes' => [
                            'src'                    => '/icon.jpg',
                            'data-htmlarea-file-uid' => '1',
                            'class'                  => 'image image-inline',
                        ],
                        'originalHtml' => $originalImg,
                    ],
                ],
            ]);

        $this->resolverService
            ->method('resolve')
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<img src="/processed-icon.jpg" width="24" height="24" alt="TYPO3 Logo" class="image image-inline" />');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        // Verify complete structure
        self::assertStringContainsString('<a href="https://typo3.org"', $result);
        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString('Click here ', $result);
        self::assertStringContainsString('<img src="/processed-icon.jpg"', $result);
        self::assertStringContainsString(' to visit TYPO3', $result);
        self::assertStringContainsString('</a>', $result);
    }

    /**
     * Test renderLink with image at beginning of link text.
     *
     * Pattern: "<img> TYPO3 Documentation"
     */
    #[Test]
    public function renderLinkWithImageAtBeginning(): void
    {
        $originalImg = '<img src="/logo.jpg" data-htmlarea-file-uid="1" class="image image-inline" />';
        $linkHtml    = '<a href="/docs">' . $originalImg . ' TYPO3 Documentation</a>';

        $dto = new ImageRenderingDto(
            src: '/processed-logo.jpg',
            width: 32,
            height: 32,
            alt: 'Logo',
            title: null,
            htmlAttributes: ['class' => 'image image-inline'],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => '/docs'],
                'images' => [
                    [
                        'attributes' => [
                            'src'                    => '/logo.jpg',
                            'data-htmlarea-file-uid' => '1',
                            'class'                  => 'image image-inline',
                        ],
                        'originalHtml' => $originalImg,
                    ],
                ],
            ]);

        $this->resolverService
            ->method('resolve')
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<img src="/processed-logo.jpg" />');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        self::assertStringContainsString('<a href="/docs"', $result);
        self::assertStringContainsString('<img src="/processed-logo.jpg"', $result);
        self::assertStringContainsString('TYPO3 Documentation</a>', $result);
    }

    /**
     * Test renderLink with image at end of link text.
     *
     * Pattern: "Download PDF <img>"
     */
    #[Test]
    public function renderLinkWithImageAtEnd(): void
    {
        $originalImg = '<img src="/pdf-icon.jpg" data-htmlarea-file-uid="1" class="image image-inline" />';
        $linkHtml    = '<a href="/download.pdf">Download PDF ' . $originalImg . '</a>';

        $dto = new ImageRenderingDto(
            src: '/processed-pdf-icon.jpg',
            width: 16,
            height: 16,
            alt: 'PDF',
            title: null,
            htmlAttributes: ['class' => 'image image-inline'],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => '/download.pdf'],
                'images' => [
                    [
                        'attributes' => [
                            'src'                    => '/pdf-icon.jpg',
                            'data-htmlarea-file-uid' => '1',
                            'class'                  => 'image image-inline',
                        ],
                        'originalHtml' => $originalImg,
                    ],
                ],
            ]);

        $this->resolverService
            ->method('resolve')
            ->willReturn($dto);

        $this->renderingService
            ->method('render')
            ->willReturn('<img src="/processed-pdf-icon.jpg" />');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        self::assertStringContainsString('<a href="/download.pdf"', $result);
        self::assertStringContainsString('Download PDF ', $result);
        self::assertStringContainsString('<img src="/processed-pdf-icon.jpg"', $result);
        self::assertStringContainsString('</a>', $result);
    }

    // ========================================================================
    // Issue #606 Tests - renderLink must resolve t3:// URLs in text-only links
    // ========================================================================

    /**
     * Test that renderLink resolves t3:// URLs in links without images.
     *
     * Since externalBlocks.a bypasses TYPO3's normal link resolution (tags.a
     * is cleared), t3:// links must be resolved explicitly even when the link
     * contains only text and no images.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/606
     */
    #[Test]
    public function renderLinkResolvesT3UrlInTextOnlyLink(): void
    {
        $linkHtml = '<a href="t3://page?uid=42">Visit our page</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->expects(self::once())
            ->method('parseLinkWithImages')
            ->with($linkHtml)
            ->willReturn([
                'link'   => ['href' => 't3://page?uid=42'],
                'images' => [],
            ]);

        // ContentObjectRenderer must be called to resolve the t3:// URL
        $this->contentObjectRenderer
            ->expects(self::once())
            ->method('typoLink_URL')
            ->with(['parameter' => 't3://page?uid=42'])
            ->willReturn('/my-page/');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        self::assertStringContainsString('href="/my-page/"', $result);
        self::assertStringContainsString('Visit our page', $result);
        self::assertStringContainsString('</a>', $result);
        // Must NOT contain unresolved t3:// URL
        self::assertStringNotContainsString('t3://page', $result);
    }

    /**
     * Test that renderLink resolves t3://file links in text-only links.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/606
     */
    #[Test]
    public function renderLinkResolvesT3FileUrlInTextOnlyLink(): void
    {
        $linkHtml = '<a href="t3://file?uid=123" class="download">Download PDF</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => 't3://file?uid=123', 'class' => 'download'],
                'images' => [],
            ]);

        $this->contentObjectRenderer
            ->expects(self::once())
            ->method('typoLink_URL')
            ->with(['parameter' => 't3://file?uid=123'])
            ->willReturn('/fileadmin/document.pdf');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        self::assertStringContainsString('href="/fileadmin/document.pdf"', $result);
        self::assertStringContainsString('class="download"', $result);
        self::assertStringContainsString('Download PDF', $result);
        self::assertStringNotContainsString('t3://file', $result);
    }

    /**
     * Test that renderLink preserves all link attributes when resolving t3:// URLs.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/606
     */
    #[Test]
    public function renderLinkPreservesLinkAttributesWhenResolvingT3Url(): void
    {
        $linkHtml = '<a href="t3://page?uid=42" target="_blank" class="external" title="My Page">Click here</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link' => [
                    'href'   => 't3://page?uid=42',
                    'target' => '_blank',
                    'class'  => 'external',
                    'title'  => 'My Page',
                ],
                'images' => [],
            ]);

        $this->contentObjectRenderer
            ->method('typoLink_URL')
            ->willReturn('/my-page/');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        self::assertStringContainsString('href="/my-page/"', $result);
        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString('class="external"', $result);
        self::assertStringContainsString('title="My Page"', $result);
        self::assertStringContainsString('Click here', $result);
    }

    /**
     * Test that renderLink does NOT modify non-t3:// links without images.
     *
     * Regular https/mailto/relative links should be returned unchanged.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/606
     */
    #[Test]
    public function renderLinkDoesNotModifyNonT3LinksWithoutImages(): void
    {
        $linkHtml = '<a href="https://example.com" target="_blank">External link</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => 'https://example.com', 'target' => '_blank'],
                'images' => [],
            ]);

        // typoLink_URL should NOT be called for non-t3:// URLs
        $this->contentObjectRenderer
            ->expects(self::never())
            ->method('typoLink_URL');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        // Should return original HTML unchanged
        self::assertSame($linkHtml, $result);
    }

    /**
     * Test that renderLink handles t3:// resolution returning empty string.
     *
     * When typoLink_URL cannot resolve a t3:// URL, it returns empty string.
     * The original t3:// URL should be preserved as fallback.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/606
     */
    #[Test]
    public function renderLinkFallsBackToOriginalWhenT3ResolutionFails(): void
    {
        $linkHtml = '<a href="t3://page?uid=99999">Broken link</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => 't3://page?uid=99999'],
                'images' => [],
            ]);

        // typoLink_URL returns empty when resolution fails
        $this->contentObjectRenderer
            ->method('typoLink_URL')
            ->willReturn('');

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        // Should keep the original t3:// URL since resolution failed
        self::assertStringContainsString('t3://page?uid=99999', $result);
        self::assertStringContainsString('Broken link', $result);
    }

    /**
     * Test that renderLink strips javascript: links in text-only anchors.
     *
     * SECURITY: Since externalBlocks.a is the sole handler for all <a> tags
     * (tags.a is cleared), we must validate protocols ourselves.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/606
     */
    #[Test]
    public function renderLinkStripsDisallowedProtocolInTextOnlyLink(): void
    {
        $linkHtml = '<a href="javascript:alert(1)">Click me</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => 'javascript:alert(1)'],
                'images' => [],
            ]);

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        // Link wrapper must be stripped, only inner content returned
        self::assertStringNotContainsString('<a ', $result);
        self::assertStringNotContainsString('javascript:', $result);
        self::assertStringContainsString('Click me', $result);
    }

    /**
     * Test that renderLink strips empty links with disallowed protocols.
     *
     * Edge case: <a href="javascript:..."></a> with no inner content must
     * return empty string, not preserve the malicious link tag.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/606
     */
    #[Test]
    public function renderLinkStripsEmptyLinkWithDisallowedProtocol(): void
    {
        $linkHtml = '<a href="javascript:alert(1)"></a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkHtml);

        $this->attributeParser
            ->method('parseLinkWithImages')
            ->willReturn([
                'link'   => ['href' => 'javascript:alert(1)'],
                'images' => [],
            ]);

        $result = $this->adapter->renderLink($linkHtml, [], $this->request);

        // Must return empty string, not the original malicious link
        self::assertSame('', $result);
        self::assertStringNotContainsString('javascript:', $result);
    }

    // ========================================================================
    // renderInlineLink() Tests - tags.a handler for t3:// resolution
    // ========================================================================

    /**
     * Test that renderInlineLink returns empty string when no inner content.
     */
    #[Test]
    public function renderInlineLinkReturnsEmptyStringWhenNoContent(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = ['href' => '/page'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertSame('', $result);
    }

    /**
     * Test that renderInlineLink returns content without wrapper when no href.
     */
    #[Test]
    public function renderInlineLinkReturnsContentWithoutWrapperWhenNoHref(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = ['class' => 'no-link'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('Just text');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertSame('Just text', $result);
    }

    /**
     * Test that renderInlineLink resolves t3://page URLs.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/659
     */
    #[Test]
    public function renderInlineLinkResolvesT3PageUrl(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = [
            'href'   => 't3://page?uid=1#1',
            'target' => '_blank',
        ];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn(
            '<img src="/fileadmin/_processed_/csm_moto_dog_1_274710645e.jpg" class="image image-inline" />',
        );

        $this->contentObjectRenderer
            ->expects(self::once())
            ->method('typoLink_URL')
            ->with(['parameter' => 't3://page?uid=1#1'])
            ->willReturn('/my-page/#1');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertStringContainsString('href="/my-page/#1"', $result);
        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString('<img src="/fileadmin/_processed_/', $result);
        self::assertStringNotContainsString('t3://page', $result);
    }

    /**
     * Test that renderInlineLink preserves all link attributes.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/659
     */
    #[Test]
    public function renderInlineLinkPreservesAllLinkAttributes(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = [
            'href'   => 'https://example.com',
            'target' => '_blank',
            'class'  => 'image image-inline',
            'title'  => 'Example Link',
        ];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('Link content');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertStringContainsString('href="https://example.com"', $result);
        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString('class="image image-inline"', $result);
        self::assertStringContainsString('title="Example Link"', $result);
        self::assertStringContainsString('Link content', $result);
        self::assertStringContainsString('</a>', $result);
    }

    /**
     * Test that renderInlineLink blocks javascript: protocol links.
     *
     * SECURITY: Prevents XSS via javascript: URLs.
     */
    #[Test]
    public function renderInlineLinkBlocksJavascriptProtocol(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = ['href' => 'javascript:alert(1)'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('Click me');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        // Link wrapper must be stripped, only inner content returned
        self::assertSame('Click me', $result);
        self::assertStringNotContainsString('<a ', $result);
        self::assertStringNotContainsString('javascript:', $result);
    }

    /**
     * Test that renderInlineLink passes through non-t3:// links unchanged.
     */
    #[Test]
    public function renderInlineLinkPassesThroughRegularUrls(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = ['href' => 'https://typo3.org'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('TYPO3');

        // typoLink_URL should NOT be called for non-t3:// URLs
        $this->contentObjectRenderer
            ->expects(self::never())
            ->method('typoLink_URL');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertSame('<a href="https://typo3.org">TYPO3</a>', $result);
    }

    /**
     * Test that renderInlineLink handles t3:// resolution failure gracefully.
     *
     * When typoLink_URL returns empty string, original URL is preserved.
     */
    #[Test]
    public function renderInlineLinkFallsBackWhenT3ResolutionFails(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = ['href' => 't3://page?uid=99999'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('Broken link');

        $this->contentObjectRenderer
            ->method('typoLink_URL')
            ->willReturn('');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        // Original t3:// URL preserved as fallback
        self::assertStringContainsString('t3://page?uid=99999', $result);
        self::assertStringContainsString('Broken link', $result);
    }

    /**
     * Test that renderInlineLink blocks data: protocol links.
     *
     * SECURITY: Prevents XSS via data: URLs.
     */
    #[Test]
    public function renderInlineLinkBlocksDataProtocol(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = ['href' => 'data:text/html,<script>alert(1)</script>'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('Malicious');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertSame('Malicious', $result);
        self::assertStringNotContainsString('data:', $result);
    }

    /**
     * Test that renderInlineLink blocks vbscript: protocol links.
     *
     * SECURITY: Prevents XSS via vbscript: URLs.
     */
    #[Test]
    public function renderInlineLinkBlocksVbscriptProtocol(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = ['href' => 'vbscript:MsgBox("XSS")'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('Malicious');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertSame('Malicious', $result);
        self::assertStringNotContainsString('vbscript:', $result);
    }

    /**
     * Test that renderInlineLink passes through mailto: links unchanged.
     */
    #[Test]
    public function renderInlineLinkPassesThroughMailtoLinks(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = ['href' => 'mailto:info@example.com'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('Email us');

        $this->contentObjectRenderer
            ->expects(self::never())
            ->method('typoLink_URL');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertSame('<a href="mailto:info@example.com">Email us</a>', $result);
    }

    /**
     * Test that renderInlineLink passes through tel: links unchanged.
     */
    #[Test]
    public function renderInlineLinkPassesThroughTelLinks(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = ['href' => 'tel:+491234567890'];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('Call us');

        $this->contentObjectRenderer
            ->expects(self::never())
            ->method('typoLink_URL');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertSame('<a href="tel:+491234567890">Call us</a>', $result);
    }

    /**
     * Test that renderInlineLink works with already-processed image content.
     *
     * This is the key scenario: tags.img processes the image first (depth-first),
     * then tags.a wraps the result. The inner content is already a processed <img>.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/659
     */
    #[Test]
    public function renderInlineLinkWrapsAlreadyProcessedImageContent(): void
    {
        $processedImg = '<img src="/fileadmin/_processed_/0/0/csm_moto_dog_1_274710645e.jpg" '
            . 'width="200" height="150" alt="Dog" class="image image-inline" '
            . 'decoding="async" loading="lazy" />';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = [
            'href'   => 't3://page?uid=1',
            'target' => '_blank',
            'class'  => 'image image-inline',
        ];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($processedImg);

        $this->contentObjectRenderer
            ->method('typoLink_URL')
            ->with(['parameter' => 't3://page?uid=1'])
            ->willReturn('/home/');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertStringContainsString('<a href="/home/"', $result);
        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString('class="image image-inline"', $result);
        self::assertStringContainsString($processedImg, $result);
        self::assertStringContainsString('</a>', $result);
        self::assertStringNotContainsString('t3://', $result);
    }

    // ========================================================================
    // Issue #667 Tests - renderInlineLink must handle double-wrapped <a> tags
    // ========================================================================

    /**
     * Test that renderInlineLink strips nested <a> wrapper from double-wrapped content.
     *
     * When DB has historical <a><a><img></a></a>, parseFunc's tags.a passes the
     * content between outer <a> and first </a> as currentVal, which includes the
     * inner <a> opening tag: <a class="..." href="..."><img ...>
     * renderInlineLink must strip this nested <a> before re-wrapping.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/667
     */
    #[Test]
    public function renderInlineLinkStripsNestedLinkWrapperFromDoubleWrappedContent(): void
    {
        // parseFunc gives us the content between outer <a> and first </a>:
        // <a class="image image-inline" href="t3://page?uid=1#1" target="_blank"><img class="image-inline" src="/fileadmin/test.jpg" data-htmlarea-file-uid="2">
        $innerContent = '<a class="image image-inline" href="t3://page?uid=1#1" target="_blank">'
            . '<img class="image-inline" src="/fileadmin/test.jpg" data-htmlarea-file-uid="2">';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        // Outer <a> attributes are in cObj->parameters
        $this->contentObjectRenderer->parameters = [
            'href'   => 't3://page?uid=1#1',
            'target' => '_blank',
            'class'  => 'image image-inline',
        ];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($innerContent);

        $this->contentObjectRenderer
            ->method('typoLink_URL')
            ->with(['parameter' => 't3://page?uid=1#1'])
            ->willReturn('/my-page/#1');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        // Should have a single <a> wrapping just the <img> (no nested <a>)
        self::assertStringContainsString('href="/my-page/#1"', $result);
        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString('<img class="image-inline"', $result);
        // Must have exactly one opening <a> tag
        self::assertSame(1, substr_count($result, '<a '));
        // Must have exactly one closing </a> tag
        self::assertSame(1, substr_count($result, '</a>'));
        // Must NOT contain unresolved t3:// URL
        self::assertStringNotContainsString('t3://page', $result);
    }

    /**
     * Test that renderInlineLink handles double-wrapped content with closing </a>.
     *
     * When parseFunc collects up to the first </a>, the result may include
     * the inner <a>'s closing tag: <a ...><img ...></a>
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/667
     */
    #[Test]
    public function renderInlineLinkStripsNestedLinkWrapperWithClosingTag(): void
    {
        $innerContent = '<a class="image image-inline" href="t3://page?uid=1#1" target="_blank">'
            . '<img class="image-inline" src="/fileadmin/test.jpg" data-htmlarea-file-uid="2" />'
            . '</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = [
            'href' => 'https://example.com',
        ];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($innerContent);

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        // Should have a single <a> wrapping just the <img>
        self::assertSame(1, substr_count($result, '<a '));
        self::assertSame(1, substr_count($result, '</a>'));
        self::assertStringContainsString('href="https://example.com"', $result);
        self::assertStringContainsString('<img class="image-inline"', $result);
    }

    /**
     * Test that renderInlineLink does NOT strip text links (only img-only nested wrappers).
     *
     * Conservative: the nested <a> stripping only applies when the entire content
     * is <a ...><img ...></a>. Text links like <a href="...">Click here</a>
     * should pass through unchanged.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/667
     */
    #[Test]
    public function renderInlineLinkDoesNotStripTextLinks(): void
    {
        $innerContent = '<a href="https://other.com">Click here</a>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = [
            'href' => 'https://example.com',
        ];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($innerContent);

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        // The nested text link should NOT be stripped — it's not an img-only wrapper
        self::assertStringContainsString('Click here', $result);
        // Outer <a> wraps the inner content (which itself contains an <a>)
        self::assertStringContainsString('href="https://example.com"', $result);
    }

    /**
     * Test that renderInlineLink returns empty when cObj is not set.
     */
    #[Test]
    public function renderInlineLinkReturnsEmptyWhenNoCObj(): void
    {
        // Don't call setContentObjectRenderer
        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertSame('', $result);
    }

    /**
     * Test that renderInlineLink filters non-string parameters.
     *
     * cObj->parameters may contain non-string values; they must be skipped.
     */
    #[Test]
    public function renderInlineLinkFiltersNonStringParameters(): void
    {
        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->parameters = [
            'href'  => '/page',
            0       => 'numeric-key', // Non-string key
            'valid' => 'attribute',
        ];
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn('Content');

        $result = $this->adapter->renderInlineLink(null, [], $this->request);

        self::assertStringContainsString('href="/page"', $result);
        self::assertStringContainsString('valid="attribute"', $result);
        self::assertStringNotContainsString('numeric-key', $result);
    }
}
