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
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;

/**
 * Test case for ImageRenderingService.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
#[AllowMockObjectsWithoutExpectations]
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
            params: null,
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
            params: null,
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
            params: null,
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
            params: null,
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
            params: null,
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
        $linkDto1 = new LinkDto('/', null, null, null, true, null);
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
        $linkDto2 = new LinkDto('/', null, null, null, true, null);
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
        $linkDto3 = new LinkDto('/', null, null, null, false, null);
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
        $linkDto4 = new LinkDto('/', null, null, null, false, null);
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

    // ========================================================================
    // Issue #595: Alignment class without caption should NOT trigger figure
    // ========================================================================

    /**
     * Image with figureClass but no caption should use Standalone template.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/595
     */
    public function testSelectTemplateWithAlignmentClassButNoCaptionUsesStandalone(): void
    {
        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: ['class' => 'image image-left'],
            caption: null,
            link: null,
            isMagicImage: true,
            figureClass: 'image image-left',
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/Standalone', $template);
    }

    /**
     * Linked image with figureClass but no caption should use Link template (not LinkWithCaption).
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/595
     */
    public function testSelectTemplateWithAlignmentClassLinkedNoCaptionUsesLink(): void
    {
        $linkDto = new LinkDto(
            url: 'https://example.com',
            target: '_blank',
            class: null,
            params: null,
            isPopup: false,
            jsConfig: null,
        );

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: ['class' => 'image image-center'],
            caption: null,
            link: $linkDto,
            isMagicImage: true,
            figureClass: 'image image-center',
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/Link', $template);
    }

    /**
     * Image with BOTH alignment class AND caption should use WithCaption template.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/595
     */
    public function testSelectTemplateWithAlignmentClassAndCaptionUsesWithCaption(): void
    {
        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: 'A caption',
            link: null,
            isMagicImage: true,
            figureClass: 'image image-right',
        );

        $template = $this->callProtectedMethod($this->service, 'selectTemplate', [$dto]);

        self::assertSame('Image/WithCaption', $template);
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
                         height="600"
                         decoding="async" />
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
                params: null,
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

    // ========================================================================
    // TypoScript Configuration Tests (Issue #434)
    // ========================================================================

    /**
     * Test that render() uses default template paths when no configuration provided.
     */
    public function testRenderUsesDefaultPathsWithoutConfiguration(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                // Verify default paths are used
                $templatePaths = $data->templateRootPaths ?? [];
                $partialPaths  = $data->partialRootPaths ?? [];
                $layoutPaths   = $data->layoutRootPaths ?? [];

                // Partials in TYPO3 standard location per issue #547
                return in_array('EXT:rte_ckeditor_image/Resources/Private/Templates/', $templatePaths, true)
                    && in_array('EXT:rte_ckeditor_image/Resources/Private/Partials/', $partialPaths, true)
                    && in_array('EXT:rte_ckeditor_image/Resources/Private/Templates/Layouts/', $layoutPaths, true);
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $this->service->render($dto, $requestMock);
    }

    /**
     * Test that render() uses custom template paths from configuration.
     */
    public function testRenderUsesCustomTemplatePathsFromConfiguration(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                $templatePaths = $data->templateRootPaths ?? [];

                // Default path should be first (lower priority), custom path second (higher priority)
                $defaultPath = 'EXT:rte_ckeditor_image/Resources/Private/Templates/';
                $customPath  = 'EXT:my_sitepackage/Resources/Private/Templates/';

                $defaultPos = array_search($defaultPath, $templatePaths, true);
                $customPos  = array_search($customPath, $templatePaths, true);

                // Both must exist and custom must come after default (higher priority)
                return $defaultPos !== false && $customPos !== false && $defaultPos < $customPos;
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $config = [
            'templateRootPaths.' => [
                '10' => 'EXT:my_sitepackage/Resources/Private/Templates/',
            ],
        ];

        $this->service->render($dto, $requestMock, $config);
    }

    /**
     * Test that render() uses custom partial paths from configuration.
     */
    public function testRenderUsesCustomPartialPathsFromConfiguration(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                $partialPaths = $data->partialRootPaths ?? [];

                return in_array('EXT:my_sitepackage/Resources/Private/Partials/', $partialPaths, true);
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $config = [
            'partialRootPaths.' => [
                '10' => 'EXT:my_sitepackage/Resources/Private/Partials/',
            ],
        ];

        $this->service->render($dto, $requestMock, $config);
    }

    /**
     * Test that render() uses custom layout paths from configuration.
     */
    public function testRenderUsesCustomLayoutPathsFromConfiguration(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                $layoutPaths = $data->layoutRootPaths ?? [];

                return in_array('EXT:my_sitepackage/Resources/Private/Layouts/', $layoutPaths, true);
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $config = [
            'layoutRootPaths.' => [
                '10' => 'EXT:my_sitepackage/Resources/Private/Layouts/',
            ],
        ];

        $this->service->render($dto, $requestMock, $config);
    }

    /**
     * Test that custom paths are merged with defaults, not replacing them.
     */
    public function testRenderMergesCustomPathsWithDefaults(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                $templatePaths = $data->templateRootPaths ?? [];

                // Both default AND custom paths should be present
                $hasDefault = in_array('EXT:rte_ckeditor_image/Resources/Private/Templates/', $templatePaths, true);
                $hasCustom  = in_array('EXT:my_sitepackage/Resources/Private/Templates/', $templatePaths, true);

                return $hasDefault && $hasCustom;
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $config = [
            'templateRootPaths.' => [
                '10' => 'EXT:my_sitepackage/Resources/Private/Templates/',
            ],
        ];

        $this->service->render($dto, $requestMock, $config);
    }

    /**
     * Test that higher-numbered paths take precedence (standard TYPO3 convention).
     */
    public function testRenderPathPriorityFollowsTypo3Convention(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                $templatePaths = $data->templateRootPaths ?? [];

                // Paths should be ordered by key: 0, 10, 20 (higher = later = higher priority)
                // Find positions
                $defaultPos  = array_search('EXT:rte_ckeditor_image/Resources/Private/Templates/', $templatePaths, true);
                $sitePos     = array_search('EXT:my_sitepackage/Resources/Private/Templates/', $templatePaths, true);
                $overridePos = array_search('EXT:override_package/Resources/Private/Templates/', $templatePaths, true);

                // Later items have higher priority in Fluid - verify correct order
                return $defaultPos !== false && $sitePos !== false && $overridePos !== false
                    && $defaultPos < $sitePos && $sitePos < $overridePos;
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        $config = [
            'templateRootPaths.' => [
                '10' => 'EXT:my_sitepackage/Resources/Private/Templates/',
                '20' => 'EXT:override_package/Resources/Private/Templates/',
            ],
        ];

        $this->service->render($dto, $requestMock, $config);
    }

    /**
     * Test that settings wrapper is supported for TypoScript convention.
     */
    public function testRenderSupportsSettingsWrapper(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                $templatePaths = $data->templateRootPaths ?? [];

                return in_array('EXT:my_sitepackage/Resources/Private/Templates/', $templatePaths, true);
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        // Configuration with settings. wrapper (as documented)
        $config = [
            'settings.' => [
                'templateRootPaths.' => [
                    '10' => 'EXT:my_sitepackage/Resources/Private/Templates/',
                ],
            ],
        ];

        $this->service->render($dto, $requestMock, $config);
    }

    /**
     * Test that key 0 cannot be overridden by custom configuration.
     *
     * The default path at priority 0 should always be preserved.
     */
    public function testRenderProtectsDefaultPathAtKeyZero(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                $templatePaths = $data->templateRootPaths ?? [];

                // Default path should ALWAYS be first (key 0 protected)
                $defaultPath = 'EXT:rte_ckeditor_image/Resources/Private/Templates/';

                return isset($templatePaths[0]) && $templatePaths[0] === $defaultPath;
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        // Attempt to override key 0 - should be ignored
        $config = [
            'templateRootPaths.' => [
                '0' => 'EXT:malicious_override/Resources/Private/Templates/',
            ],
        ];

        $this->service->render($dto, $requestMock, $config);
    }

    /**
     * Test that non-numeric keys in path configuration are ignored.
     *
     * Only numeric keys > 0 should be accepted to prevent accidental
     * casting to 0 (which would override the default).
     */
    public function testRenderIgnoresNonNumericPathKeys(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                $templatePaths = $data->templateRootPaths ?? [];

                // Non-numeric key "foo" should be ignored (it would cast to 0)
                // So only default path should be present
                return count($templatePaths) === 1
                    && $templatePaths[0] === 'EXT:rte_ckeditor_image/Resources/Private/Templates/';
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        // Non-numeric key should be ignored
        $config = [
            'templateRootPaths.' => [
                'foo' => 'EXT:bad_override/Resources/Private/Templates/',
            ],
        ];

        $this->service->render($dto, $requestMock, $config);
    }

    /**
     * Test that string numeric keys from TypoScript are correctly converted to integers.
     *
     * Note: PHP auto-converts pure numeric string keys like '10' to integers in arrays.
     * This test uses a key with leading zeros ('010') which PHP preserves as a string,
     * allowing us to test the string-to-integer conversion path in mergePathsWithDefault().
     */
    public function testRenderConvertsStringNumericKeysToIntegers(): void
    {
        $viewMock = $this->createMock(ViewInterface::class);
        $viewMock->method('render')->willReturn('<img src="test.jpg" />');

        $this->viewFactoryMock
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (ViewFactoryData $data): bool {
                $templatePaths = $data->templateRootPaths ?? [];

                // String key '010' should be converted to int 10 and added after default
                // Expected order: [0] = default, [1] = custom (from key '010' -> 10)
                return count($templatePaths) === 2
                    && $templatePaths[0] === 'EXT:rte_ckeditor_image/Resources/Private/Templates/'
                    && $templatePaths[1] === 'EXT:custom/Resources/Private/Templates/';
            }))
            ->willReturn($viewMock);

        $requestMock = $this->createMock(ServerRequestInterface::class);

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt',
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        // Key '010' with leading zero stays as string in PHP arrays,
        // but ctype_digit('010') returns true, so it triggers the string conversion path
        $config = [
            'templateRootPaths.' => [
                '010' => 'EXT:custom/Resources/Private/Templates/',
            ],
        ];

        $this->service->render($dto, $requestMock, $config);
    }
}
