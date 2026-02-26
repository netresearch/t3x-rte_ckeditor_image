<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service\Security;

use Netresearch\RteCKEditorImage\Service\Security\SecurityValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for SecurityValidator.
 */
#[CoversClass(SecurityValidator::class)]
final class SecurityValidatorTest extends UnitTestCase
{
    private SecurityValidator $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new SecurityValidator();
    }

    #[Test]
    public function getValidatedIpForUrlBlocksPrivateIpv4Ranges(): void
    {
        $privateUrls = [
            'http://10.0.0.1/image.jpg',       // 10.0.0.0/8
            'http://172.16.0.1/image.jpg',     // 172.16.0.0/12
            'http://192.168.1.1/image.jpg',    // 192.168.0.0/16
            'http://127.0.0.1/image.jpg',      // Loopback
            'http://169.254.1.1/image.jpg',    // Link-local
        ];

        foreach ($privateUrls as $url) {
            $result = $this->subject->getValidatedIpForUrl($url);
            self::assertNull($result, "Expected {$url} to be blocked");
        }
    }

    #[Test]
    public function getValidatedIpForUrlBlocksCloudMetadataEndpoints(): void
    {
        $metadataUrls = [
            'http://169.254.169.254/latest/meta-data',
            'http://metadata.google.internal/computeMetadata/v1/',
        ];

        foreach ($metadataUrls as $url) {
            $result = $this->subject->getValidatedIpForUrl($url);
            self::assertNull($result, "Expected {$url} to be blocked");
        }
    }

    #[Test]
    public function getValidatedIpForUrlReturnsNullForInvalidUrl(): void
    {
        $result = $this->subject->getValidatedIpForUrl('not-a-valid-url');
        self::assertNull($result);
    }

    #[Test]
    public function getValidatedIpForUrlReturnsNullForUrlWithoutHost(): void
    {
        $result = $this->subject->getValidatedIpForUrl('/relative/path/image.jpg');
        self::assertNull($result);
    }

    #[Test]
    public function isAllowedImageMimeTypeAcceptsJpegImages(): void
    {
        // Create minimal valid JPEG data (JPEG magic bytes: FF D8 FF)
        $jpegData = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

        $result = $this->subject->isAllowedImageMimeType($jpegData);
        self::assertTrue($result);
    }

    #[Test]
    public function isAllowedImageMimeTypeAcceptsPngImages(): void
    {
        // Create minimal valid PNG data (PNG magic bytes)
        $pngData = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x00\x00\x00\x00:~\x9bU\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

        $result = $this->subject->isAllowedImageMimeType($pngData);
        self::assertTrue($result);
    }

    #[Test]
    public function isAllowedImageMimeTypeRejectsSvgImages(): void
    {
        $svgData = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100"/></svg>';

        $result = $this->subject->isAllowedImageMimeType($svgData);
        self::assertFalse($result, 'SVG should be rejected per ADR-003');
    }

    #[Test]
    public function isAllowedImageMimeTypeRejectsHtmlContent(): void
    {
        $htmlData = '<html><body><script>alert("xss")</script></body></html>';

        $result = $this->subject->isAllowedImageMimeType($htmlData);
        self::assertFalse($result);
    }

    #[Test]
    public function isAllowedImageMimeTypeRejectsEmptyContent(): void
    {
        $result = $this->subject->isAllowedImageMimeType('');
        self::assertFalse($result);
    }

    #[Test]
    public function validateLocalPathReturnsNullForEmptyPath(): void
    {
        $result = $this->subject->validateLocalPath('', '/var/www/html');
        self::assertNull($result);
    }

    #[Test]
    public function validateLocalPathReturnsNullForEmptyPublicPath(): void
    {
        $result = $this->subject->validateLocalPath('fileadmin/test.jpg', '');
        self::assertNull($result);
    }

    #[Test]
    public function validateLocalPathSanitizesDirectoryTraversal(): void
    {
        // Create a temp directory structure for testing
        $tempDir  = sys_get_temp_dir() . '/security_test_' . uniqid();
        $testFile = $tempDir . '/fileadmin/test.jpg';

        mkdir(dirname($testFile), 0o755, true);
        touch($testFile);

        try {
            // Attempt path traversal - should be sanitized
            $result = $this->subject->validateLocalPath('../../../etc/passwd', $tempDir);
            self::assertNull($result);

            // Valid path should work
            $result = $this->subject->validateLocalPath('fileadmin/test.jpg', $tempDir);
            self::assertSame($testFile, $result);
        } finally {
            // Cleanup
            unlink($testFile);
            rmdir(dirname($testFile));
            rmdir($tempDir);
        }
    }

    #[Test]
    #[DataProvider('allowedExtensionsProvider')]
    public function isAllowedExtensionAcceptsValidExtensions(string $extension): void
    {
        self::assertTrue($this->subject->isAllowedExtension($extension));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedExtensionsProvider(): array
    {
        return [
            'jpg'           => ['jpg'],
            'jpeg'          => ['jpeg'],
            'gif'           => ['gif'],
            'png'           => ['png'],
            'webp'          => ['webp'],
            'JPG uppercase' => ['JPG'],
            'PNG uppercase' => ['PNG'],
        ];
    }

    #[Test]
    #[DataProvider('disallowedExtensionsProvider')]
    public function isAllowedExtensionRejectsInvalidExtensions(string $extension): void
    {
        self::assertFalse($this->subject->isAllowedExtension($extension));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function disallowedExtensionsProvider(): array
    {
        return [
            'svg'  => ['svg'],
            'php'  => ['php'],
            'html' => ['html'],
            'js'   => ['js'],
            'exe'  => ['exe'],
            'bmp'  => ['bmp'],
        ];
    }

    #[Test]
    public function getAllowedMimeTypesReturnsExpectedTypes(): void
    {
        $expected = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];

        self::assertSame($expected, $this->subject->getAllowedMimeTypes());
    }

    #[Test]
    public function getAllowedExtensionsReturnsExpectedExtensions(): void
    {
        $expected = [
            'jpg',
            'jpeg',
            'gif',
            'png',
            'webp',
        ];

        self::assertSame($expected, $this->subject->getAllowedExtensions());
    }

    #[Test]
    public function getValidatedIpForUrlRejectsNonHttpProtocols(): void
    {
        $nonHttpUrls = [
            'ftp://example.com/image.jpg',
            'file:///etc/passwd',
            'gopher://example.com/image.jpg',
            'javascript:alert(1)',
            'data:image/png;base64,iVBORw0KGgo=',
        ];

        foreach ($nonHttpUrls as $url) {
            $result = $this->subject->getValidatedIpForUrl($url);
            self::assertNull($result, "Expected {$url} to be blocked due to non-HTTP protocol");
        }
    }

    #[Test]
    public function getValidatedIpForUrlBlocksIpv6Loopback(): void
    {
        // IPv6 loopback address ::1
        $result = $this->subject->getValidatedIpForUrl('http://[::1]/image.jpg');
        self::assertNull($result, 'IPv6 loopback should be blocked');
    }

    #[Test]
    public function getValidatedIpForUrlBlocksIpv4MappedIpv6Addresses(): void
    {
        // IPv4-mapped IPv6 addresses should be blocked if they map to private ranges
        $mappedAddresses = [
            'http://[::ffff:127.0.0.1]/image.jpg',  // Loopback mapped
            'http://[::ffff:192.168.1.1]/image.jpg', // Private range mapped
            'http://[::ffff:10.0.0.1]/image.jpg',    // Private range mapped
        ];

        foreach ($mappedAddresses as $url) {
            $result = $this->subject->getValidatedIpForUrl($url);
            self::assertNull($result, "Expected {$url} (IPv4-mapped IPv6) to be blocked");
        }
    }

    #[Test]
    public function getValidatedIpForUrlBlocksPrivateIpv6Ranges(): void
    {
        // Private IPv6 ranges
        $privateIpv6Urls = [
            'http://[fc00::1]/image.jpg',  // Unique Local Address
            'http://[fd00::1]/image.jpg',  // Unique Local Address
            'http://[fe80::1]/image.jpg',  // Link-local
        ];

        foreach ($privateIpv6Urls as $url) {
            $result = $this->subject->getValidatedIpForUrl($url);
            self::assertNull($result, "Expected {$url} (private IPv6) to be blocked");
        }
    }

    #[Test]
    public function getValidatedIpForUrlReturnsIpForValidExternalUrl(): void
    {
        // Test with a well-known external domain
        // Note: This test requires DNS resolution to work
        $result = $this->subject->getValidatedIpForUrl('https://example.com/image.jpg');

        // example.com resolves to 93.184.215.14 (public IP)
        // Should return an IP address string (not null)
        if ($result !== null) {
            self::assertMatchesRegularExpression('/^[\d.]+$/', $result, 'Should return a valid IPv4 address');
        } else {
            // DNS resolution might fail in some test environments
            self::markTestSkipped('DNS resolution not available in this environment');
        }
    }

    #[Test]
    public function getValidatedIpForUrlAcceptsHttpsProtocol(): void
    {
        // This just tests the https scheme is accepted (DNS may fail)
        $result = $this->subject->getValidatedIpForUrl('https://1.1.1.1/image.jpg');
        self::assertSame('1.1.1.1', $result, 'Public IP via HTTPS should be allowed');
    }

    #[Test]
    public function getValidatedIpForUrlAcceptsPublicIpDirectly(): void
    {
        // Public IPs should be allowed
        $publicIps = [
            'http://8.8.8.8/image.jpg' => '8.8.8.8',
            'http://1.1.1.1/image.jpg' => '1.1.1.1',
        ];

        foreach ($publicIps as $url => $expectedIp) {
            $result = $this->subject->getValidatedIpForUrl($url);
            self::assertSame($expectedIp, $result, "Expected {$url} to return IP {$expectedIp}");
        }
    }

    #[Test]
    public function isAllowedImageMimeTypeAcceptsGifImages(): void
    {
        // Create minimal valid GIF data (GIF89a header)
        $gifData = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

        $result = $this->subject->isAllowedImageMimeType($gifData);
        self::assertTrue($result, 'GIF images should be allowed');
    }

    #[Test]
    public function isAllowedImageMimeTypeAcceptsWebpImages(): void
    {
        // Create minimal valid WebP data (RIFF header with WEBP)
        $webpData = "RIFF\x24\x00\x00\x00WEBPVP8 \x18\x00\x00\x000\x01\x00\x9d\x01*\x01\x00\x01\x00\x00\x34%\xa4\x00\x03p\x00\xfe\xfb\x94\x00\x00";

        $result = $this->subject->isAllowedImageMimeType($webpData);
        self::assertTrue($result, 'WebP images should be allowed');
    }

    #[Test]
    public function isAllowedImageMimeTypeRejectsBinaryGarbage(): void
    {
        // Random binary data that doesn't match any image format
        $garbageData = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f";

        $result = $this->subject->isAllowedImageMimeType($garbageData);
        self::assertFalse($result, 'Binary garbage should be rejected');
    }

    #[Test]
    public function validateLocalPathReturnsNullForNonExistentFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/security_test_' . uniqid();
        mkdir($tempDir, 0o755, true);

        try {
            // File doesn't exist - realpath should fail
            $result = $this->subject->validateLocalPath('nonexistent/file.jpg', $tempDir);
            self::assertNull($result, 'Non-existent file should return null');
        } finally {
            rmdir($tempDir);
        }
    }

    #[Test]
    public function validateLocalPathReturnsNullForPathOutsidePublicPath(): void
    {
        // Create two separate temp directories
        $publicDir   = sys_get_temp_dir() . '/public_' . uniqid();
        $privateDir  = sys_get_temp_dir() . '/private_' . uniqid();
        $privateFile = $privateDir . '/secret.jpg';

        mkdir($publicDir, 0o755, true);
        mkdir($privateDir, 0o755, true);
        touch($privateFile);

        try {
            // Try to access file outside public path via symlink or other means
            // This tests that even if a file exists, it must be within public path
            $result = $this->subject->validateLocalPath($privateFile, $publicDir);
            self::assertNull($result, 'File outside public path should return null');
        } finally {
            unlink($privateFile);
            rmdir($privateDir);
            rmdir($publicDir);
        }
    }

    #[Test]
    public function getValidatedIpForUrlHandlesHostnameWithPort(): void
    {
        // URL with explicit port should still work
        $result = $this->subject->getValidatedIpForUrl('http://8.8.8.8:8080/image.jpg');
        self::assertSame('8.8.8.8', $result, 'URL with port should return correct IP');
    }

    #[Test]
    public function getValidatedIpForUrlBlocksInstanceDataHostname(): void
    {
        // instance-data is a cloud metadata hostname pattern
        $result = $this->subject->getValidatedIpForUrl('http://instance-data/latest/');
        // This will likely fail DNS resolution, returning null - which is correct behavior
        self::assertNull($result, 'instance-data hostname should be blocked');
    }
}
