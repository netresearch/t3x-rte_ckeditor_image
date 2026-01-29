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
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
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
#[AllowMockObjectsWithoutExpectations]
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

    // ========================================================================
    // Caption Extraction Tests
    // ========================================================================

    #[Test]
    public function sanitizeCaptionReturnsEmptyStringForEmptyInput(): void
    {
        $result = $this->callPrivateMethod($this->service, 'sanitizeCaption', ['']);

        self::assertSame('', $result);
    }

    #[Test]
    public function sanitizeCaptionEncodesHtmlTags(): void
    {
        // sanitizeCaption uses htmlspecialchars to encode HTML for safe display
        $result = $this->callPrivateMethod($this->service, 'sanitizeCaption', ['<b>Bold</b> text']);

        self::assertSame('&lt;b&gt;Bold&lt;/b&gt; text', $result);
    }

    #[Test]
    public function sanitizeCaptionEncodesScriptTags(): void
    {
        // Script tags are encoded, preventing XSS execution
        $result = $this->callPrivateMethod($this->service, 'sanitizeCaption', ['Caption <script>alert(1)</script>']);

        self::assertSame('Caption &lt;script&gt;alert(1)&lt;/script&gt;', $result);
    }

    #[Test]
    public function sanitizeCaptionTrimsWhitespace(): void
    {
        $result = $this->callPrivateMethod($this->service, 'sanitizeCaption', ['   Caption text   ']);

        self::assertSame('Caption text', $result);
    }

    #[Test]
    public function sanitizeCaptionPreservesPlainText(): void
    {
        $caption = 'This is a plain text caption with no HTML';

        $result = $this->callPrivateMethod($this->service, 'sanitizeCaption', [$caption]);

        self::assertSame($caption, $result);
    }

    #[Test]
    public function sanitizeCaptionEncodesHtmlEntities(): void
    {
        // Already-encoded entities get double-encoded for safe display
        $result = $this->callPrivateMethod($this->service, 'sanitizeCaption', ['Caption &amp; More']);

        self::assertSame('Caption &amp;amp; More', $result);
    }

    #[Test]
    public function sanitizeCaptionEncodesSpecialCharacters(): void
    {
        $caption = 'Caption with "quotes" and <angle> brackets';

        $result = $this->callPrivateMethod($this->service, 'sanitizeCaption', [$caption]);

        // Quotes and angle brackets are encoded for safe display
        self::assertSame('Caption with &quot;quotes&quot; and &lt;angle&gt; brackets', $result);
    }

    /**
     * Data provider for caption sanitization edge cases.
     *
     * sanitizeCaption() uses htmlspecialchars() to encode HTML for safe display.
     *
     * @return array<string, array{input: string, expected: string}>
     */
    public static function captionSanitizationDataProvider(): array
    {
        return [
            'empty string' => [
                'input'    => '',
                'expected' => '',
            ],
            'whitespace only' => [
                'input'    => '   ',
                'expected' => '',
            ],
            'simple text' => [
                'input'    => 'Simple caption',
                'expected' => 'Simple caption',
            ],
            'text with newlines' => [
                'input'    => "Line 1\nLine 2",
                'expected' => "Line 1\nLine 2",
            ],
            'html paragraph encoded' => [
                'input'    => '<p>Paragraph caption</p>',
                'expected' => '&lt;p&gt;Paragraph caption&lt;/p&gt;',
            ],
            'nested html encoded' => [
                'input'    => '<div><p><strong>Nested</strong> text</p></div>',
                'expected' => '&lt;div&gt;&lt;p&gt;&lt;strong&gt;Nested&lt;/strong&gt; text&lt;/p&gt;&lt;/div&gt;',
            ],
            'html with attributes encoded' => [
                'input'    => '<span class="caption" style="color:red">Styled</span>',
                'expected' => '&lt;span class=&quot;caption&quot; style=&quot;color:red&quot;&gt;Styled&lt;/span&gt;',
            ],
            'xss attempt onclick encoded' => [
                'input'    => '<img src="x" onclick="alert(1)">Caption',
                'expected' => '&lt;img src=&quot;x&quot; onclick=&quot;alert(1)&quot;&gt;Caption',
            ],
            'xss attempt onerror encoded' => [
                'input'    => '<img src="x" onerror="alert(1)">Caption',
                'expected' => '&lt;img src=&quot;x&quot; onerror=&quot;alert(1)&quot;&gt;Caption',
            ],
            'unicode text preserved' => [
                'input'    => 'Ümläuts and émojis 🎉',
                'expected' => 'Ümläuts and émojis 🎉',
            ],
        ];
    }

    #[Test]
    #[DataProvider('captionSanitizationDataProvider')]
    public function sanitizeCaptionHandlesVariousInputs(string $input, string $expected): void
    {
        $result = $this->callPrivateMethod($this->service, 'sanitizeCaption', [$input]);

        self::assertSame($expected, $result);
    }

    // ========================================================================
    // URL Protocol Allowlist Tests
    // ========================================================================

    /**
     * Data provider for allowed link protocols.
     *
     * @return array<string, array{url: string}>
     */
    public static function allowedLinkProtocolsDataProvider(): array
    {
        return [
            'https url'              => ['url' => 'https://example.com/page'],
            'http url'               => ['url' => 'http://example.com/page'],
            'mailto link'            => ['url' => 'mailto:user@example.com'],
            'tel link'               => ['url' => 'tel:+1234567890'],
            't3 page link'           => ['url' => 't3://page?uid=123'],
            't3 file link'           => ['url' => 't3://file?uid=456'],
            'relative path'          => ['url' => '/path/to/page'],
            'relative path no slash' => ['url' => 'path/to/page'],
            'anchor link'            => ['url' => '#section'],
            'relative with anchor'   => ['url' => '/page#section'],
            'https with port'        => ['url' => 'https://example.com:8080/page'],
            'https with query'       => ['url' => 'https://example.com/page?foo=bar'],
            'path with colon'        => ['url' => 'path/to:file'],  // Colon after slash = not a protocol
            'path with colon deeper' => ['url' => 'some/path/file:name.txt'],
        ];
    }

    #[Test]
    #[DataProvider('allowedLinkProtocolsDataProvider')]
    public function validateLinkUrlAllowsValidProtocols(string $url): void
    {
        $result = $this->callPrivateMethod($this->service, 'validateLinkUrl', [$url]);

        self::assertTrue($result, "URL should be allowed: {$url}");
    }

    /**
     * Data provider for blocked link protocols.
     *
     * @return array<string, array{url: string}>
     */
    public static function blockedLinkProtocolsDataProvider(): array
    {
        return [
            'javascript protocol'   => ['url' => 'javascript:alert(1)'],
            'javascript uppercase'  => ['url' => 'JAVASCRIPT:alert(1)'],
            'javascript mixed case' => ['url' => 'JavaScript:alert(1)'],
            'vbscript protocol'     => ['url' => 'vbscript:msgbox(1)'],
            'data text html'        => ['url' => 'data:text/html,<script>alert(1)</script>'],
            'data application'      => ['url' => 'data:application/javascript,alert(1)'],
            'file protocol'         => ['url' => 'file:///etc/passwd'],
            'ftp protocol'          => ['url' => 'ftp://example.com/file'],
            'ftps protocol'         => ['url' => 'ftps://example.com/file'],
            'sftp protocol'         => ['url' => 'sftp://example.com/file'],
            'gopher protocol'       => ['url' => 'gopher://example.com/'],
            'ldap protocol'         => ['url' => 'ldap://example.com/'],
            'dict protocol'         => ['url' => 'dict://example.com/'],
            'ssh protocol'          => ['url' => 'ssh://user@host'],
            'telnet protocol'       => ['url' => 'telnet://example.com/'],
            'jar protocol'          => ['url' => 'jar:file:///path/to/file.jar!/'],
            'unknown protocol'      => ['url' => 'unknown://example.com/'],
            'custom protocol'       => ['url' => 'myapp://action'],
        ];
    }

    #[Test]
    #[DataProvider('blockedLinkProtocolsDataProvider')]
    public function validateLinkUrlBlocksDangerousProtocols(string $url): void
    {
        $result = $this->callPrivateMethod($this->service, 'validateLinkUrl', [$url]);

        self::assertFalse($result, "URL should be blocked: {$url}");
    }

    #[Test]
    public function validateLinkUrlBlocksEmptyUrl(): void
    {
        $result = $this->callPrivateMethod($this->service, 'validateLinkUrl', ['']);

        self::assertFalse($result);
    }

    #[Test]
    public function validateLinkUrlBlocksWhitespaceOnlyUrl(): void
    {
        $result = $this->callPrivateMethod($this->service, 'validateLinkUrl', ['   ']);

        self::assertFalse($result);
    }

    #[Test]
    public function validateLinkUrlHandlesUrlWithLeadingWhitespace(): void
    {
        // Leading whitespace with valid protocol - should be trimmed and allowed
        $result = $this->callPrivateMethod($this->service, 'validateLinkUrl', ['  https://example.com']);

        self::assertTrue($result);
    }

    #[Test]
    public function validateLinkUrlBlocksJavascriptWithWhitespace(): void
    {
        // Attempting to bypass with leading whitespace
        $result = $this->callPrivateMethod($this->service, 'validateLinkUrl', ['  javascript:alert(1)']);

        self::assertFalse($result);
    }

    // ========================================================================
    // parseIntAttribute Tests
    // ========================================================================

    #[Test]
    public function parseIntAttributeReturnsIntegerForNumericString(): void
    {
        $result = $this->callPrivateMethod($this->service, 'parseIntAttribute', ['123']);

        self::assertSame(123, $result);
    }

    #[Test]
    public function parseIntAttributeReturnsIntegerForNegativeNumericString(): void
    {
        $result = $this->callPrivateMethod($this->service, 'parseIntAttribute', ['-50']);

        self::assertSame(-50, $result);
    }

    #[Test]
    public function parseIntAttributeReturnsZeroForNonNumericString(): void
    {
        $result = $this->callPrivateMethod($this->service, 'parseIntAttribute', ['abc']);

        self::assertSame(0, $result);
    }

    #[Test]
    public function parseIntAttributeReturnsFallbackForNonNumericString(): void
    {
        $result = $this->callPrivateMethod($this->service, 'parseIntAttribute', ['abc', 100]);

        self::assertSame(100, $result);
    }

    #[Test]
    public function parseIntAttributeReturnsZeroForEmptyString(): void
    {
        $result = $this->callPrivateMethod($this->service, 'parseIntAttribute', ['']);

        self::assertSame(0, $result);
    }

    #[Test]
    public function parseIntAttributeReturnsFallbackForEmptyString(): void
    {
        $result = $this->callPrivateMethod($this->service, 'parseIntAttribute', ['', 200]);

        self::assertSame(200, $result);
    }

    #[Test]
    public function parseIntAttributeIgnoresNonNumericFallback(): void
    {
        $result = $this->callPrivateMethod($this->service, 'parseIntAttribute', ['abc', 'xyz']);

        self::assertSame(0, $result);
    }

    #[Test]
    public function parseIntAttributeHandlesFloatString(): void
    {
        $result = $this->callPrivateMethod($this->service, 'parseIntAttribute', ['123.45']);

        self::assertSame(123, $result);
    }

    // ========================================================================
    // isPopupAttributeSet Tests
    // ========================================================================

    #[Test]
    public function isPopupAttributeSetReturnsTrueForDataHtmlareaZoom(): void
    {
        $attributes = ['data-htmlarea-zoom' => '1'];

        $result = $this->callPrivateMethod($this->service, 'isPopupAttributeSet', [$attributes]);

        self::assertTrue($result);
    }

    #[Test]
    public function isPopupAttributeSetReturnsTrueForDataHtmlareaClickenlarge(): void
    {
        $attributes = ['data-htmlarea-clickenlarge' => '1'];

        $result = $this->callPrivateMethod($this->service, 'isPopupAttributeSet', [$attributes]);

        self::assertTrue($result);
    }

    #[Test]
    public function isPopupAttributeSetReturnsFalseForEmptyAttributes(): void
    {
        $attributes = [];

        $result = $this->callPrivateMethod($this->service, 'isPopupAttributeSet', [$attributes]);

        self::assertFalse($result);
    }

    #[Test]
    public function isPopupAttributeSetReturnsFalseForOtherAttributes(): void
    {
        $attributes = ['src' => 'image.jpg', 'alt' => 'Alt text', 'width' => '100'];

        $result = $this->callPrivateMethod($this->service, 'isPopupAttributeSet', [$attributes]);

        self::assertFalse($result);
    }

    #[Test]
    public function isPopupAttributeSetReturnsTrueWhenBothZoomAttributesPresent(): void
    {
        $attributes = [
            'data-htmlarea-zoom'         => '1',
            'data-htmlarea-clickenlarge' => '1',
        ];

        $result = $this->callPrivateMethod($this->service, 'isPopupAttributeSet', [$attributes]);

        self::assertTrue($result);
    }

    // ========================================================================
    // getPopupLinkClass Tests (#562)
    // ========================================================================

    #[Test]
    public function getPopupLinkClassReturnsDefaultWhenConfigIsNull(): void
    {
        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [null]);

        self::assertSame('popup-link', $result);
    }

    #[Test]
    public function getPopupLinkClassReturnsDefaultWhenConfigIsEmpty(): void
    {
        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [[]]);

        self::assertSame('popup-link', $result);
    }

    #[Test]
    public function getPopupLinkClassReturnsConfiguredLinkClass(): void
    {
        $config = ['linkClass' => 'my-lightbox'];

        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [$config]);

        self::assertSame('my-lightbox', $result);
    }

    #[Test]
    public function getPopupLinkClassTrimsWhitespaceFromLinkClass(): void
    {
        $config = ['linkClass' => '  custom-popup  '];

        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [$config]);

        self::assertSame('custom-popup', $result);
    }

    #[Test]
    public function getPopupLinkClassReturnsDefaultForEmptyLinkClass(): void
    {
        $config = ['linkClass' => '   '];

        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [$config]);

        self::assertSame('popup-link', $result);
    }

    #[Test]
    public function getPopupLinkClassExtractsClassFromATagParams(): void
    {
        $config = [
            'linkParams.' => [
                'ATagParams' => 'class="lightbox-gallery" data-lightbox="main"',
            ],
        ];

        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [$config]);

        self::assertSame('lightbox-gallery', $result);
    }

    #[Test]
    public function getPopupLinkClassExtractsClassFromATagParamsWithSingleQuotes(): void
    {
        $config = [
            'linkParams.' => [
                'ATagParams' => "class='fancybox' rel='gallery'",
            ],
        ];

        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [$config]);

        self::assertSame('fancybox', $result);
    }

    #[Test]
    public function getPopupLinkClassPrefersLinkClassOverATagParams(): void
    {
        $config = [
            'linkClass'   => 'preferred-class',
            'linkParams.' => [
                'ATagParams' => 'class="atag-class"',
            ],
        ];

        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [$config]);

        self::assertSame('preferred-class', $result);
    }

    #[Test]
    public function getPopupLinkClassReturnsDefaultWhenATagParamsHasNoClass(): void
    {
        $config = [
            'linkParams.' => [
                'ATagParams' => 'data-lightbox="gallery" rel="lightbox"',
            ],
        ];

        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [$config]);

        self::assertSame('popup-link', $result);
    }

    #[Test]
    public function getPopupLinkClassTrimsWhitespaceFromATagParams(): void
    {
        $config = [
            'linkParams.' => [
                'ATagParams' => 'class="  spaced-class  " rel="gallery"',
            ],
        ];

        $result = $this->callPrivateMethod($this->service, 'getPopupLinkClass', [$config]);

        self::assertSame('spaced-class', $result);
    }

    // ========================================================================
    // getNestedTypoScriptValue Tests
    // ========================================================================

    #[Test]
    public function getNestedTypoScriptValueReturnsValueForSimpleKey(): void
    {
        $array = ['key' => 'value'];
        $keys  = ['key'];

        $result = $this->callPrivateMethod($this->service, 'getNestedTypoScriptValue', [$array, $keys]);

        self::assertSame('value', $result);
    }

    #[Test]
    public function getNestedTypoScriptValueReturnsValueForNestedKeys(): void
    {
        $array = ['level1' => ['level2' => ['level3' => 'deep value']]];
        $keys  = ['level1', 'level2', 'level3'];

        $result = $this->callPrivateMethod($this->service, 'getNestedTypoScriptValue', [$array, $keys]);

        self::assertSame('deep value', $result);
    }

    #[Test]
    public function getNestedTypoScriptValueReturnsNullForMissingKey(): void
    {
        $array = ['key' => 'value'];
        $keys  = ['nonexistent'];

        $result = $this->callPrivateMethod($this->service, 'getNestedTypoScriptValue', [$array, $keys]);

        self::assertNull($result);
    }

    #[Test]
    public function getNestedTypoScriptValueReturnsNullForPartialPath(): void
    {
        $array = ['level1' => ['level2' => 'value']];
        $keys  = ['level1', 'level2', 'level3']; // level3 doesn't exist

        $result = $this->callPrivateMethod($this->service, 'getNestedTypoScriptValue', [$array, $keys]);

        self::assertNull($result);
    }

    #[Test]
    public function getNestedTypoScriptValueReturnsNullForEmptyArray(): void
    {
        $array = [];
        $keys  = ['key'];

        $result = $this->callPrivateMethod($this->service, 'getNestedTypoScriptValue', [$array, $keys]);

        self::assertNull($result);
    }

    #[Test]
    public function getNestedTypoScriptValueReturnsArrayForPartialPath(): void
    {
        $array = ['level1' => ['level2' => ['a' => 1, 'b' => 2]]];
        $keys  = ['level1', 'level2'];

        $result = $this->callPrivateMethod($this->service, 'getNestedTypoScriptValue', [$array, $keys]);

        self::assertSame(['a' => 1, 'b' => 2], $result);
    }

    #[Test]
    public function getNestedTypoScriptValueHandlesEmptyKeysArray(): void
    {
        $array = ['key' => 'value'];
        $keys  = [];

        $result = $this->callPrivateMethod($this->service, 'getNestedTypoScriptValue', [$array, $keys]);

        // With empty keys, should return the original array
        self::assertSame(['key' => 'value'], $result);
    }

    // ========================================================================
    // Quality Multiplier Tests
    // ========================================================================

    #[Test]
    public function getQualityMultiplierReturnsOneForStandard(): void
    {
        $result = $this->callPrivateMethod($this->service, 'getQualityMultiplier', ['standard']);

        self::assertSame(1.0, $result);
    }

    #[Test]
    public function getQualityMultiplierReturnsTwoForRetina(): void
    {
        $result = $this->callPrivateMethod($this->service, 'getQualityMultiplier', ['retina']);

        self::assertSame(2.0, $result);
    }

    #[Test]
    public function getQualityMultiplierReturnsThreeForUltra(): void
    {
        $result = $this->callPrivateMethod($this->service, 'getQualityMultiplier', ['ultra']);

        self::assertSame(3.0, $result);
    }

    #[Test]
    public function getQualityMultiplierReturnsSixForPrint(): void
    {
        $result = $this->callPrivateMethod($this->service, 'getQualityMultiplier', ['print']);

        self::assertSame(6.0, $result);
    }

    #[Test]
    public function getQualityMultiplierReturnsPointNineForLow(): void
    {
        $result = $this->callPrivateMethod($this->service, 'getQualityMultiplier', ['low']);

        self::assertSame(0.9, $result);
    }

    #[Test]
    public function getQualityMultiplierReturnsOneForUnknown(): void
    {
        $result = $this->callPrivateMethod($this->service, 'getQualityMultiplier', ['unknown']);

        self::assertSame(1.0, $result);
    }

    #[Test]
    public function getQualityMultiplierReturnsOneForEmptyString(): void
    {
        $result = $this->callPrivateMethod($this->service, 'getQualityMultiplier', ['']);

        self::assertSame(1.0, $result);
    }
}
