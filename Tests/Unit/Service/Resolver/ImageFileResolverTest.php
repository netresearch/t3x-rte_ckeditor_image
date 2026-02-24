<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service\Resolver;

use Netresearch\RteCKEditorImage\Service\Environment\EnvironmentInfoInterface;
use Netresearch\RteCKEditorImage\Service\Fetcher\ExternalImageFetcherInterface;
use Netresearch\RteCKEditorImage\Service\Resolver\ImageFileResolver;
use Netresearch\RteCKEditorImage\Service\Security\SecurityValidatorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Test case for ImageFileResolver.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ImageFileResolver::class)]
class ImageFileResolverTest extends TestCase
{
    private ResourceFactory&MockObject $resourceFactory;
    private SecurityValidatorInterface&MockObject $securityValidator;
    private ExternalImageFetcherInterface&MockObject $externalImageFetcher;
    private EnvironmentInfoInterface&MockObject $environmentInfo;
    private LoggerInterface&MockObject $logger;
    private ImageFileResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourceFactory      = $this->createMock(ResourceFactory::class);
        $this->securityValidator    = $this->createMock(SecurityValidatorInterface::class);
        $this->externalImageFetcher = $this->createMock(ExternalImageFetcherInterface::class);
        $this->environmentInfo      = $this->createMock(EnvironmentInfoInterface::class);
        $this->logger               = $this->createMock(LoggerInterface::class);

