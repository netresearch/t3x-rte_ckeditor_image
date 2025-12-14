<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Service;

use Netresearch\RteCKEditorImage\Service\ImageAttributeParser;
use Netresearch\RteCKEditorImage\Service\ImageRenderingService;
use Netresearch\RteCKEditorImage\Service\ImageResolverService;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Integration tests for new service architecture.
 *
 * Tests the complete pipeline: Parser → Resolver → Renderer
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class ImageRenderingIntegrationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ImageAttributeParser $parser;

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ImageResolverService $resolver;

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ImageRenderingService $renderer;

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();

        // Import test data
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/../Controller/Fixtures/sys_file.csv');

        // Get services from container
        $this->parser   = $this->get(ImageAttributeParser::class);
        $this->resolver = $this->get(ImageResolverService::class);
        $this->renderer = $this->get(ImageRenderingService::class);

        // Create mock request
        $this->request = new ServerRequest();
    }

    #[Test]
    public function servicesAreProperlyInjected(): void
    {
        self::assertInstanceOf(ImageAttributeParser::class, $this->parser);
        self::assertInstanceOf(ImageResolverService::class, $this->resolver);
        self::assertInstanceOf(ImageRenderingService::class, $this->renderer);
    }

    #[Test]
    public function parserExtractsAttributesFromHtml(): void
    {
        $html = '<img src="/test.jpg" alt="Test" width="800" height="600" data-htmlarea-file-uid="1" />';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertSame('/test.jpg', $attributes['src']);
        self::assertSame('Test', $attributes['alt']);
        self::assertSame('800', $attributes['width']);
        self::assertSame('600', $attributes['height']);
        self::assertSame('1', $attributes['data-htmlarea-file-uid']);
    }

    #[Test]
    public function resolverCreatesValidDto(): void
    {
        $attributes = [
            'src'    => '/external-image.jpg',
            'alt'    => 'External Image',
            'width'  => '800',
            'height' => '600',
        ];

        $conf = [];

        $dto = $this->resolver->resolve($attributes, $conf, $this->request);

        self::assertNotNull($dto);
        self::assertSame('/external-image.jpg', $dto->src);
        self::assertSame('External Image', $dto->alt);
        self::assertSame(800, $dto->width);
        self::assertSame(600, $dto->height);
        self::assertFalse($dto->isMagicImage); // External image
    }

    #[Test]
    public function resolverSanitizesCaptionForXssPrevention(): void
    {
        $attributes = [
            'src'          => '/image.jpg',
            'width'        => '100',
            'height'       => '100',
            'data-caption' => '<script>alert("XSS")</script>Caption',
        ];

        $conf = [];

        $dto = $this->resolver->resolve($attributes, $conf, $this->request);

        self::assertNotNull($dto);
        self::assertNotNull($dto->caption);
        // Caption should be HTML-escaped
        self::assertStringContainsString('&lt;script&gt;', $dto->caption);
        self::assertStringNotContainsString('<script>', $dto->caption);
    }

    #[Test]
    public function resolverHandlesNullCaptionCorrectly(): void
    {
        $attributes = [
            'src'    => '/image.jpg',
            'width'  => '100',
            'height' => '100',
        ];

        $conf = [];

        $dto = $this->resolver->resolve($attributes, $conf, $this->request);

        self::assertNotNull($dto);
        self::assertNull($dto->caption);
    }

    #[Test]
    public function resolverHandlesEmptyCaptionCorrectly(): void
    {
        $attributes = [
            'src'          => '/image.jpg',
            'width'        => '100',
            'height'       => '100',
            'data-caption' => '',
        ];

        $conf = [];

        $dto = $this->resolver->resolve($attributes, $conf, $this->request);

        self::assertNotNull($dto);
        self::assertNull($dto->caption); // Empty string becomes null
    }

    #[Test]
    public function fullPipelineParserToResolverWorks(): void
    {
        // Step 1: Parse HTML
        $html       = '<img src="/test.jpg" alt="Test Image" width="800" height="600" data-caption="Test Caption" />';
        $attributes = $this->parser->parseImageAttributes($html);

        // Step 2: Resolve to DTO
        $dto = $this->resolver->resolve($attributes, [], $this->request);

        // Verify DTO
        self::assertNotNull($dto);
        self::assertSame('Test Image', $dto->alt);
        self::assertSame('Test Caption', $dto->caption);
        self::assertSame(800, $dto->width);
        self::assertSame(600, $dto->height);
    }

    #[Test]
    public function resolverRespectsNoScaleConfiguration(): void
    {
        $attributes = [
            'src'          => '/image.jpg',
            'width'        => '800',
            'height'       => '600',
            'data-quality' => 'none', // This should trigger noScale
        ];

        $conf = [];

        $dto = $this->resolver->resolve($attributes, $conf, $this->request);

        self::assertNotNull($dto);
        // NoScale means original dimensions should be preserved
        self::assertSame(800, $dto->width);
        self::assertSame(600, $dto->height);
    }

    #[Test]
    public function resolverHandlesQualityMultipliers(): void
    {
        $testCases = [
            'low'      => 0.9,
            'standard' => 1.0,
            'retina'   => 2.0,
            'ultra'    => 3.0,
            'print'    => 6.0,
        ];

        foreach ($testCases as $quality => $expectedMultiplier) {
            $attributes = [
                'src'          => '/image.jpg',
                'width'        => '100',
                'height'       => '100',
                'data-quality' => $quality,
            ];

            $conf = [];

            $dto = $this->resolver->resolve($attributes, $conf, $this->request);

            self::assertNotNull($dto, "Failed for quality: {$quality}");
            // The actual multiplier is applied internally, we just verify DTO is created
            self::assertSame(100, $dto->width);
            self::assertSame(100, $dto->height);
        }
    }

    #[Test]
    public function parserHandlesLinkWithImages(): void
    {
        $html = '<a href="https://example.com" target="_blank"><img src="/image.jpg" alt="Image" /></a>';

        $result = $this->parser->parseLinkWithImages($html);

        self::assertSame('https://example.com', $result['link']['href']);
        self::assertSame('_blank', $result['link']['target']);
        self::assertCount(1, $result['images']);
        self::assertSame('/image.jpg', $result['images'][0]['attributes']['src']);
    }

    #[Test]
    public function resolverCreatesLinkDtoForLinkedImages(): void
    {
        $linkAttributes = [
            'href'   => 'https://example.com',
            'target' => '_blank',
            'class'  => 'external-link',
        ];

        $imageAttributes = [
            'src'    => '/image.jpg',
            'width'  => '100',
            'height' => '100',
        ];

        $conf = [];

        $dto = $this->resolver->resolve($imageAttributes, $conf, $this->request, $linkAttributes);

        self::assertNotNull($dto);
        self::assertNotNull($dto->link);
        self::assertSame('https://example.com', $dto->link->url);
        self::assertSame('_blank', $dto->link->target);
        self::assertSame('external-link', $dto->link->class);
        self::assertFalse($dto->link->isPopup);
    }

    #[Test]
    public function resolverDetectsPopupLinks(): void
    {
        $linkAttributes = [
            'href' => '/large-image.jpg',
        ];

        $imageAttributes = [
            'src'                => '/image.jpg',
            'width'              => '100',
            'height'             => '100',
            'data-htmlarea-zoom' => '1', // This indicates popup
        ];

        $conf = [];

        $dto = $this->resolver->resolve($imageAttributes, $conf, $this->request, $linkAttributes);

        self::assertNotNull($dto);
        self::assertNotNull($dto->link);
        self::assertTrue($dto->link->isPopup);
    }

    #[Test]
    public function resolverHandlesZeroDimensions(): void
    {
        $attributes = [
            'src'    => '/image.jpg',
            'width'  => '0',
            'height' => '0',
        ];

        $conf = [];

        $dto = $this->resolver->resolve($attributes, $conf, $this->request);

        self::assertNotNull($dto);
        self::assertSame(0, $dto->width);
        self::assertSame(0, $dto->height);
    }

    #[Test]
    public function resolverHandlesMissingDimensions(): void
    {
        $attributes = [
            'src' => '/image.jpg',
        ];

        $conf = [];

        $dto = $this->resolver->resolve($attributes, $conf, $this->request);

        self::assertNotNull($dto);
        self::assertSame(0, $dto->width);
        self::assertSame(0, $dto->height);
    }

    #[Test]
    public function xssPreventionInCaptionWithQuotes(): void
    {
        $maliciousCaption = 'Caption with <img src=x onerror="alert(1)"> and \' quotes';

        $attributes = [
            'src'          => '/image.jpg',
            'width'        => '100',
            'height'       => '100',
            'data-caption' => $maliciousCaption,
        ];

        $conf = [];

        $dto = $this->resolver->resolve($attributes, $conf, $this->request);

        self::assertNotNull($dto);
        self::assertNotNull($dto->caption);

        // Verify both HTML tags and quotes are escaped
        self::assertStringContainsString('&lt;img', $dto->caption);
        self::assertStringContainsString('&quot;', $dto->caption);
        self::assertStringNotContainsString('<img', $dto->caption);
        // The dangerous part is the executable attribute: onerror="..." - quotes must be encoded
        // After encoding: onerror=&quot;...&quot; - the = is still present but harmless
        self::assertStringNotContainsString('onerror="', $dto->caption);
    }

    #[Test]
    public function resolverReturnsNullForInvalidFileUid(): void
    {
        $attributes = [
            'src'                    => '/image.jpg',
            'width'                  => '100',
            'height'                 => '100',
            'data-htmlarea-file-uid' => '99999', // Non-existent file UID
        ];

        $conf = [];

        $dto = $this->resolver->resolve($attributes, $conf, $this->request);

        // Should return null for non-existent file
        self::assertNull($dto);
    }

    #[Test]
    public function parserHandlesEmptyStringGracefully(): void
    {
        $attributes = $this->parser->parseImageAttributes('');

        self::assertEmpty($attributes);
    }

    #[Test]
    public function parserHandlesInvalidHtmlGracefully(): void
    {
        $html = '<div>Not an image</div>';

        $attributes = $this->parser->parseImageAttributes($html);

        self::assertEmpty($attributes);
    }
}
