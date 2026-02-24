<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Domain\Model;

use Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto;
use Netresearch\RteCKEditorImage\Domain\Model\LinkDto;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test case for ImageRenderingDto.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
class ImageRenderingDtoTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $linkDto = new LinkDto(
            url: 'https://example.com',
            target: '_blank',
            class: 'link-class',
            params: null,
            isPopup: false,
            jsConfig: null,
        );

        $htmlAttributes = [
            'class'   => 'image-class',
            'loading' => 'lazy',
        ];

        $dto = new ImageRenderingDto(
            src: '/path/to/image.jpg',
            width: 800,
            height: 600,
            alt: 'Alt text',
            title: 'Title text',
            htmlAttributes: $htmlAttributes,
            caption: 'Caption text',
            link: $linkDto,
            isMagicImage: true,
        );

        self::assertSame('/path/to/image.jpg', $dto->src);
        self::assertSame(800, $dto->width);
        self::assertSame(600, $dto->height);
        self::assertSame('Alt text', $dto->alt);
        self::assertSame('Title text', $dto->title);
        self::assertSame($htmlAttributes, $dto->htmlAttributes);
        self::assertSame('Caption text', $dto->caption);
        self::assertSame($linkDto, $dto->link);
        self::assertTrue($dto->isMagicImage);
    }

    public function testConstructorAcceptsNullValues(): void
    {
        $dto = new ImageRenderingDto(
            src: '/path/to/image.jpg',
            width: 800,
            height: 600,
            alt: null,
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: false,
        );

        self::assertNull($dto->alt);
        self::assertNull($dto->title);
        self::assertEmpty($dto->htmlAttributes);
        self::assertNull($dto->caption);
        self::assertNull($dto->link);
        self::assertFalse($dto->isMagicImage);
    }

    public function testPropertiesAreReadonly(): void
    {
        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 100,
            height: 100,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: true,
        );

        // Attempting to modify readonly properties should cause a fatal error
        // We test this by checking the property reflection
        $reflection = new ReflectionClass($dto);

        $properties = [
            'src', 'width', 'height', 'alt', 'title',
            'htmlAttributes', 'caption', 'link', 'isMagicImage',
        ];

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
        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 100,
            height: 100,
            alt: 'Alt',
            title: 'Title',
            htmlAttributes: ['class' => 'test'],
            caption: 'Caption',
            link: null,
            isMagicImage: true,
        );

        $reflection = new ReflectionClass($dto);
        self::assertTrue(
            $reflection->isFinal(),
            'ImageRenderingDto should be final to prevent extension',
        );
    }

    public function testZeroDimensionsAllowed(): void
    {
        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 0,
            height: 0,
            alt: null,
            title: null,
            htmlAttributes: [],
            caption: null,
            link: null,
            isMagicImage: false,
        );

        self::assertSame(0, $dto->width);
        self::assertSame(0, $dto->height);
    }

    public function testEmptyStringValuesAccepted(): void
    {
        $dto = new ImageRenderingDto(
            src: '',
            width: 100,
            height: 100,
            alt: '',
            title: '',
            htmlAttributes: [],
            caption: '',
            link: null,
            isMagicImage: false,
        );

        self::assertSame('', $dto->src);
        self::assertSame('', $dto->alt);
        self::assertSame('', $dto->title);
        self::assertSame('', $dto->caption);
    }

    public function testHtmlAttributesCanContainMixedTypes(): void
    {
        $htmlAttributes = [
            'class'        => 'test-class',
            'data-id'      => 123,
            'data-enabled' => true,
            'style'        => 'color: red;',
        ];

        $dto = new ImageRenderingDto(
            src: '/image.jpg',
            width: 100,
            height: 100,
            alt: null,
            title: null,
            htmlAttributes: $htmlAttributes,
            caption: null,
            link: null,
            isMagicImage: false,
        );

        self::assertSame($htmlAttributes, $dto->htmlAttributes);
        self::assertSame('test-class', $dto->htmlAttributes['class']);
        self::assertSame(123, $dto->htmlAttributes['data-id']);
        // @phpstan-ignore staticMethod.alreadyNarrowedType (testing DTO correctly stores mixed-type attributes)
        self::assertSame(true, $dto->htmlAttributes['data-enabled']);
    }
}