        $this->resolver = new ImageFileResolver(
            $this->resourceFactory,
            $this->securityValidator,
            $this->externalImageFetcher,
            $this->environmentInfo,
            $this->logger,
        );
    }

    // ========================================================================
    // resolveByUid() Tests
    // ========================================================================

    #[Test]
    public function resolveByUidReturnsNullForZeroUid(): void
    {
        $result = $this->resolver->resolveByUid(0);

        self::assertNull($result);
    }

    #[Test]
    public function resolveByUidReturnsNullForNegativeUid(): void
    {
        $result = $this->resolver->resolveByUid(-1);

        self::assertNull($result);
    }

    #[Test]
    public function resolveByUidReturnsFileForValidUid(): void
    {
        $fileMock = $this->createMock(File::class);

        $this->resourceFactory
            ->expects($this->once())
            ->method('getFileObject')
            ->with(123)
            ->willReturn($fileMock);

        $result = $this->resolver->resolveByUid(123);

        self::assertSame($fileMock, $result);
    }

    #[Test]
    public function resolveByUidReturnsNullOnException(): void
    {
        $this->resourceFactory
            ->method('getFileObject')
            ->willThrowException(new RuntimeException('File not found'));

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Could not resolve file by UID',
                self::callback(static function (mixed $context): bool {
                    return is_array($context)
                        && $context['fileUid'] === 999
                        && is_string($context['exception'])
                        && str_contains($context['exception'], 'File not found');
                }),
            );

        $result = $this->resolver->resolveByUid(999);

        self::assertNull($result);
    }

    // ========================================================================
    // resolveByPath() Tests
    // ========================================================================

    #[Test]
    public function resolveByPathReturnsNullForEmptyPath(): void
    {
        $result = $this->resolver->resolveByPath('');

        self::assertNull($result);
    }

    #[Test]
    public function resolveByPathReturnsNullForWhitespacePath(): void
    {
        $result = $this->resolver->resolveByPath('   ');

        self::assertNull($result);
    }

    #[Test]
    public function resolveByPathReturnsNullWhenSecurityValidationFails(): void
    {
        $this->environmentInfo
            ->method('getPublicPath')
            ->willReturn('/var/www/public');

        $this->securityValidator
            ->method('validateLocalPath')
            ->with('../../../etc/passwd', '/var/www/public')
            ->willReturn(null);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Local image path failed security validation',
                ['path' => '../../../etc/passwd'],
            );

        $result = $this->resolver->resolveByPath('../../../etc/passwd');

        self::assertNull($result);
    }

    #[Test]
    public function resolveByPathReturnsFileForValidPath(): void
    {
        $this->environmentInfo
            ->method('getPublicPath')
            ->willReturn('/var/www/public');

        $this->securityValidator
            ->method('validateLocalPath')
            ->with('fileadmin/image.jpg', '/var/www/public')
            ->willReturn('/var/www/public/fileadmin/image.jpg');

        $fileMock = $this->createMock(File::class);

        $this->resourceFactory
            ->expects($this->once())
            ->method('retrieveFileOrFolderObject')
            ->with('fileadmin/image.jpg')
            ->willReturn($fileMock);

        $result = $this->resolver->resolveByPath('fileadmin/image.jpg');

        self::assertSame($fileMock, $result);
    }

    #[Test]
    public function resolveByPathReturnsNullOnException(): void
    {
        $this->environmentInfo
            ->method('getPublicPath')
            ->willReturn('/var/www/public');

        $this->securityValidator
            ->method('validateLocalPath')
            ->willReturn('/var/www/public/fileadmin/missing.jpg');

        $this->resourceFactory
            ->method('retrieveFileOrFolderObject')
            ->willThrowException(new RuntimeException('File not found'));

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Could not resolve file by path',
                self::callback(static function (mixed $context): bool {
                    return is_array($context) && $context['path'] === 'fileadmin/missing.jpg';
                }),
            );

        $result = $this->resolver->resolveByPath('fileadmin/missing.jpg');

        self::assertNull($result);
    }

    #[Test]
    public function resolveByPathReturnsNullWhenNotFileInstance(): void
    {
        $this->environmentInfo
            ->method('getPublicPath')
            ->willReturn('/var/www/public');

        $this->securityValidator
            ->method('validateLocalPath')
            ->willReturn('/var/www/public/fileadmin');

        // Returns a Folder instead of File
        $folderMock = $this->createMock(Folder::class);

        $this->resourceFactory
            ->method('retrieveFileOrFolderObject')
            ->willReturn($folderMock);

        $result = $this->resolver->resolveByPath('fileadmin');

        self::assertNull($result);
    }

    // ========================================================================
    // processImage() Tests
    // ========================================================================

    #[Test]
    public function processImageReturnsNullForZeroWidth(): void
    {
        $fileMock = $this->createMock(File::class);

        $result = $this->resolver->processImage($fileMock, 0, 100);

        self::assertNull($result);
    }

    #[Test]
    public function processImageReturnsNullForZeroHeight(): void
    {
        $fileMock = $this->createMock(File::class);

        $result = $this->resolver->processImage($fileMock, 100, 0);

        self::assertNull($result);
    }

    #[Test]
    public function processImageReturnsNullForNegativeDimensions(): void
    {
        $fileMock = $this->createMock(File::class);

        $result = $this->resolver->processImage($fileMock, -100, -100);

        self::assertNull($result);
    }

    #[Test]
    public function processImageReturnsProcessedFile(): void
    {
        $fileMock          = $this->createMock(File::class);
        $processedFileMock = $this->createMock(ProcessedFile::class);

        $fileMock
            ->expects($this->once())
            ->method('process')
            ->with(
                ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                ['width' => 400, 'height' => 300],
            )
            ->willReturn($processedFileMock);

        $result = $this->resolver->processImage($fileMock, 400, 300);

        self::assertSame($processedFileMock, $result);
    }

    #[Test]
    public function processImageWithAdditionalOptions(): void
    {
        $fileMock          = $this->createMock(File::class);
        $processedFileMock = $this->createMock(ProcessedFile::class);

        $fileMock
            ->expects($this->once())
            ->method('process')
            ->with(
                ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                [
                    'width'  => 400,
                    'height' => 300,
                    'crop'   => 'center',
                ],
            )
            ->willReturn($processedFileMock);

        $result = $this->resolver->processImage($fileMock, 400, 300, ['crop' => 'center']);

        self::assertSame($processedFileMock, $result);
    }

    #[Test]
    public function processImageReturnsNullOnException(): void
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getUid')->willReturn(123);
        $fileMock
            ->method('process')
            ->willThrowException(new RuntimeException('Processing failed'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to process image',
                self::callback(static function (mixed $context): bool {
                    return is_array($context)
                        && $context['fileUid'] === 123
                        && $context['width'] === 400
                        && $context['height'] === 300;
                }),
            );

        $result = $this->resolver->processImage($fileMock, 400, 300);

        self::assertNull($result);
    }

    // ========================================================================
    // importExternalImage() Tests
    // ========================================================================

    #[Test]
    public function importExternalImageReturnsNullWhenFetchFails(): void
    {
        $this->externalImageFetcher
            ->method('fetch')
            ->with('https://external.com/image.jpg')
            ->willReturn(null);

        $result = $this->resolver->importExternalImage('https://external.com/image.jpg');

        self::assertNull($result);
    }

    #[Test]
    public function importExternalImageReturnsNullOnException(): void
    {
        $this->externalImageFetcher
            ->method('fetch')
            ->willReturn('fake-image-content');

        $this->resourceFactory
            ->method('getFolderObjectFromCombinedIdentifier')
            ->willThrowException(new RuntimeException('Folder not found'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to import external image',
                self::callback(static function (mixed $context): bool {
                    return is_array($context) && $context['url'] === 'https://external.com/image.jpg';
                }),
            );

        $result = $this->resolver->importExternalImage('https://external.com/image.jpg');

        self::assertNull($result);
    }

    #[Test]
    public function importExternalImageUsesDefaultTargetFolder(): void
    {
        $this->externalImageFetcher
            ->method('fetch')
            ->willReturn('fake-image-content');

        $folderMock = $this->createMock(Folder::class);
        $fileMock   = $this->createMock(File::class);

        $this->resourceFactory
            ->expects($this->once())
            ->method('getFolderObjectFromCombinedIdentifier')
            ->with('1:/_temp_/')
            ->willReturn($folderMock);

        $folderMock
            ->method('addFile')
            ->willReturn($fileMock);

        $this->securityValidator
            ->method('isAllowedExtension')
            ->willReturn(true);

        $result = $this->resolver->importExternalImage('https://external.com/image.jpg');

        self::assertSame($fileMock, $result);
    }

    #[Test]
    public function importExternalImageUsesCustomTargetFolder(): void
    {
        $this->externalImageFetcher
            ->method('fetch')
            ->willReturn('fake-image-content');

        $folderMock = $this->createMock(Folder::class);
        $fileMock   = $this->createMock(File::class);

        $this->resourceFactory
            ->expects($this->once())
            ->method('getFolderObjectFromCombinedIdentifier')
            ->with('1:/user_uploads/')
            ->willReturn($folderMock);

        $folderMock
            ->method('addFile')
            ->willReturn($fileMock);

        $this->securityValidator
            ->method('isAllowedExtension')
            ->willReturn(true);

        $result = $this->resolver->importExternalImage('https://external.com/image.jpg', '1:/user_uploads/');

        self::assertSame($fileMock, $result);
    }

    // ========================================================================
    // fileExists() Tests
    // ========================================================================

    #[Test]
    public function fileExistsReturnsTrueForExistingFile(): void
    {
        $fileMock = $this->createMock(File::class);

        $this->resourceFactory
            ->method('getFileObject')
            ->with(123)
            ->willReturn($fileMock);

        $result = $this->resolver->fileExists(123);

        self::assertTrue($result);
    }

    #[Test]
    public function fileExistsReturnsFalseForNonExistingFile(): void
    {
        $this->resourceFactory
            ->method('getFileObject')
            ->willThrowException(new RuntimeException('Not found'));

        $result = $this->resolver->fileExists(999);

        self::assertFalse($result);
    }

    #[Test]
    public function fileExistsReturnsFalseForZeroUid(): void
    {
        $result = $this->resolver->fileExists(0);

        self::assertFalse($result);
    }

    // ========================================================================
    // Filename Generation Tests (via importExternalImage)
    // ========================================================================

    /**
     * @return array<string, array{string, bool}>
     */
    public static function filenameExtensionDataProvider(): array
    {
        return [
            'valid jpg'     => ['https://example.com/photo.jpg', true],
            'valid png'     => ['https://example.com/image.png', true],
            'valid gif'     => ['https://example.com/anim.gif', true],
            'valid webp'    => ['https://example.com/modern.webp', true],
            'invalid exe'   => ['https://example.com/file.exe', false],
            'no extension'  => ['https://example.com/image', false],
            'with query'    => ['https://example.com/image.jpg?size=large', true],
            'with fragment' => ['https://example.com/image.png#section', true],
        ];
    }

    #[Test]
    #[DataProvider('filenameExtensionDataProvider')]
    public function importExternalImageValidatesExtension(string $url, bool $isAllowed): void
    {
        $this->externalImageFetcher
            ->method('fetch')
            ->willReturn('fake-image-content');

        $folderMock = $this->createMock(Folder::class);
        $fileMock   = $this->createMock(File::class);

        $this->resourceFactory
            ->method('getFolderObjectFromCombinedIdentifier')
            ->willReturn($folderMock);

        $this->securityValidator
            ->method('isAllowedExtension')
            ->willReturn($isAllowed);

        $folderMock
            ->method('addFile')
            ->with(
                self::anything(),
                self::callback(static function (mixed $filename) use ($isAllowed): bool {
                    // If extension not allowed, should default to .jpg
                    if (!$isAllowed) {
                        return is_string($filename) && str_ends_with($filename, '.jpg');
                    }

                    return true;
                }),
            )
            ->willReturn($fileMock);

        $result = $this->resolver->importExternalImage($url);

        self::assertInstanceOf(File::class, $result);
    }
}
