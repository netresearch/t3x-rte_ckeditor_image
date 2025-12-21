<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Database;

use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\DefaultUploadFolderResolver;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for RteImagesDbHook.
 */
#[CoversClass(RteImagesDbHook::class)]
final class RteImagesDbHookTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private RteImagesDbHook $subject;

    /** @var ExtensionConfiguration&MockObject */
    private ExtensionConfiguration $extensionConfigurationMock;

    /** @var LogManager&MockObject */
    private LogManager $logManagerMock;

    /** @var ResourceFactory&MockObject */
    private ResourceFactory $resourceFactoryMock;

    /** @var Context&MockObject */
    private Context $contextMock;

    /** @var RequestFactory&MockObject */
    private RequestFactory $requestFactoryMock;

    /** @var DefaultUploadFolderResolver&MockObject */
    private DefaultUploadFolderResolver $uploadFolderResolverMock;

    /** @var Logger&MockObject */
    private Logger $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create all required mocks
        /** @var ExtensionConfiguration&MockObject $extensionConfigurationMock */
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);

        /** @var LogManager&MockObject $logManagerMock */
        $logManagerMock = $this->createMock(LogManager::class);

        /** @var ResourceFactory&MockObject $resourceFactoryMock */
        $resourceFactoryMock = $this->createMock(ResourceFactory::class);

        /** @var Context&MockObject $contextMock */
        $contextMock = $this->createMock(Context::class);

        /** @var RequestFactory&MockObject $requestFactoryMock */
        $requestFactoryMock = $this->createMock(RequestFactory::class);

        /** @var DefaultUploadFolderResolver&MockObject $uploadFolderResolverMock */
        $uploadFolderResolverMock = $this->createMock(DefaultUploadFolderResolver::class);

        /** @var Logger&MockObject $loggerMock */
        $loggerMock = $this->createMock(Logger::class);

        // Configure extension configuration mock
        $extensionConfigurationMock
            ->method('get')
            ->willReturnCallback(function ($extension, $key) {
                if ($extension === 'rte_ckeditor_image') {
                    return match ($key) {
                        'fetchExternalImages' => false,
                        default               => null,
                    };
                }

                return null;
            });

        // Configure log manager to return logger mock
        $logManagerMock
            ->method('getLogger')
            ->with(RteImagesDbHook::class)
            ->willReturn($loggerMock);

        // Assign mocks to properties
        $this->extensionConfigurationMock = $extensionConfigurationMock;
        $this->logManagerMock             = $logManagerMock;
        $this->resourceFactoryMock        = $resourceFactoryMock;
        $this->contextMock                = $contextMock;
        $this->requestFactoryMock         = $requestFactoryMock;
        $this->uploadFolderResolverMock   = $uploadFolderResolverMock;
        $this->loggerMock                 = $loggerMock;

        // Create subject with all dependencies
        $this->subject = new RteImagesDbHook(
            $this->extensionConfigurationMock,
            $this->logManagerMock,
            $this->resourceFactoryMock,
            $this->contextMock,
            $this->requestFactoryMock,
            $this->uploadFolderResolverMock,
        );
    }

    #[Test]
    public function constructorInitializesWithDependencyInjection(): void
    {
        // Verify subject was created successfully with all dependencies
        self::assertInstanceOf(RteImagesDbHook::class, $this->subject);
    }

    #[Test]
    public function constructorLoadsExtensionConfigurationForFetchExternalImages(): void
    {
        /** @var ExtensionConfiguration&MockObject $configMock */
        $configMock = $this->createMock(ExtensionConfiguration::class);
        $configMock
            ->expects(self::once())
            ->method('get')
            ->with('rte_ckeditor_image', 'fetchExternalImages')
            ->willReturn(true);

        new RteImagesDbHook(
            $configMock,
            $this->logManagerMock,
            $this->resourceFactoryMock,
            $this->contextMock,
            $this->requestFactoryMock,
            $this->uploadFolderResolverMock,
        );
    }

    #[Test]
    public function constructorRequestsLoggerFromLogManager(): void
    {
        $this->logManagerMock
            ->expects(self::once())
            ->method('getLogger')
            ->with(RteImagesDbHook::class)
            ->willReturn($this->loggerMock);

        new RteImagesDbHook(
            $this->extensionConfigurationMock,
            $this->logManagerMock,
            $this->resourceFactoryMock,
            $this->contextMock,
            $this->requestFactoryMock,
            $this->uploadFolderResolverMock,
        );
    }

    /**
     * @todo Convert to functional test - requires proper TYPO3 DI container for BackendUtility::getTCAtypeValue()
     */
    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesNonRteField(): void
    {
        // TYPO3 v14+ requires TcaSchemaFactory in DI container for BackendUtility::getTCAtypeValue()
        // Unit tests don't have proper DI setup, so skip on v14+
        // Version detection: GridColumnItem::getRow() was introduced as a new public API in TYPO3 v14
        // and does not exist in v13. We use this feature check instead of a TYPO3 version API because
        // the unit-test environment does not bootstrap the full DI container needed for version lookup.
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+ (TcaSchemaFactory needs DI container)');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['bodytext' => 'plain text content'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type' => 'text',
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // Field array should remain unchanged for non-RTE fields
        self::assertSame('plain text content', $fieldArray['bodytext']);
    }
}
