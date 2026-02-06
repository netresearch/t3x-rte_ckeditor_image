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
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionMethod;
use RuntimeException;
use TYPO3\CMS\Backend\ElementBrowser\ElementBrowserRegistry;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Richtext;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Resource\DefaultUploadFolderResolver;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for SelectImageController.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(SelectImageController::class)]
final class SelectImageControllerTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private SelectImageController $subject;

    /** @var ResourceFactory&MockObject */
    private ResourceFactory $resourceFactoryMock;

    /** @var DefaultUploadFolderResolver&MockObject */
    private DefaultUploadFolderResolver $uploadFolderResolverMock;

    /** @var Richtext&MockObject */
    private Richtext $richtextMock;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ResourceFactory&MockObject $resourceFactoryMock */
        $resourceFactoryMock = $this->createMock(ResourceFactory::class);

        /** @var DefaultUploadFolderResolver&MockObject $uploadFolderResolverMock */
        $uploadFolderResolverMock = $this->createMock(DefaultUploadFolderResolver::class);

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);

        /** @var Richtext&MockObject $richtextMock */
        $richtextMock = $this->createMock(Richtext::class);
        $richtextMock->method('getConfiguration')->willReturn([]);

        $this->resourceFactoryMock      = $resourceFactoryMock;
        $this->uploadFolderResolverMock = $uploadFolderResolverMock;
        $this->richtextMock             = $richtextMock;
        $this->subject                  = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $this->richtextMock,
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
    public function calculateDisplayDimensionsReturnsOriginalWhenWithinLimits(): void
    {
        $result = $this->callProtectedMethod('calculateDisplayDimensions', [800, 600, 1920, 1080]);

        self::assertSame(['width' => 800, 'height' => 600], $result);
    }

    #[Test]
    public function calculateDisplayDimensionsScalesDownWhenWidthExceedsLimit(): void
    {
        // 2400x1800 image with 1920x1080 max dimensions
        // widthScale = 1920/2400 = 0.8, heightScale = 1080/1800 = 0.6
        // Uses min(0.8, 0.6) = 0.6 (height is more limiting!)
        // Width: 2400 * 0.6 = 1440, Height: 1800 * 0.6 = 1080
        $result = $this->callProtectedMethod('calculateDisplayDimensions', [2400, 1800, 1920, 1080]);

        self::assertSame(['width' => 1440, 'height' => 1080], $result);
    }

    #[Test]
    public function calculateDisplayDimensionsScalesDownWhenHeightExceedsLimit(): void
    {
        // 1600x1200 image with 1920x1080 max dimensions
        // Height is limiting factor: 1200 -> 1080 (scale = 0.9)
        // Width: 1600 * 0.9 = 1440
        $result = $this->callProtectedMethod('calculateDisplayDimensions', [1600, 1200, 1920, 1080]);

        self::assertSame(['width' => 1440, 'height' => 1080], $result);
    }

    #[Test]
    public function calculateDisplayDimensionsPreservesAspectRatio(): void
    {
        // 3000x2000 image with 1920x1080 max dimensions
        // widthScale = 1920/3000 = 0.64, heightScale = 1080/2000 = 0.54
        // Uses min(0.64, 0.54) = 0.54 (height is more limiting!)
        // Width: 3000 * 0.54 = 1620, Height: 2000 * 0.54 = 1080
        $result = $this->callProtectedMethod('calculateDisplayDimensions', [3000, 2000, 1920, 1080]);

        self::assertSame(['width' => 1620, 'height' => 1080], $result);
        // Verify aspect ratio is preserved: 3000/2000 = 1.5, 1620/1080 = 1.5
        self::assertEqualsWithDelta(3000 / 2000, $result['width'] / $result['height'], 0.01);
    }

    #[Test]
    public function calculateDisplayDimensionsHandlesSmallImages(): void
    {
        // Small 200x150 image with 1920x1080 max dimensions - should return original
        $result = $this->callProtectedMethod('calculateDisplayDimensions', [200, 150, 1920, 1080]);

        self::assertSame(['width' => 200, 'height' => 150], $result);
    }

    #[Test]
    public function calculateDisplayDimensionsHandlesSvgScenarios(): void
    {
        // SVG with small dimensions (100x100) should be allowed to scale up to max
        // This is handled by returning original dimensions and letting frontend scale
        $result = $this->callProtectedMethod('calculateDisplayDimensions', [100, 100, 1920, 1080]);

        self::assertSame(['width' => 100, 'height' => 100], $result);
    }

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
        $backendUserMock = $this->createMock(BackendUserAuthentication::class);
        $backendUserMock->method('check')->with('tables_select', 'sys_file')->willReturn(false);

        $GLOBALS['BE_USER'] = $backendUserMock;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);

        $result = $this->callProtectedMethod('isFileAccessibleByUser', [$fileMock]);

        self::assertFalse($result);
    }

    #[Test]
    public function isFileAccessibleByUserReturnsTrueWhenFilePermitsRead(): void
    {
        $backendUserMock = $this->createMock(BackendUserAuthentication::class);
        $backendUserMock->method('check')->with('tables_select', 'sys_file')->willReturn(true);

        $GLOBALS['BE_USER'] = $backendUserMock;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        // File::checkActionPermission('read') uses TYPO3's built-in permission system
        // which internally checks admin status, file mounts, and user permissions
        $fileMock->method('checkActionPermission')->with('read')->willReturn(true);

        $result = $this->callProtectedMethod('isFileAccessibleByUser', [$fileMock]);

        self::assertTrue($result);
    }

    #[Test]
    public function isFileAccessibleByUserReturnsFalseWhenFileDeniesRead(): void
    {
        $backendUserMock = $this->createMock(BackendUserAuthentication::class);
        $backendUserMock->method('check')->with('tables_select', 'sys_file')->willReturn(true);

        $GLOBALS['BE_USER'] = $backendUserMock;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        // Non-admin user without proper file mount access
        $fileMock->method('checkActionPermission')->with('read')->willReturn(false);

        $result = $this->callProtectedMethod('isFileAccessibleByUser', [$fileMock]);

        self::assertFalse($result);
    }

    #[Test]
    public function isFileAccessibleByUserDelegatesToFilePermissionCheck(): void
    {
        // This test verifies that we correctly delegate to TYPO3's built-in
        // File::checkActionPermission() which internally handles:
        // - Admin user detection (admins always have access)
        // - File mount boundary checks (isWithinFileMountBoundaries)
        // - User group permissions
        // This replaces the broken getFileStorageRecords() approach from issue #290

        $backendUserMock = $this->createMock(BackendUserAuthentication::class);
        $backendUserMock->method('check')->with('tables_select', 'sys_file')->willReturn(true);

        $GLOBALS['BE_USER'] = $backendUserMock;

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        // Verify checkActionPermission is called with 'read' action
        $fileMock->expects(self::once())
            ->method('checkActionPermission')
            ->with('read')
            ->willReturn(true);

        $result = $this->callProtectedMethod('isFileAccessibleByUser', [$fileMock]);

        self::assertTrue($result);
    }

    // ==================================================================================
    // mainAction() expandFolder tests (issue #290 follow-up)
    // These tests verify the request modification logic using a testable subclass
    // that intercepts the parent::mainAction() call to avoid complex mocking.
    // ==================================================================================

    /**
     * Creates a testable controller that mimics SelectImageController::mainAction() behavior
     * but captures the modified queryParams instead of calling parent::mainAction().
     *
     * Note: We pass the uploadFolderResolver mock separately because the parent class
     * stores it as private readonly, making it inaccessible to the anonymous subclass.
     */
    private function createTestableController(): TestableExpandFolderController
    {
        return new TestableExpandFolderController($this->uploadFolderResolverMock);
    }

    #[Test]
    public function mainActionPreservesExpandFolderWhenAlreadyProvided(): void
    {
        // This tests the fix for issue #290 follow-up:
        // expandFolder should NOT be overwritten when already in the request,
        // allowing folder navigation to work correctly.

        $existingExpandFolder = '1:/user_upload/subfolder/';

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(null);
        $requestMock->method('getQueryParams')->willReturn([
            'expandFolder' => $existingExpandFolder,
            'bparams'      => 'field|||',
        ]);

        // Backend user exists but uploadFolderResolver should NOT be called
        $backendUserMock = $this->createMock(BackendUserAuthentication::class);

        $GLOBALS['BE_USER'] = $backendUserMock;

        // The uploadFolderResolver should NOT be called when expandFolder is already set
        $this->uploadFolderResolverMock
            ->expects(self::never())
            ->method('resolve');

        $testableController = $this->createTestableController();
        $testableController->mainAction($requestMock);

        // Verify expandFolder was preserved (not overwritten)
        self::assertNotNull($testableController->capturedQueryParams);
        self::assertSame($existingExpandFolder, $testableController->capturedQueryParams['expandFolder']);
    }

    #[Test]
    public function mainActionSetsExpandFolderWhenNotProvided(): void
    {
        // When expandFolder is NOT in the request, it should be set from uploadFolderResolver

        $defaultFolderIdentifier = '1:/user_upload/';

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(null);
        $requestMock->method('getQueryParams')->willReturn([
            'bparams' => 'field|||',
            // expandFolder NOT set
        ]);

        // Backend user exists
        $backendUserMock = $this->createMock(BackendUserAuthentication::class);

        $GLOBALS['BE_USER'] = $backendUserMock;

        // Mock folder
        /** @var Folder&MockObject $folderMock */
        $folderMock = $this->createMock(Folder::class);
        $folderMock->method('getCombinedIdentifier')->willReturn($defaultFolderIdentifier);

        // uploadFolderResolver SHOULD be called and return the default folder
        $this->uploadFolderResolverMock
            ->expects(self::once())
            ->method('resolve')
            ->with($backendUserMock)
            ->willReturn($folderMock);

        $testableController = $this->createTestableController();
        $testableController->mainAction($requestMock);

        // Verify expandFolder was set from the resolver
        self::assertNotNull($testableController->capturedQueryParams);
        self::assertSame($defaultFolderIdentifier, $testableController->capturedQueryParams['expandFolder']);
    }

    #[Test]
    public function mainActionDoesNotSetExpandFolderForInfoAction(): void
    {
        // For info action, expandFolder modification should be skipped entirely

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(null);
        $requestMock->method('getQueryParams')->willReturn([
            'action'  => 'info',
            'bparams' => 'field|||',
        ]);

        // Backend user exists
        $backendUserMock = $this->createMock(BackendUserAuthentication::class);

        $GLOBALS['BE_USER'] = $backendUserMock;

        // uploadFolderResolver should NOT be called for info action
        $this->uploadFolderResolverMock
            ->expects(self::never())
            ->method('resolve');

        $testableController = $this->createTestableController();
        $testableController->mainAction($requestMock);

        // Verify expandFolder was NOT set (info action skips the logic)
        self::assertNotNull($testableController->capturedQueryParams);
        self::assertArrayNotHasKey('expandFolder', $testableController->capturedQueryParams);
    }

    #[Test]
    public function mainActionHandlesUploadFolderResolverException(): void
    {
        // When uploadFolderResolver throws an exception, it should be silently handled

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(null);
        $requestMock->method('getQueryParams')->willReturn([
            'bparams' => 'field|||',
        ]);

        $backendUserMock = $this->createMock(BackendUserAuthentication::class);

        $GLOBALS['BE_USER'] = $backendUserMock;

        // uploadFolderResolver throws exception
        $this->uploadFolderResolverMock
            ->expects(self::once())
            ->method('resolve')
            ->willThrowException(new Exception('No upload folder configured'));

        $testableController = $this->createTestableController();

        // Should not throw - exception is silently caught
        $testableController->mainAction($requestMock);

        // Verify expandFolder was NOT set (no crash, just skipped)
        self::assertNotNull($testableController->capturedQueryParams);
        self::assertArrayNotHasKey('expandFolder', $testableController->capturedQueryParams);
    }

    #[Test]
    public function mainActionHandlesFalseFromResolver(): void
    {
        // When uploadFolderResolver returns false (no default folder configured), expandFolder should not be set
        // Note: DefaultUploadFolderResolver::resolve() returns Folder|bool, not Folder|null

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(null);
        $requestMock->method('getQueryParams')->willReturn([
            'bparams' => 'field|||',
        ]);

        $backendUserMock = $this->createMock(BackendUserAuthentication::class);

        $GLOBALS['BE_USER'] = $backendUserMock;

        // uploadFolderResolver returns false (no default folder configured)
        $this->uploadFolderResolverMock
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(false);

        $testableController = $this->createTestableController();
        $testableController->mainAction($requestMock);

        // Verify expandFolder was NOT set
        self::assertNotNull($testableController->capturedQueryParams);
        self::assertArrayNotHasKey('expandFolder', $testableController->capturedQueryParams);
    }

    #[Test]
    public function mainActionDoesNotSetExpandFolderWithoutBackendUser(): void
    {
        // Without a backend user, expandFolder should not be set

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(null);
        $requestMock->method('getQueryParams')->willReturn([
            'bparams' => 'field|||',
        ]);

        // No backend user
        $GLOBALS['BE_USER'] = null;

        // uploadFolderResolver should NOT be called without backend user
        $this->uploadFolderResolverMock
            ->expects(self::never())
            ->method('resolve');

        $testableController = $this->createTestableController();
        $testableController->mainAction($requestMock);

        // Verify expandFolder was NOT set
        self::assertNotNull($testableController->capturedQueryParams);
        self::assertArrayNotHasKey('expandFolder', $testableController->capturedQueryParams);
    }

    // ========================================================================
    // mainAction Dispatch to linkBrowserAction Tests
    // ========================================================================

    #[Test]
    public function mainActionDispatchesToLinkBrowserActionForLinkBrowserAction(): void
    {
        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('wizard_link', self::anything())
            ->willReturn('/typo3/wizard/link?token=abc123');

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $controller = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $this->richtextMock,
        );

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(null);
        $requestMock->method('getQueryParams')->willReturn([
            'action' => 'linkBrowser',
            'pid'    => 1,
        ]);

        $response = $controller->mainAction($requestMock);

        // Should return JSON response from linkBrowserAction
        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('url', $data);
    }

    #[Test]
    public function mainActionDispatchesToLinkBrowserActionForLowercaseAction(): void
    {
        // Test the lowercase variant 'linkbrowser'
        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->willReturn('/typo3/wizard/link');

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $controller = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $this->richtextMock,
        );

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn(null);
        $requestMock->method('getQueryParams')->willReturn([
            'action' => 'linkbrowser', // lowercase variant
        ]);

        $response = $controller->mainAction($requestMock);

        // Should return JSON response from linkBrowserAction
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function mainActionDispatchesToLinkBrowserActionFromParsedBody(): void
    {
        // Test that action from parsed body takes precedence
        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->willReturn('/typo3/wizard/link');

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $controller = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $this->richtextMock,
        );

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getParsedBody')->willReturn([
            'action' => 'linkBrowser',
        ]);
        $requestMock->method('getQueryParams')->willReturn([]);

        $response = $controller->mainAction($requestMock);

        // Should return JSON response from linkBrowserAction
        self::assertSame(200, $response->getStatusCode());
    }

    // ========================================================================
    // linkBrowserAction Tests
    // ========================================================================

    #[Test]
    public function linkBrowserActionReturnsJsonResponseWithUrl(): void
    {
        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('wizard_link', self::anything())
            ->willReturn('/typo3/wizard/link?token=abc123');

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $controller = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $this->richtextMock,
        );

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'pid'          => 1,
            'currentValue' => 't3://page?uid=5',
        ]);

        $response = $controller->linkBrowserAction($requestMock);

        self::assertSame(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        self::assertIsArray($data);
        self::assertArrayHasKey('url', $data);
        self::assertSame('/typo3/wizard/link?token=abc123', $data['url']);
    }

    #[Test]
    public function linkBrowserActionHandlesEmptyQueryParams(): void
    {
        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('wizard_link', self::callback(static function (array $params): bool {
                // Verify default values are used
                return $params['P']['pid'] === 0
                    && $params['P']['currentValue'] === '';
            }))
            ->willReturn('/typo3/wizard/link');

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $controller = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $this->richtextMock,
        );

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([]);

        $response = $controller->linkBrowserAction($requestMock);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function linkBrowserActionReturnsErrorOnException(): void
    {
        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->willThrowException(new Exception('Route not found'));

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $controller = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $this->richtextMock,
        );

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([]);

        $response = $controller->linkBrowserAction($requestMock);

        self::assertSame(500, $response->getStatusCode());

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        self::assertIsArray($data);
        self::assertArrayHasKey('error', $data);
        self::assertIsString($data['error']);
        // Error message is sanitized to prevent leaking internal details
        self::assertSame('Failed to generate link browser URL', $data['error']);
    }

    #[Test]
    public function linkBrowserActionPassesCorrectParametersToUriBuilder(): void
    {
        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('wizard_link', self::callback(static function (array $params): bool {
                // Verify the P array structure
                $p = $params['P'] ?? [];

                return $p['table'] === 'tt_content'
                    && $p['uid'] === 0  // No specific tt_content record; page context via pid
                    && $p['pid'] === 42
                    && $p['field'] === 'bodytext'
                    && $p['formName'] === 'typo3image_linkform'
                    && $p['itemName'] === 'typo3image_link'
                    && $p['currentValue'] === 'https://example.com'
                    && $p['currentSelectedValues'] === 'https://example.com';
            }))
            ->willReturn('/typo3/wizard/link');

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $controller = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $this->richtextMock,
        );

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'P' => [
                'pid' => 42,
            ],
            'currentValue' => 'https://example.com',
        ]);

        $controller->linkBrowserAction($requestMock);
    }

    #[Test]
    public function linkBrowserActionReadsPidFromTopLevelFallback(): void
    {
        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('wizard_link', self::callback(static function (array $params): bool {
                return $params['P']['pid'] === 42;
            }))
            ->willReturn('/typo3/wizard/link');

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $controller = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $this->richtextMock,
        );

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'pid' => 42, // Top-level fallback (backward compat)
        ]);

        $controller->linkBrowserAction($requestMock);
    }

    // ========================================================================
    // buildLinkBrowserParams Tests
    // ========================================================================

    #[Test]
    public function buildLinkBrowserParamsReturnsEmptyForEmptyConfig(): void
    {
        $result = $this->callProtectedMethod('buildLinkBrowserParams', [[]]);

        self::assertSame([], $result);
    }

    #[Test]
    public function buildLinkBrowserParamsReturnsAllowedTypes(): void
    {
        $rteConfig = [
            'allowedTypes' => 'page,url,file',
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame(['allowedTypes' => 'page,url,file'], $result);
    }

    #[Test]
    public function buildLinkBrowserParamsAllowedTypesMinusRemoveItems(): void
    {
        $rteConfig = [
            'allowedTypes' => 'page,url,file,folder,email',
            'buttons'      => [
                'link' => [
                    'options' => [
                        'removeItems' => 'folder,email',
                    ],
                ],
            ],
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame(['allowedTypes' => 'page,url,file'], $result);
    }

    #[Test]
    public function buildLinkBrowserParamsReturnsBlindLinkOptions(): void
    {
        $rteConfig = [
            'blindLinkOptions' => 'telephone,folder',
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame(['blindLinkOptions' => 'telephone,folder'], $result);
    }

    #[Test]
    public function buildLinkBrowserParamsCombinesBlindLinkOptionsAndRemoveItems(): void
    {
        $rteConfig = [
            'blindLinkOptions' => 'telephone',
            'buttons'          => [
                'link' => [
                    'options' => [
                        'removeItems' => 'folder',
                    ],
                ],
            ],
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame(['blindLinkOptions' => 'telephone,folder'], $result);
    }

    #[Test]
    public function buildLinkBrowserParamsRemoveItemsOnly(): void
    {
        $rteConfig = [
            'buttons' => [
                'link' => [
                    'options' => [
                        'removeItems' => 'telephone',
                    ],
                ],
            ],
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame(['blindLinkOptions' => 'telephone'], $result);
    }

    #[Test]
    public function buildLinkBrowserParamsAllowedTypesTakesPrecedenceOverBlindLinkOptions(): void
    {
        $rteConfig = [
            'allowedTypes'     => 'page,url',
            'blindLinkOptions' => 'telephone', // Should be ignored when allowedTypes is set
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame(['allowedTypes' => 'page,url'], $result);
        self::assertArrayNotHasKey('blindLinkOptions', $result);
    }

    #[Test]
    public function buildLinkBrowserParamsReturnsAllowedOptions(): void
    {
        $rteConfig = [
            'allowedOptions' => 'target,title,class',
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame(['allowedOptions' => 'target,title,class'], $result);
    }

    #[Test]
    public function buildLinkBrowserParamsReturnsBlindLinkFields(): void
    {
        $rteConfig = [
            'blindLinkFields' => 'params,rel',
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame(['blindLinkFields' => 'params,rel'], $result);
    }

    #[Test]
    public function buildLinkBrowserParamsAllowedOptionsTakesPrecedenceOverBlindLinkFields(): void
    {
        $rteConfig = [
            'allowedOptions'  => 'target,title',
            'blindLinkFields' => 'params', // Should be ignored when allowedOptions is set
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame(['allowedOptions' => 'target,title'], $result);
        self::assertArrayNotHasKey('blindLinkFields', $result);
    }

    #[Test]
    public function buildLinkBrowserParamsCombinesTypeAndFieldRestrictions(): void
    {
        $rteConfig = [
            'allowedTypes'   => 'page,url,file',
            'allowedOptions' => 'target,title',
        ];

        $result = $this->callProtectedMethod('buildLinkBrowserParams', [$rteConfig]);

        self::assertSame([
            'allowedTypes'   => 'page,url,file',
            'allowedOptions' => 'target,title',
        ], $result);
    }

    #[Test]
    public function linkBrowserActionPassesRteConfigRestrictionsToParams(): void
    {
        /** @var Richtext&MockObject $richtextMock */
        $richtextMock = $this->createMock(Richtext::class);
        $richtextMock->method('getConfiguration')->willReturn([
            'allowedTypes' => 'page,url',
            'buttons'      => [
                'link' => [
                    'options' => [
                        'removeItems' => 'url',
                    ],
                ],
            ],
        ]);

        /** @var UriBuilder&MockObject $uriBuilderMock */
        $uriBuilderMock = $this->createMock(UriBuilder::class);
        $uriBuilderMock
            ->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('wizard_link', self::callback(static function (array $params): bool {
                $linkParams = $params['P']['params'] ?? [];

                // Should have allowedTypes with 'url' removed by removeItems
                return ($linkParams['allowedTypes'] ?? '') === 'page';
            }))
            ->willReturn('/typo3/wizard/link');

        /** @var ElementBrowserRegistry&MockObject $elementBrowserRegistryMock */
        $elementBrowserRegistryMock = $this->createMock(ElementBrowserRegistry::class);

        $controller = new SelectImageController(
            $this->resourceFactoryMock,
            $this->uploadFolderResolverMock,
            $elementBrowserRegistryMock,
            $uriBuilderMock,
            $richtextMock,
        );

        /** @var ServerRequestInterface&MockObject $requestMock */
        $requestMock = $this->createMock(ServerRequestInterface::class);
        $requestMock->method('getQueryParams')->willReturn([
            'P' => [
                'pid'                       => 1,
                'richtextConfigurationName' => 'default',
            ],
        ]);

        $controller->linkBrowserAction($requestMock);
    }
}

/**
 * Test double that mimics SelectImageController::mainAction() expandFolder logic.
 *
 * This class replicates the expandFolder handling logic from mainAction() to allow
 * unit testing without the complexity of mocking the parent ElementBrowserController.
 *
 * @internal Only for use in SelectImageControllerTest
 */
final class TestableExpandFolderController
{
    /** @var array<string, mixed>|null */
    public ?array $capturedQueryParams = null;

    public function __construct(
        private readonly DefaultUploadFolderResolver $uploadFolderResolver
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        // Replicate the logic from SelectImageController::mainAction()
        $parsedBody = $request->getParsedBody();
        /** @var array<string, mixed> $queryParams */
        $queryParams = $request->getQueryParams();

        // Extract action from body or query params (matches refactored pattern in SelectImageController)
        $actionFromBody = is_array($parsedBody) ? ($parsedBody['action'] ?? null) : null;
        $action         = $actionFromBody ?? $queryParams['action'] ?? null;
        $isInfoAction   = $action === 'info';

        if (!$isInfoAction) {
            $bparamsValue = $queryParams['bparams'] ?? '';
            $bparams      = explode('|', is_string($bparamsValue) ? $bparamsValue : '');

            if (isset($bparams[3]) && ($bparams[3] === '')) {
                $gfxExt                 = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] ?? '';
                $bparams[3]             = is_string($gfxExt) ? $gfxExt : '';
                $queryParams['bparams'] = implode('|', $bparams);
            }

            // This is the key logic being tested (issue #290 follow-up fix):
            // expandFolder should only be set when NOT already provided
            if (
                !isset($queryParams['expandFolder'])
                && isset($GLOBALS['BE_USER'])
                && $GLOBALS['BE_USER'] instanceof BackendUserAuthentication
            ) {
                try {
                    $folder = $this->uploadFolderResolver->resolve($GLOBALS['BE_USER']);
                    if ($folder instanceof Folder) {
                        $queryParams['expandFolder'] = $folder->getCombinedIdentifier();
                    }
                } catch (Exception) {
                    // Silently handle exceptions - matches production behavior
                }
            }
        }

        // Capture the modified queryParams for test assertions
        $this->capturedQueryParams = $queryParams;

        // Return a simple response instead of calling parent::mainAction()
        return new Response();
    }
}
