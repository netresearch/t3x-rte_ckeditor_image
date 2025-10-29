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
                        'allowSvgImages'      => false,
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
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(function ($extension, $key) {
                self::assertSame('rte_ckeditor_image', $extension);

                return match ($key) {
                    'fetchExternalImages' => true,
                    'allowSvgImages'      => false,
                    default               => null,
                };
            });

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
    public function constructorLoadsExtensionConfigurationForAllowSvgImages(): void
    {
        /** @var ExtensionConfiguration&MockObject $configMock */
        $configMock = $this->createMock(ExtensionConfiguration::class);
        $configMock
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(function ($extension, $key) {
                self::assertSame('rte_ckeditor_image', $extension);

                return match ($key) {
                    'fetchExternalImages' => false,
                    'allowSvgImages'      => true,
                    default               => null,
                };
            });

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

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesNonRteField(): void
    {
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
