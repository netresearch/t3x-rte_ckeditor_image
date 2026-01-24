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
use Netresearch\RteCKEditorImage\Service\ImageAttributeParser;
use Netresearch\RteCKEditorImage\Service\ImageRenderingService;
use Netresearch\RteCKEditorImage\Service\ImageResolverService;
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
            ->with($dto, $this->request)
            ->willReturn('<img src="/processed.jpg" width="800" height="600" alt="Test" />');

        $result = $this->adapter->renderImageAttributes(null, [], $this->request);

        self::assertSame('<img src="/processed.jpg" width="800" height="600" alt="Test" />', $result);
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
    public function renderImagesReturnsOriginalWhenNoImagesFound(): void
    {
        $linkContent = '<span>Text only, no images</span>';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
        $this->contentObjectRenderer->method('getCurrentVal')->willReturn($linkContent);

        $this->attributeParser
            ->expects(self::once())
            ->method('parseLinkWithImages')
            ->with($linkContent)
            ->willReturn(['link' => [], 'images' => []]);

        $result = $this->adapter->renderImages(null, [], $this->request);

        self::assertSame($linkContent, $result);
    }

    #[Test]
    public function renderImagesSkipsImagesWithoutFileUid(): void
    {
        $linkContent = '<img src="/external.jpg" />';

        $this->adapter->setContentObjectRenderer($this->contentObjectRenderer);
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

        self::assertSame($linkContent, $result);
    }

    #[Test]
    public function prefixIdAndExtKeyAreSet(): void
    {
        self::assertSame('ImageRenderingAdapter', $this->adapter->prefixId);
        self::assertSame('rte_ckeditor_image', $this->adapter->extKey);
        self::assertSame('Classes/Controller/ImageRenderingAdapter.php', $this->adapter->scriptRelPath);
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
            )
            ->willReturn($dto);

        $this->renderingService
            ->expects(self::once())
            ->method('render')
            ->with($dto, $this->request)
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
            ]);

        $this->resolverService
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(null); // Resolution fails

        $result = $this->adapter->renderFigure(null, [], $this->request);

        self::assertSame($html, $result);
    }
}
