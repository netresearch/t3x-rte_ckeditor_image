<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service;

use Netresearch\RteCKEditorImage\Service\ImageResolverService;
use Netresearch\RteCKEditorImage\Utils\ProcessedFilesHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Security\SvgSanitizer;

/**
 * Test case for ImageResolverService.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class ImageResolverServiceTest extends TestCase
{
    private ImageResolverService $service;

    /** @var ResourceFactory&\PHPUnit\Framework\MockObject\MockObject */
    private ResourceFactory $resourceFactoryMock;

    /** @var ProcessedFilesHandler&\PHPUnit\Framework\MockObject\MockObject */
    private ProcessedFilesHandler $processedFilesHandlerMock;

    /** @var SvgSanitizer&\PHPUnit\Framework\MockObject\MockObject */
    private SvgSanitizer $svgSanitizerMock;

    /** @var LogManager&\PHPUnit\Framework\MockObject\MockObject */
    private LogManager $logManagerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourceFactoryMock       = $this->createMock(ResourceFactory::class);
        $this->processedFilesHandlerMock = $this->createMock(ProcessedFilesHandler::class);
        $this->svgSanitizerMock          = $this->createMock(SvgSanitizer::class);
        $this->logManagerMock            = $this->createMock(LogManager::class);

        // Mock logger to prevent null reference
        $loggerMock = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->logManagerMock->method('getLogger')->willReturn($loggerMock);

        // Default: SvgSanitizer returns content unchanged (pass-through)
        $this->svgSanitizerMock->method('sanitizeContent')
            ->willReturnCallback(static fn (string $content): string => $content);

        $this->service = new ImageResolverService(
            $this->resourceFactoryMock,
            $this->processedFilesHandlerMock,
            $this->svgSanitizerMock,
            $this->logManagerMock,
        );
    }

    /**
     * Helper method to call private methods for testing.
     *
     * @param object  $object     Object instance
     * @param string  $methodName Method name to call
     * @param mixed[] $parameters Parameters to pass
     *
     * @return mixed
     */
    protected function callPrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionMethod($object::class, $methodName);

        return $reflection->invokeArgs($object, $parameters);
    }

    /**
     * Create a mock File object with specified properties.
     *
     * @param array<string, mixed> $properties File properties
     *
     * @return File&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createFileMock(array $properties = []): File
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')
            ->willReturnCallback(static function (string $key) use ($properties): mixed {
                return $properties[$key] ?? null;
            });

        return $fileMock;
    }

    /**
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/502
     */
    #[Test]
    public function getAttributeValueReturnsEmptyStringWhenOverrideIsTrue(): void
    {
        // Regression test for #502: data-alt-override="true" with alt="" must return ""
        // Previously returned literal "true" instead of empty string
        $fileMock = $this->createFileMock(['alt' => 'File Alt Text']);

        $attributes = [
            'alt'               => '',
            'data-alt-override' => 'true',
        ];

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', ['alt', $attributes, $fileMock]);

        // The override flag "true" means "use the alt attribute as-is, don't fall back to file metadata"
        self::assertSame('', $result, 'When data-alt-override="true" and alt="", the result should be empty string');
    }

    /**
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/502
     */
    #[Test]
    public function getAttributeValueReturnsEmptyStringForTitleWhenOverrideIsTrue(): void
    {
        // Regression test for #502: same fix applies to title attribute
        $fileMock = $this->createFileMock(['title' => 'File Title Text']);

        $attributes = [
            'title'               => '',
            'data-title-override' => 'true',
        ];

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', ['title', $attributes, $fileMock]);

        self::assertSame('', $result, 'When data-title-override="true" and title="", the result should be empty string');
    }

    /**
     * Data provider for override attribute tests.
     *
     * @return array<string, array{attribute: string, attributes: array<string, string>, fileProperty: string, expected: string|null}>
     */
    public static function overrideAttributeDataProvider(): array
    {
        return [
            'alt override true with empty alt' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => '', 'data-alt-override' => 'true'],
                'fileProperty' => 'File Alt',
                'expected'     => '',
            ],
            'alt override true with explicit alt value' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => 'Explicit Alt', 'data-alt-override' => 'true'],
                'fileProperty' => 'File Alt',
                'expected'     => 'Explicit Alt',
            ],
            'alt override with custom value' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => '', 'data-alt-override' => 'Custom Override Alt'],
                'fileProperty' => 'File Alt',
                'expected'     => 'Custom Override Alt',
            ],
            'title override true with empty title' => [
                'attribute'    => 'title',
                'attributes'   => ['title' => '', 'data-title-override' => 'true'],
                'fileProperty' => 'File Title',
                'expected'     => '',
            ],
            'title override true with explicit title value' => [
                'attribute'    => 'title',
                'attributes'   => ['title' => 'Explicit Title', 'data-title-override' => 'true'],
                'fileProperty' => 'File Title',
                'expected'     => 'Explicit Title',
            ],
            'title override with custom value' => [
                'attribute'    => 'title',
                'attributes'   => ['title' => '', 'data-title-override' => 'Custom Override Title'],
                'fileProperty' => 'File Title',
                'expected'     => 'Custom Override Title',
            ],
            'no override falls back to attribute' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => 'My Alt'],
                'fileProperty' => 'File Alt',
                'expected'     => 'My Alt',
            ],
            'no override and no attribute falls back to file property' => [
                'attribute'    => 'alt',
                'attributes'   => [],
                'fileProperty' => 'File Alt',
                'expected'     => 'File Alt',
            ],
            'no override and empty attribute falls back to file property' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => ''],
                'fileProperty' => 'File Alt',
                'expected'     => 'File Alt',
            ],
        ];
    }

    /**
     * @param array<string, string> $attributes
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/502
     */
    #[Test]
    #[DataProvider('overrideAttributeDataProvider')]
    public function getAttributeValueHandlesOverrideCorrectly(
        string $attribute,
        array $attributes,
        string $fileProperty,
        ?string $expected,
    ): void {
        $fileMock = $this->createFileMock([$attribute => $fileProperty]);

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', [$attribute, $attributes, $fileMock]);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function getAttributeValueReturnsNullForEmptyAttributeName(): void
    {
        $fileMock = $this->createFileMock([]);

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', ['', [], $fileMock]);

        self::assertNull($result);
    }

    #[Test]
    public function getAttributeValueReturnsNullWhenNoValueAvailable(): void
    {
        $fileMock = $this->createFileMock([]); // No file properties

        $attributes = []; // No attributes

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', ['alt', $attributes, $fileMock]);

        self::assertNull($result);
    }

    // ========================================================================
    // SVG Data URI Sanitization Tests
    // ========================================================================

    /**
     * Helper to create a service instance with custom SvgSanitizer mock behavior.
     *
     * @param callable(string): string $sanitizerCallback Callback for sanitizeContent
     */
    private function createServiceWithSanitizer(callable $sanitizerCallback): ImageResolverService
    {
        $svgSanitizerMock = $this->createMock(SvgSanitizer::class);
        $svgSanitizerMock->method('sanitizeContent')
            ->willReturnCallback($sanitizerCallback);

        return new ImageResolverService(
            $this->resourceFactoryMock,
            $this->processedFilesHandlerMock,
            $svgSanitizerMock,
            $this->logManagerMock,
        );
    }

    #[Test]
    public function sanitizeSvgDataUriPassesThroughNonSvgDataUri(): void
    {
        // PNG data URI should pass through unchanged
        $pngDataUri = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $result = $this->callPrivateMethod($this->service, 'sanitizeSvgDataUri', [$pngDataUri]);

        self::assertSame($pngDataUri, $result);
    }

    #[Test]
    public function sanitizeSvgDataUriPassesThroughRegularHttpsUrl(): void
    {
        $httpsUrl = 'https://example.com/image.svg';

        $result = $this->callPrivateMethod($this->service, 'sanitizeSvgDataUri', [$httpsUrl]);

        self::assertSame($httpsUrl, $result);
    }

    #[Test]
    public function sanitizeSvgDataUriHandlesCaseInsensitiveMimeType(): void
    {
        // Mixed case should still be recognized as SVG
        $mixedCaseUri = 'DATA:IMAGE/SVG+XML;base64,' . base64_encode('<svg></svg>');

        $result = $this->callPrivateMethod($this->service, 'sanitizeSvgDataUri', [$mixedCaseUri]);
        self::assertIsString($result);

        // Should be processed - original prefix is preserved (including case)
        self::assertStringStartsWith('DATA:IMAGE/SVG+XML;base64,', $result);
    }

    #[Test]
    public function sanitizeSvgDataUriHandlesMixedCaseBase64Marker(): void
    {
        // SECURITY: Mixed case ;BASE64, must still be sanitized (was a bypass vector)
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $cleanSvg     = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        $service = $this->createServiceWithSanitizer(
            static fn (string $content): string => str_contains($content, '<script>')
                ? $cleanSvg
                : $content,
        );

        // Use uppercase BASE64 marker - this must NOT bypass sanitization
        $maliciousUri = 'data:image/svg+xml;BASE64,' . base64_encode($maliciousSvg);

        $result = $this->callPrivateMethod($service, 'sanitizeSvgDataUri', [$maliciousUri]);
        self::assertIsString($result);

        // Verify script was removed (not bypassed)
        self::assertStringContainsString(';BASE64,', $result); // Original marker preserved
        $markerPos = strpos($result, ';BASE64,');
        self::assertIsInt($markerPos);
        $base64Part    = substr($result, $markerPos + 8);
        $decodedResult = base64_decode($base64Part, true);

        self::assertSame($cleanSvg, $decodedResult, 'Mixed-case BASE64 marker must not bypass sanitization');
    }

    #[Test]
    public function sanitizeSvgDataUriPreservesCharsetParameterWithBase64(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        // Data URI with charset parameter before base64
        $dataUri = 'data:image/svg+xml;charset=utf-8;base64,' . base64_encode($svgContent);

        $result = $this->callPrivateMethod($this->service, 'sanitizeSvgDataUri', [$dataUri]);
        self::assertIsString($result);

        // Verify charset parameter is preserved
        self::assertStringStartsWith('data:image/svg+xml;charset=utf-8;base64,', $result);

        // Verify content is still valid
        $markerPos = strpos($result, ';base64,');
        self::assertIsInt($markerPos);
        $base64Part    = substr($result, $markerPos + 8);
        $decodedResult = base64_decode($base64Part, true);

        self::assertSame($svgContent, $decodedResult);
    }

    #[Test]
    public function sanitizeSvgDataUriSanitizesBase64EncodedSvgWithScript(): void
    {
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $cleanSvg     = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        // Configure sanitizer to strip <script> tags
        $service = $this->createServiceWithSanitizer(
            static fn (string $content): string => str_contains($content, '<script>')
                ? $cleanSvg
                : $content,
        );

        $maliciousUri = 'data:image/svg+xml;base64,' . base64_encode($maliciousSvg);

        $result = $this->callPrivateMethod($service, 'sanitizeSvgDataUri', [$maliciousUri]);
        self::assertIsString($result);

        // Decode result and verify script was removed
        $resultParts   = explode(';base64,', $result, 2);
        $decodedResult = base64_decode($resultParts[1], true);

        self::assertSame($cleanSvg, $decodedResult);
    }

    #[Test]
    public function sanitizeSvgDataUriSanitizesRawEncodedSvgWithEventHandler(): void
    {
        $maliciousSvg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>';
        $cleanSvg     = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        // Configure sanitizer to strip event handlers
        $service = $this->createServiceWithSanitizer(
            static fn (string $content): string => str_contains($content, 'onload')
                ? $cleanSvg
                : $content,
        );

        $maliciousUri = 'data:image/svg+xml,' . rawurlencode($maliciousSvg);

        $result = $this->callPrivateMethod($service, 'sanitizeSvgDataUri', [$maliciousUri]);
        self::assertIsString($result);

        // Decode result and verify event handler was removed
        $commaPos = strpos($result, ',');
        self::assertIsInt($commaPos);
        $decodedResult = rawurldecode(substr($result, $commaPos + 1));

        self::assertSame($cleanSvg, $decodedResult);
    }

    #[Test]
    public function sanitizeSvgDataUriHandlesInvalidBase64Gracefully(): void
    {
        // Invalid base64 content (not valid base64 string)
        $invalidUri = 'data:image/svg+xml;base64,!!not_valid_base64!!';

        $result = $this->callPrivateMethod($this->service, 'sanitizeSvgDataUri', [$invalidUri]);

        // Should return original when base64 decode fails
        self::assertSame($invalidUri, $result);
    }

    #[Test]
    public function sanitizeSvgDataUriHandlesMalformedBase64UriGracefully(): void
    {
        // Malformed: missing actual base64 content after marker
        $malformedUri = 'data:image/svg+xml;base64,';

        $result = $this->callPrivateMethod($this->service, 'sanitizeSvgDataUri', [$malformedUri]);

        // Empty base64 data should return original (graceful degradation)
        self::assertSame($malformedUri, $result);
    }

    #[Test]
    public function sanitizeSvgDataUriHandlesMalformedRawUriGracefully(): void
    {
        // Malformed: no comma separator
        $malformedUri = 'data:image/svg+xml%3Csvg%3E%3C/svg%3E';

        $result = $this->callPrivateMethod($this->service, 'sanitizeSvgDataUri', [$malformedUri]);

        // Should return original when format is invalid
        self::assertSame($malformedUri, $result);
    }

    #[Test]
    public function sanitizeSvgDataUriPreservesCleanSvgUnchanged(): void
    {
        $cleanSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="red"/></svg>';

        $originalUri = 'data:image/svg+xml;base64,' . base64_encode($cleanSvg);

        $result = $this->callPrivateMethod($this->service, 'sanitizeSvgDataUri', [$originalUri]);
        self::assertIsString($result);

        // Decode and verify content is unchanged
        $resultParts   = explode(';base64,', $result, 2);
        $decodedResult = base64_decode($resultParts[1], true);

        self::assertSame($cleanSvg, $decodedResult);
    }

    /**
     * Data provider for SVG XSS payload tests.
     *
     * @return array<string, array{maliciousContent: string, description: string}>
     */
    public static function svgXssPayloadDataProvider(): array
    {
        return [
            'script tag' => [
                'maliciousContent' => '<script>alert(1)</script>',
                'description'      => 'Inline script execution',
            ],
            'onload event handler' => [
                'maliciousContent' => '<svg onload="alert(1)">',
                'description'      => 'Event handler XSS',
            ],
            'onerror event handler' => [
                'maliciousContent' => '<image onerror="alert(1)">',
                'description'      => 'Image error handler XSS',
            ],
            'onclick event handler' => [
                'maliciousContent' => '<rect onclick="alert(1)">',
                'description'      => 'Click handler XSS',
            ],
            'javascript href' => [
                'maliciousContent' => '<a href="javascript:alert(1)">',
                'description'      => 'JavaScript protocol in href',
            ],
            'foreignObject with script' => [
                'maliciousContent' => '<foreignObject><script>alert(1)</script></foreignObject>',
                'description'      => 'ForeignObject script injection',
            ],
            'set element with onbegin' => [
                'maliciousContent' => '<set onbegin="alert(1)">',
                'description'      => 'Animation event handler',
            ],
        ];
    }

    /**
     * Verify that various XSS payloads are passed to the sanitizer.
     *
     * This test verifies that the sanitizeSvgDataUri method correctly extracts
     * and passes SVG content to the sanitizer. The actual XSS prevention is
     * handled by TYPO3's SvgSanitizer.
     */
    #[Test]
    #[DataProvider('svgXssPayloadDataProvider')]
    public function sanitizeSvgDataUriPassesContentToSanitizer(string $maliciousContent, string $description): void
    {
        $maliciousSvg   = '<svg xmlns="http://www.w3.org/2000/svg">' . $maliciousContent . '</svg>';
        $sanitizedSvg   = '<svg xmlns="http://www.w3.org/2000/svg"><!-- sanitized --></svg>';
        $sanitizerCalls = [];

        // Track what gets passed to the sanitizer
        $service = $this->createServiceWithSanitizer(
            static function (string $content) use (&$sanitizerCalls, $sanitizedSvg): string {
                $sanitizerCalls[] = $content;

                return $sanitizedSvg;
            },
        );

        $maliciousUri = 'data:image/svg+xml;base64,' . base64_encode($maliciousSvg);

        $result = $this->callPrivateMethod($service, 'sanitizeSvgDataUri', [$maliciousUri]);
        self::assertIsString($result);

        // Verify sanitizer was called with the malicious content
        self::assertCount(1, $sanitizerCalls, 'Sanitizer should be called exactly once');
        self::assertSame($maliciousSvg, $sanitizerCalls[0], 'Sanitizer should receive the decoded SVG content');

        // Verify result contains sanitized content
        $resultParts   = explode(';base64,', $result, 2);
        $decodedResult = base64_decode($resultParts[1], true);
        self::assertSame($sanitizedSvg, $decodedResult, sprintf('Content should be sanitized (%s)', $description));
    }

    #[Test]
    public function sanitizeSvgDataUriHandlesRawFormatWithCharset(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        // Data URI with charset parameter
        $dataUri = 'data:image/svg+xml;charset=utf-8,' . rawurlencode($svgContent);

        $result = $this->callPrivateMethod($this->service, 'sanitizeSvgDataUri', [$dataUri]);
        self::assertIsString($result);

        // Should handle the charset parameter correctly
        $commaPos = strpos($result, ',');
        self::assertIsInt($commaPos);
        $decodedResult = rawurldecode(substr($result, $commaPos + 1));

        self::assertSame($svgContent, $decodedResult);
    }
}
