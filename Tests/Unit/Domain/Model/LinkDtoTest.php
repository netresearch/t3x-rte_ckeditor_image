<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Domain\Model;

use Netresearch\RteCKEditorImage\Domain\Model\LinkDto;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test case for LinkDto.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
class LinkDtoTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $jsConfig = [
            'width'   => 800,
            'height'  => 600,
            'bodyTag' => '<body style="margin:0;">',
        ];

        $dto = new LinkDto(
            url: 'https://example.com/image.jpg',
            target: '_blank',
            class: 'lightbox-link',
            params: '&L=1&type=123',
            isPopup: true,
            jsConfig: $jsConfig,
        );

        self::assertSame('https://example.com/image.jpg', $dto->url);
        self::assertSame('_blank', $dto->target);
        self::assertSame('lightbox-link', $dto->class);
        self::assertSame('&L=1&type=123', $dto->params);
        self::assertTrue($dto->isPopup);
        self::assertSame($jsConfig, $dto->jsConfig);
    }

    public function testConstructorAcceptsNullValues(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com',
            target: null,
            class: null,
            params: null,
            isPopup: false,
            jsConfig: null,
        );

        self::assertSame('https://example.com', $dto->url);
        self::assertNull($dto->target);
        self::assertNull($dto->class);
        self::assertNull($dto->params);
        self::assertFalse($dto->isPopup);
        self::assertNull($dto->jsConfig);
    }

    public function testPropertiesAreReadonly(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com',
            target: '_self',
            class: 'link',
            params: null,
            isPopup: false,
            jsConfig: null,
        );

        $reflection = new ReflectionClass($dto);

        $properties = ['url', 'target', 'class', 'params', 'isPopup', 'jsConfig'];

        foreach ($properties as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            self::assertTrue(
                $property->isReadOnly(),
                "Property {$propertyName} should be readonly",
            );
        }
    }

    public function testDtoIsImmutable(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com',
            target: null,
            class: null,
            params: null,
            isPopup: false,
            jsConfig: null,
        );

        $reflection = new ReflectionClass($dto);
        self::assertTrue(
            $reflection->isFinal(),
            'LinkDto should be final to prevent extension',
        );
    }

    public function testPopupLinkWithConfiguration(): void
    {
        $jsConfig = [
            'width'    => '800m',
            'height'   => '600m',
            'wrap'     => '<a href="javascript:close();"> | </a>',
            'JSwindow' => 1,
        ];

        $dto = new LinkDto(
            url: '/path/to/large-image.jpg',
            target: 'popup_window',
            class: 'popup-link',
            params: null,
            isPopup: true,
            jsConfig: $jsConfig,
        );

        self::assertTrue($dto->isPopup);
        self::assertNotNull($dto->jsConfig);
        self::assertSame('800m', $dto->jsConfig['width']);
        self::assertSame('600m', $dto->jsConfig['height']);
        self::assertSame(1, $dto->jsConfig['JSwindow']);
    }

    public function testRegularLinkWithoutConfiguration(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com/page',
            target: '_blank',
            class: 'external-link',
            params: null,
            isPopup: false,
            jsConfig: null,
        );

        self::assertFalse($dto->isPopup);
        self::assertNull($dto->jsConfig);
    }

    public function testEmptyStringValuesAccepted(): void
    {
        $dto = new LinkDto(
            url: '',
            target: '',
            class: '',
            params: '',
            isPopup: false,
            jsConfig: null,
        );

        self::assertSame('', $dto->url);
        self::assertSame('', $dto->target);
        self::assertSame('', $dto->class);
        self::assertSame('', $dto->params);
    }

    public function testJsConfigCanContainMixedTypes(): void
    {
        $jsConfig = [
            'width'   => 800,
            'height'  => '600m',
            'enabled' => true,
            'options' => ['crop' => false],
        ];

        $dto = new LinkDto(
            url: '/image.jpg',
            target: null,
            class: null,
            params: null,
            isPopup: true,
            jsConfig: $jsConfig,
        );

        self::assertNotNull($dto->jsConfig);
        self::assertSame(800, $dto->jsConfig['width']);
        self::assertSame('600m', $dto->jsConfig['height']);
        self::assertTrue($dto->jsConfig['enabled']);
        self::assertIsArray($dto->jsConfig['options']);
        self::assertFalse($dto->jsConfig['options']['crop']);
    }

    // ========================================================================
    // getUrlWithParams() Tests
    // ========================================================================

    public function testGetUrlWithParamsReturnsUrlWhenNoParams(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com/page',
            target: null,
            class: null,
            params: null,
            isPopup: false,
            jsConfig: null,
        );

        self::assertSame('https://example.com/page', $dto->getUrlWithParams());
    }

    public function testGetUrlWithParamsReturnsUrlWhenEmptyParams(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com/page',
            target: null,
            class: null,
            params: '',
            isPopup: false,
            jsConfig: null,
        );

        self::assertSame('https://example.com/page', $dto->getUrlWithParams());
    }

    public function testGetUrlWithParamsReplacesAmpersandWithQuestionMark(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com/page',
            target: null,
            class: null,
            params: '&L=1&type=123',
            isPopup: false,
            jsConfig: null,
        );

        // URL has no ?, so & should be replaced with ?
        self::assertSame('https://example.com/page?L=1&type=123', $dto->getUrlWithParams());
    }

    public function testGetUrlWithParamsAppendsToExistingQueryString(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com/page?foo=bar',
            target: null,
            class: null,
            params: '&L=1&type=123',
            isPopup: false,
            jsConfig: null,
        );

        // URL already has ?, so append params as-is
        self::assertSame('https://example.com/page?foo=bar&L=1&type=123', $dto->getUrlWithParams());
    }

    public function testGetUrlWithParamsHandlesParamsStartingWithQuestionMark(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com/page',
            target: null,
            class: null,
            params: '?L=1&type=123',
            isPopup: false,
            jsConfig: null,
        );

        // Params already start with ?, use as-is
        self::assertSame('https://example.com/page?L=1&type=123', $dto->getUrlWithParams());
    }

    public function testGetUrlWithParamsAddsQuestionMarkWhenParamsHaveNoPrefix(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com/page',
            target: null,
            class: null,
            params: 'L=1&type=123',
            isPopup: false,
            jsConfig: null,
        );

        // Params don't start with & or ?, add ?
        self::assertSame('https://example.com/page?L=1&type=123', $dto->getUrlWithParams());
    }

    public function testGetUrlWithParamsWorksWithTypo3InternalLinks(): void
    {
        // t3:// links get resolved before rendering, but test the pattern
        $dto = new LinkDto(
            url: '/page/about-us',
            target: null,
            class: null,
            params: '&cHash=abc123',
            isPopup: false,
            jsConfig: null,
        );

        self::assertSame('/page/about-us?cHash=abc123', $dto->getUrlWithParams());
    }
}
