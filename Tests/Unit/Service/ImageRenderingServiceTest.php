<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service;

use Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto;
use Netresearch\RteCKEditorImage\Domain\Model\LinkDto;
use Netresearch\RteCKEditorImage\Service\ImageRenderingService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;

/**
 * Test case for ImageRenderingService.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
class ImageRenderingServiceTest extends TestCase
{
    private ImageRenderingService $service;

    /** @var ViewFactoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private ViewFactoryInterface $viewFactoryMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewFactoryMock = $this->createMock(ViewFactoryInterface::class);
        $this->service         = new ImageRenderingService($this->viewFactoryMock);
    }

    /**
     * Helper method to call protected methods for testing.
     *
     * @param object  $object     Object instance
     * @param string  $methodName Method name to call
     * @param mixed[] $parameters Parameters to pass
     *
     * @return mixed
     */
    protected function callProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionMethod($object::class, $methodName);

        return $reflection->invokeArgs($object, $parameters);
    }

    public function testSelectTemplateForStandaloneImage(): void
    {
        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/Standalone', $template);
    }

    public function testSelectTemplateForImageWithCaption(): void
    {
        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: 'This is a caption',
            link: null,
            isMagicImage: true,
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/WithCaption', $template);
    }

    public function testSelectTemplateForLinkedImage(): void
    {
        $linkDto = new LinkDto(
            url: 'https://example.com',
            target: '_blank',
            class: null,
            isPopup: false,
            jsConfig: null,
        );

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: null,
            link: $linkDto,
            isMagicImage: true,
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/Link', $template);
    }

    public function testSelectTemplateForLinkedImageWithCaption(): void
    {
        $linkDto = new LinkDto(
            url: 'https://example.com',
            target: '_blank',
            class: null,
            isPopup: false,
            jsConfig: null,
        );

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: 'Image caption',
            link: $linkDto,
            isMagicImage: true,
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/LinkWithCaption', $template);
    }

    public function testSelectTemplateForPopupImage(): void
    {
        $linkDto = new LinkDto(
            url: '/large-image.jpg',
            target: 'popup',
            class: null,
            isPopup: true,
            jsConfig: ['width' => 800, 'height' => 600],
        );

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 200,
            height: 150,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: null,
            link: $linkDto,
            isMagicImage: true,
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/Popup', $template);
    }

    public function testSelectTemplateForPopupImageWithCaption(): void
    {
        $linkDto = new LinkDto(
            url: '/large-image.jpg',
            target: 'popup',
            class: null,
            isPopup: true,
            jsConfig: ['width' => 800, 'height' => 600],
        );

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 200,
            height: 150,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: 'Click to enlarge',
            link: $linkDto,
            isMagicImage: true,
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/PopupWithCaption', $template);
    }

    public function testSelectTemplatePopupPriorityOverRegularLink(): void
    {
        // Popup should be selected even if it has both link and isPopup
        $linkDto = new LinkDto(
            url: '/large-image.jpg',
            target: '_blank',
            class: 'lightbox',
            isPopup: true,
            jsConfig: ['width' => 800],
        );

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 200,
            height: 150,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: null,
            link: $linkDto,
            isMagicImage: true,
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/Popup', $template);
    }

    public function testSelectTemplateWithEmptyCaptionUsesStandalone(): void
    {
        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: '',
            link: null,
            isMagicImage: true,
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/Standalone', $template);
    }

    public function testSelectTemplateWithWhitespaceCaptionUsesWithCaption(): void
    {
        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: '   ',
            link: null,
            isMagicImage: true,
        );

        // Caption with only whitespace should still trigger WithCaption template
        // (actual trimming happens in ImageResolverService)
        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/WithCaption', $template);
    }

    public function testSelectTemplateDecisionOrder(): void
    {
        // Test priority: Popup > Link > Caption > Standalone

        // Priority 1: Popup with caption
        $linkDto1 = new LinkDto('/', null, null, true, null);
        $dto1     = new ImageRenderingDto(
            '/img.jpg',
            100,
            100,
            null,
            null,
            [],
            'Caption',
            $linkDto1,
            true,
        );
        self::assertSame(
            'Image/PopupWithCaption',
            $this->callProtectedMethod($this->service, 'selectTemplate', [$dto1]),
        );

        // Priority 2: Popup without caption
        $linkDto2 = new LinkDto('/', null, null, true, null);
        $dto2     = new ImageRenderingDto(
            '/img.jpg',
            100,
            100,
            null,
            null,
            [],
            null,
            $linkDto2,
            true,
        );
        self::assertSame(
            'Image/Popup',
            $this->callProtectedMethod($this->service, 'selectTemplate', [$dto2]),
        );

        // Priority 3: Link with caption
        $linkDto3 = new LinkDto('/', null, null, false, null);
        $dto3     = new ImageRenderingDto(
            '/img.jpg',
            100,
            100,
            null,
            null,
            [],
            'Caption',
            $linkDto3,
            true,
        );
        self::assertSame(
            'Image/LinkWithCaption',
            $this->callProtectedMethod($this->service, 'selectTemplate', [$dto3]),
        );

        // Priority 4: Link without caption
        $linkDto4 = new LinkDto('/', null, null, false, null);
        $dto4     = new ImageRenderingDto(
            '/img.jpg',
            100,
            100,
            null,
            null,
            [],
            null,
            $linkDto4,
            true,
        );
        self::assertSame(
            'Image/Link',
            $this->callProtectedMethod($this->service, 'selectTemplate', [$dto4]),
        );

        // Priority 5: Caption only
        $dto5 = new ImageRenderingDto(
            '/img.jpg',
            100,
            100,
            null,
            null,
            [],
            'Caption',
            null,
            true,
        );
        self::assertSame(
            'Image/WithCaption',
            $this->callProtectedMethod($this->service, 'selectTemplate', [$dto5]),
        );

        // Priority 6: Standalone (default)
        $dto6 = new ImageRenderingDto(
            '/img.jpg',
            100,
            100,
            null,
            null,
            [],
            null,
            null,
            true,
        );
        self::assertSame(
            'Image/Standalone',
            $this->callProtectedMethod($this->service, 'selectTemplate', [$dto6]),
        );
    }

    /**
     * Test that render() trims whitespace from Fluid template output.
     *
     * This prevents parseFunc_RTE from converting newlines into <p>&nbsp;</p>.
     */
    public function testRenderTrimsWhitespace(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->expects(self::once())
            ->method('render')
            ->willReturn("\n<figure><img src=\"test.jpg\"/></figure>\n\n");

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: 'Caption',
            link: null,
            isMagicImage: true,
        );

        $result = $this->service->render($dto, $requestMock);

        // Should be trimmed - no leading/trailing whitespace
        self::assertSame('<figure><img src="test.jpg"/></figure>', $result);
    }

    /**
     * Test that render() normalizes multi-line attributes within HTML tags.
     *
     * Fluid templates use multi-line formatting for readability:
     *   <a href="..."
     *      target="_blank"
     *      class="popup">
     *
     * This must be normalized to single-line to prevent parseFunc_RTE issues.
     */
    public function testRenderNormalizesMultiLineAttributes(): void
    {
        // Simulate Fluid output with multi-line attributes (readable template format)
        $multiLineOutput = <<<HTML
            <figure class="image">
                <a href="/fileadmin/image.jpg"
                   target="_blank"
                   class="popup-link"
                   data-popup="true">
                    <img src="/fileadmin/image.jpg"
                         alt="Test"
                         width="800"
                         height="600" />
                </a>
                <figcaption>Caption text</figcaption>
            </figure>
            HTML;

        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->expects(self::once())
            ->method('render')
            ->willReturn($multiLineOutput);

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/fileadmin/image.jpg',
            width: 800,
            height: 600,
            alt: 'Test',
            title: '',
            htmlAttributes: [],
            caption: 'Caption text',
            link: new LinkDto(
                url: '/fileadmin/image.jpg',
                target: '_blank',
                class: 'popup-link',
                isPopup: true,
                jsConfig: null,
            ),
            isMagicImage: true,
        );

        $result = $this->service->render($dto, $requestMock);

        // Verify multi-line attributes are collapsed to single line
        self::assertStringNotContainsString("\n", $result);

        // Verify all attributes are preserved (just on single line)
        self::assertStringContainsString('href="/fileadmin/image.jpg"', $result);
        self::assertStringContainsString('target="_blank"', $result);
        self::assertStringContainsString('class="popup-link"', $result);
        self::assertStringContainsString('data-popup="true"', $result);
        self::assertStringContainsString('alt="Test"', $result);
        self::assertStringContainsString('width="800"', $result);

        // Verify no whitespace between tags (prevents <p>&nbsp;</p> artifacts)
        self::assertStringNotContainsString('> <', $result);
        self::assertStringContainsString('><', $result);
    }
}
