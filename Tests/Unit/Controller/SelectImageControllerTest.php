<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Controller;

use Exception;
use Netresearch\RteCKEditorImage\Controller\SelectImageController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use RuntimeException;
use TYPO3\CMS\Backend\ElementBrowser\ElementBrowserRegistry;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for SelectImageController.
 */
#[CoversClass(SelectImageController::class)]
final class SelectImageControllerTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private SelectImageController $subject;

    /** @var ResourceFactory&MockObject */
    private ResourceFactory $resourceFactoryMock;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ResourceFactory&MockObject $resourceFactoryMock */
        $resourceFactoryMock = $this->createMock(ResourceFactory::class);

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $this->resourceFactoryMock = $resourceFactoryMock;
        $this->subject             = new SelectImageController(
            $this->resourceFactoryMock,
            $elementBrowserRegistryMock,
        );
    }

    /**
     * Helper method to access protected methods.
     *
     * @param array<int, mixed> $args
     */
    private function callProtectedMethod(string $methodName, array $args): mixed
    {
        $reflection = new ReflectionMethod($this->subject, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($this->subject, $args);
    }

    #[Test]
    public function getImageReturnsFileForValidId(): void
    {
        $fileId = 123;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->method('isDeleted')->willReturn(false);
        $fileMock->method('isMissing')->willReturn(false);

        $this->resourceFactoryMock
            ->expects(self::once())
            ->method('getFileObject')
            ->with($fileId)
            ->willReturn($fileMock);

        $result = $this->callProtectedMethod('getImage', [$fileId]);

        self::assertSame($fileMock, $result);
    }

    #[Test]
    public function getImageThrowsRuntimeExceptionWhenFileNotFound(): void
    {
        $fileId = 999;

        $this->resourceFactoryMock
            ->expects(self::once())
            ->method('getFileObject')
            ->with($fileId)
            ->willThrowException(new Exception('File not found'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');
        $this->expectExceptionCode(1734282000);

        $this->callProtectedMethod('getImage', [$fileId]);
    }

    #[Test]
    public function getImageThrowsRuntimeExceptionWhenFileIsDeleted(): void
    {
        $fileId = 123;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->expects(self::once())->method('isDeleted')->willReturn(true);
        $fileMock->expects(self::never())->method('isMissing');

        $this->resourceFactoryMock
            ->expects(self::once())
            ->method('getFileObject')
            ->with($fileId)
            ->willReturn($fileMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1734282001);

        $this->callProtectedMethod('getImage', [$fileId]);
    }

    #[Test]
    public function getImageThrowsRuntimeExceptionWhenFileIsMissing(): void
    {
        $fileId = 123;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->expects(self::once())->method('isDeleted')->willReturn(false);
        $fileMock->expects(self::once())->method('isMissing')->willReturn(true);

        $this->resourceFactoryMock
            ->expects(self::once())
            ->method('getFileObject')
            ->with($fileId)
            ->willReturn($fileMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1734282001);

        $this->callProtectedMethod('getImage', [$fileId]);
    }

    #[Test]
    public function processImageReturnsProcessedFileWithCorrectDimensions(): void
    {
        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')
            ->willReturnMap([
                ['width', 2000],
                ['height', 1500],
            ]);

        /** @var ProcessedFile&MockObject $processedFileMock */
        $processedFileMock = $this->createMock(ProcessedFile::class);

        $fileMock
            ->expects(self::once())
            ->method('process')
            ->with(
                ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                [
                    'width'  => 1920,
                    'height' => 1500,
                ],
            )
            ->willReturn($processedFileMock);

        $params        = ['width' => 1920, 'height' => 1500];
        $maxDimensions = ['width' => 1920, 'height' => 9999];

        $result = $this->callProtectedMethod('processImage', [$fileMock, $params, $maxDimensions]);

        self::assertSame($processedFileMock, $result);
    }

    #[Test]
    public function processImageRespectsMaxDimensions(): void
    {
        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')
            ->willReturnMap([
                ['width', 5000],
                ['height', 3000],
            ]);

        /** @var ProcessedFile&MockObject $processedFileMock */
        $processedFileMock = $this->createMock(ProcessedFile::class);

        $fileMock
            ->expects(self::once())
            ->method('process')
            ->with(
                ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                [
                    'width'  => 1920,
                    'height' => 1080,
                ],
            )
            ->willReturn($processedFileMock);

        // Request larger than max dimensions - should be clamped
        $params        = ['width' => 5000, 'height' => 3000];
        $maxDimensions = ['width' => 1920, 'height' => 1080];

        $result = $this->callProtectedMethod('processImage', [$fileMock, $params, $maxDimensions]);

        self::assertSame($processedFileMock, $result);
    }

    #[Test]
    public function processImageUsesFilePropertiesWhenParamsNotProvided(): void
    {
        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')
            ->willReturnMap([
                ['width', 1920],
                ['height', 1080],
            ]);

        /** @var ProcessedFile&MockObject $processedFileMock */
        $processedFileMock = $this->createMock(ProcessedFile::class);

        $fileMock
            ->expects(self::once())
            ->method('process')
            ->with(
                ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                [
                    'width'  => 1920,
                    'height' => 1080,
                ],
            )
            ->willReturn($processedFileMock);

        // Empty params - should use file properties
        $params        = [];
        $maxDimensions = ['width' => 1920, 'height' => 9999];

        $result = $this->callProtectedMethod('processImage', [$fileMock, $params, $maxDimensions]);

        self::assertSame($processedFileMock, $result);
    }

    // Note: getMaxDimensions tests require functional test setup due to BackendUtility dependency
    // These are better tested in functional tests

    #[Test]
    public function infoActionReturns412WhenFileIdIsMissing(): void
    {
        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'fileId' => null,
            'table'  => 'tt_content',
        ]);

        $response = $this->subject->infoAction($requestMock);

        self::assertSame(412, $response->getStatusCode());
        self::assertSame('Precondition Failed', $response->getReasonPhrase());
    }

    #[Test]
    public function infoActionReturns412WhenFileIdIsNotNumeric(): void
    {
        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'fileId' => 'not-a-number',
            'table'  => 'tt_content',
        ]);

        $response = $this->subject->infoAction($requestMock);

        self::assertSame(412, $response->getStatusCode());
    }

    #[Test]
    public function infoActionReturns404WhenFileNotFound(): void
    {
        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'fileId' => 999,
            'table'  => 'tt_content',
        ]);

        $this->resourceFactoryMock
            ->method('getFileObject')
            ->willThrowException(new Exception('File not found'));

        $response = $this->subject->infoAction($requestMock);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
    }

    // Note: mainAction tests require functional test setup due to parent::mainAction() dependency
    // Testing the parent ElementBrowserController behavior is not suitable for unit tests

    #[Test]
    public function isFileAccessibleByUserReturnsFalseWhenNoBackendUser(): void
    {
        $GLOBALS['BE_USER'] = null;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);

        $result = $this->callProtectedMethod('isFileAccessibleByUser', [$fileMock]);

        self::assertFalse($result);
    }

    #[Test]
    public function isFileAccessibleByUserReturnsFalseWhenNoTableSelectPermission(): void
    {
        $backendUserMock = $this->createMock(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
        $backendUserMock->method('check')->with('tables_select', 'sys_file')->willReturn(false);

        $GLOBALS['BE_USER'] = $backendUserMock;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);

        $result = $this->callProtectedMethod('isFileAccessibleByUser', [$fileMock]);

        self::assertFalse($result);
    }

    #[Test]
    public function isFileAccessibleByUserReturnsTrueForAdminUsers(): void
    {
        $backendUserMock = $this->createMock(\TYPO3\CMS\Core\Authentication\BackendUserAuthentication::class);
        $backendUserMock->method('check')->with('tables_select', 'sys_file')->willReturn(true);
        $backendUserMock->method('isAdmin')->willReturn(true);

        $GLOBALS['BE_USER'] = $backendUserMock;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);

        $result = $this->callProtectedMethod('isFileAccessibleByUser', [$fileMock]);

        self::assertTrue($result);
    }

    // Note: isFileAccessibleByUser tests with getFileStorageRecords() require functional test setup
    // The BackendUserAuthentication mock doesn't support this method properly in unit tests
}
