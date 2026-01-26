<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service\Fetcher;

use Exception;
use Netresearch\RteCKEditorImage\Service\Fetcher\ExternalImageFetcher;
use Netresearch\RteCKEditorImage\Service\Security\SecurityValidatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for ExternalImageFetcher.
 */
#[CoversClass(ExternalImageFetcher::class)]
final class ExternalImageFetcherTest extends UnitTestCase
{
    private ExternalImageFetcher $subject;

    /** @var SecurityValidatorInterface&MockObject */
    private MockObject $securityValidatorMock;

    /** @var RequestFactory&MockObject */
    private MockObject $requestFactoryMock;

    /** @var LoggerInterface&MockObject */
    private MockObject $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityValidatorMock = $this->createMock(SecurityValidatorInterface::class);
        $this->requestFactoryMock    = $this->createMock(RequestFactory::class);
        $this->loggerMock            = $this->createMock(LoggerInterface::class);

        $this->subject = new ExternalImageFetcher(
            $this->securityValidatorMock,
            $this->requestFactoryMock,
            $this->loggerMock,
        );
    }

    #[Test]
    public function fetchReturnsNullForEmptyUrl(): void
    {
        $result = $this->subject->fetch('');

        self::assertNull($result);
    }

    #[Test]
    public function fetchReturnsNullForWhitespaceUrl(): void
    {
        $result = $this->subject->fetch('   ');

        self::assertNull($result);
    }

    #[Test]
    public function fetchReturnsNullWhenSecurityValidationFails(): void
    {
        $url = 'https://example.com/image.jpg';

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->with($url)
            ->willReturn(null);

        $this->loggerMock
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('failed security validation'),
                self::arrayHasKey('url'),
            );

        $result = $this->subject->fetch($url);

        self::assertNull($result);
    }

    #[Test]
    public function fetchReturnsNullWhenMimeTypeValidationFails(): void
    {
        $url     = 'https://example.com/file.pdf';
        $content = 'PDF content that is not an image';

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('93.184.216.34');

        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($content);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);

        $this->requestFactoryMock
            ->method('request')
            ->willReturn($responseMock);

        $this->securityValidatorMock
            ->method('isAllowedImageMimeType')
            ->with($content)
            ->willReturn(false);

        $this->loggerMock
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('invalid MIME type'),
                self::arrayHasKey('url'),
            );

        $result = $this->subject->fetch($url);

        self::assertNull($result);
    }

    #[Test]
    public function fetchReturnsNullOnNonSuccessStatusCode(): void
    {
        $url = 'https://example.com/image.jpg';

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('93.184.216.34');

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(404);

        $this->requestFactoryMock
            ->method('request')
            ->willReturn($responseMock);

        $this->loggerMock
            ->expects(self::once())
            ->method('info')
            ->with(
                self::stringContains('non-success status'),
                self::callback(static fn (array $context): bool => $context['statusCode'] === 404),
            );

        $result = $this->subject->fetch($url);

        self::assertNull($result);
    }

    #[Test]
    public function fetchReturnsNullOnRequestException(): void
    {
        $url = 'https://example.com/image.jpg';

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('93.184.216.34');

        $this->requestFactoryMock
            ->method('request')
            ->willThrowException(new Exception('Connection failed'));

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed to fetch external image'),
                self::callback(static fn (array $context): bool => $context['exception'] === 'Connection failed'),
            );

        $result = $this->subject->fetch($url);

        self::assertNull($result);
    }

    #[Test]
    public function fetchReturnsContentOnSuccess(): void
    {
        $url     = 'https://example.com/image.jpg';
        $content = $this->getMinimalJpegContent();

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('93.184.216.34');

        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($content);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);

        $this->requestFactoryMock
            ->method('request')
            ->willReturn($responseMock);

        $this->securityValidatorMock
            ->method('isAllowedImageMimeType')
            ->with($content)
            ->willReturn(true);

        $result = $this->subject->fetch($url);

        self::assertSame($content, $result);
    }

    #[Test]
    public function isExternalUrlReturnsFalseForEmptyUrl(): void
    {
        self::assertFalse($this->subject->isExternalUrl(''));
    }

    #[Test]
    #[DataProvider('dataUriProvider')]
    public function isExternalUrlReturnsFalseForDataUri(string $dataUri): void
    {
        self::assertFalse($this->subject->isExternalUrl($dataUri), "Data URI should not be external: {$dataUri}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function dataUriProvider(): array
    {
        return [
            'data uri lowercase'  => ['data:image/png;base64,abc123'],
            'data uri uppercase'  => ['DATA:image/png;base64,abc123'],
            'data uri mixed case' => ['DaTa:image/gif;base64,xyz'],
        ];
    }

    #[Test]
    #[DataProvider('externalUrlProvider')]
    public function isExternalUrlReturnsTrueForHttpUrls(string $url): void
    {
        self::assertTrue($this->subject->isExternalUrl($url), "URL should be external: {$url}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function externalUrlProvider(): array
    {
        return [
            'http url'  => ['http://example.com/image.jpg'],
            'https url' => ['https://example.com/image.png'],
            'with port' => ['https://example.com:8080/image.gif'],
            'with path' => ['https://cdn.example.com/assets/images/photo.webp'],
        ];
    }

    #[Test]
    #[DataProvider('nonExternalUrlProvider')]
    public function isExternalUrlReturnsFalseForNonExternalUrls(string $url): void
    {
        self::assertFalse($this->subject->isExternalUrl($url), "URL should not be external: {$url}");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonExternalUrlProvider(): array
    {
        return [
            'relative path'     => ['/fileadmin/image.jpg'],
            'relative no slash' => ['fileadmin/image.jpg'],
            'protocol relative' => ['//example.com/image.jpg'],
            'ftp url'           => ['ftp://example.com/image.jpg'],
            'file url'          => ['file:///path/to/image.jpg'],
        ];
    }

    /**
     * Get minimal valid JPEG content for testing.
     */
    private function getMinimalJpegContent(): string
    {
        // Minimal valid JPEG file header
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xDB\x00C\x00\x08\x06\x06\x07\x06\x05\x08\x07\x07\x07\x09\x09\x08\x0A\x0C\x14\x0D\x0C\x0B\x0B\x0C\x19\x12\x13\x0F\x14\x1D\x1A\x1F\x1E\x1D\x1A\x1C\x1C $.' \",#\x1C\x1C(7),01444\x1F'9telecommu.<telecommu2telecommu\xFF\xD9";
    }

    #[Test]
    public function fetchReturnsNullWhenContentExceedsMaxSize(): void
    {
        $url = 'https://example.com/large-image.jpg';

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('93.184.216.34');

        // Create content larger than 10MB (the MAX_CONTENT_LENGTH)
        $largeContent = str_repeat('x', 10 * 1024 * 1024 + 1);

        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($largeContent);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);

        $this->requestFactoryMock
            ->method('request')
            ->willReturn($responseMock);

        $this->loggerMock
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('exceeds maximum size'),
                self::callback(static fn (array $context): bool => isset($context['size'])),
            );

        $result = $this->subject->fetch($url);

        self::assertNull($result);
    }

    #[Test]
    public function fetchReturnsNullForUrlWithInvalidParsedHost(): void
    {
        // A URL that passes security validation but fails parse_url host extraction
        // This is edge case - security validator returned IP but URL is malformed
        $url = 'https:///image.jpg'; // No host in URL

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('93.184.216.34');

        $result = $this->subject->fetch($url);

        self::assertNull($result);
    }

    #[Test]
    public function fetchCorrectlyHandlesIpv6ValidatedAddress(): void
    {
        $url     = 'https://ipv6host.example.com/image.jpg';
        $content = $this->getMinimalJpegContent();

        // Return an IPv6 address from validation
        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('2001:db8::1');

        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($content);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);

        // Verify the request is made with brackets around IPv6 address
        $this->requestFactoryMock
            ->expects(self::once())
            ->method('request')
            ->with(
                self::stringContains('[2001:db8::1]'),
                'GET',
                self::anything(),
            )
            ->willReturn($responseMock);

        $this->securityValidatorMock
            ->method('isAllowedImageMimeType')
            ->willReturn(true);

        $result = $this->subject->fetch($url);

        self::assertSame($content, $result);
    }

    #[Test]
    public function fetchHandlesUrlWithQueryString(): void
    {
        $url     = 'https://example.com/image.jpg?token=abc123&size=large';
        $content = $this->getMinimalJpegContent();

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('93.184.216.34');

        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($content);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);

        // Verify query string is preserved
        $this->requestFactoryMock
            ->expects(self::once())
            ->method('request')
            ->with(
                self::stringContains('?token=abc123&size=large'),
                'GET',
                self::anything(),
            )
            ->willReturn($responseMock);

        $this->securityValidatorMock
            ->method('isAllowedImageMimeType')
            ->willReturn(true);

        $result = $this->subject->fetch($url);

        self::assertSame($content, $result);
    }

    #[Test]
    public function fetchHandlesUrlWithExplicitPort(): void
    {
        $url     = 'https://example.com:8443/image.jpg';
        $content = $this->getMinimalJpegContent();

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('93.184.216.34');

        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($content);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);

        // Verify port is preserved in the request URL
        $this->requestFactoryMock
            ->expects(self::once())
            ->method('request')
            ->with(
                self::stringContains(':8443'),
                'GET',
                self::anything(),
            )
            ->willReturn($responseMock);

        $this->securityValidatorMock
            ->method('isAllowedImageMimeType')
            ->willReturn(true);

        $result = $this->subject->fetch($url);

        self::assertSame($content, $result);
    }

    #[Test]
    public function fetchPassesHostHeaderInRequest(): void
    {
        $url     = 'https://cdn.example.com/image.jpg';
        $content = $this->getMinimalJpegContent();

        $this->securityValidatorMock
            ->method('getValidatedIpForUrl')
            ->willReturn('93.184.216.34');

        $streamMock = $this->createMock(StreamInterface::class);
        $streamMock->method('getContents')->willReturn($content);

        $responseMock = $this->createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(200);
        $responseMock->method('getBody')->willReturn($streamMock);

        // Verify Host header is set to original hostname
        $this->requestFactoryMock
            ->expects(self::once())
            ->method('request')
            ->with(
                self::anything(),
                'GET',
                self::callback(static function (array $options): bool {
                    return isset($options['headers']['Host'])
                        && $options['headers']['Host'] === 'cdn.example.com';
                }),
            )
            ->willReturn($responseMock);

        $this->securityValidatorMock
            ->method('isAllowedImageMimeType')
            ->willReturn(true);

        $result = $this->subject->fetch($url);

        self::assertSame($content, $result);
    }

    #[Test]
    public function fetchReturnsNullForNon2xxStatusCodes(): void
    {
        $statusCodes = [100, 301, 302, 400, 401, 403, 500, 502, 503];

        foreach ($statusCodes as $statusCode) {
            $this->securityValidatorMock
                ->method('getValidatedIpForUrl')
                ->willReturn('93.184.216.34');

            $responseMock = $this->createMock(ResponseInterface::class);
            $responseMock->method('getStatusCode')->willReturn($statusCode);

            $this->requestFactoryMock
                ->method('request')
                ->willReturn($responseMock);

            $result = $this->subject->fetch('https://example.com/image.jpg');

            self::assertNull($result, "Status code {$statusCode} should return null");
        }
    }

    #[Test]
    public function isExternalUrlReturnsFalseForWhitespaceOnlyUrl(): void
    {
        self::assertFalse($this->subject->isExternalUrl('   '));
        self::assertFalse($this->subject->isExternalUrl("\t"));
        self::assertFalse($this->subject->isExternalUrl("\n"));
    }
}
