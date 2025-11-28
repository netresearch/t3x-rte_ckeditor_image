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
            isPopup: true,
            jsConfig: $jsConfig,
        );

        self::assertSame('https://example.com/image.jpg', $dto->url);
        self::assertSame('_blank', $dto->target);
        self::assertSame('lightbox-link', $dto->class);
        self::assertTrue($dto->isPopup);
        self::assertSame($jsConfig, $dto->jsConfig);
    }

    public function testConstructorAcceptsNullValues(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com',
            target: null,
            class: null,
            isPopup: false,
            jsConfig: null,
        );

        self::assertSame('https://example.com', $dto->url);
        self::assertNull($dto->target);
        self::assertNull($dto->class);
        self::assertFalse($dto->isPopup);
        self::assertNull($dto->jsConfig);
    }

    public function testPropertiesAreReadonly(): void
    {
        $dto = new LinkDto(
            url: 'https://example.com',
            target: '_self',
            class: 'link',
            isPopup: false,
            jsConfig: null,
        );

        $reflection = new ReflectionClass($dto);

        $properties = ['url', 'target', 'class', 'isPopup', 'jsConfig'];

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
            isPopup: false,
            jsConfig: null,
        );

        self::assertSame('', $dto->url);
        self::assertSame('', $dto->target);
        self::assertSame('', $dto->class);
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
}
