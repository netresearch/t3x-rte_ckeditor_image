<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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
}
