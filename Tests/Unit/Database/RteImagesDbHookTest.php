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
use ReflectionClass;
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

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresFieldNotInTca(): void
    {
        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['non_existing_field' => 'some value'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Ensure the field does not exist in TCA
        unset($GLOBALS['TCA']['tt_content']['columns']['non_existing_field']);

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // Field array should remain unchanged
        self::assertSame(['non_existing_field' => 'some value'], $fieldArray);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresFieldWithoutTypeInConfig(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['bodytext' => 'content'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration without 'type' key
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'rows' => 5,
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // Field array should remain unchanged
        self::assertSame(['bodytext' => 'content'], $fieldArray);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresNonTextTypeField(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['header' => 'A header'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration with type 'input'
        $GLOBALS['TCA']['tt_content']['columns']['header']['config'] = [
            'type' => 'input',
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // Field array should remain unchanged
        self::assertSame(['header' => 'A header'], $fieldArray);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresFieldWithoutEnableRichtext(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['bodytext' => 'plain text'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration without enableRichtext
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type' => 'text',
            'rows' => 10,
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // Field array should remain unchanged
        self::assertSame(['bodytext' => 'plain text'], $fieldArray);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresFieldWithEnableRichtextFalse(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['bodytext' => 'plain text'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration with enableRichtext = false
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type'           => 'text',
            'enableRichtext' => false,
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // Field array should remain unchanged
        self::assertSame(['bodytext' => 'plain text'], $fieldArray);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresNullFieldValue(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['bodytext' => null];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration with enableRichtext = true
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // Field array should remain unchanged when value is null
        self::assertNull($fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayProcessesMultipleFields(): void
    {
        // This test requires Environment initialization for GeneralUtility::getIndpEnv()
        // which is called in modifyRteField(). Needs functional test setup.
        self::markTestSkipped('Test requires functional test setup (Environment initialization)');
    }

    public function xdisabled_processDatamapPostProcessFieldArrayProcessesMultipleFields(): void
    {
        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = [
            'bodytext'    => 'text without images',
            'header'      => 'Not an RTE field',
            'description' => 'another text without images',
        ];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];
        $GLOBALS['TCA']['tt_content']['columns']['header']['config'] = [
            'type' => 'input',
        ];
        $GLOBALS['TCA']['tt_content']['columns']['description']['config'] = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // RTE fields should be processed (even if content unchanged without images)
        // Non-RTE fields should remain unchanged
        self::assertSame('Not an RTE field', $fieldArray['header']);
    }

    /**
     * @todo Convert to functional test - requires TYPO3 Environment for GeneralUtility::getIndpEnv()
     */
    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesEmptyStringValue(): void
    {
        // Skip: modifyRteField() calls GeneralUtility::getIndpEnv() which requires TYPO3 Environment
        self::markTestSkipped('Test requires functional test setup (Environment initialization)');
    }

    /**
     * @todo Convert to functional test - requires TYPO3 Environment for GeneralUtility::getIndpEnv()
     */
    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesContentWithoutImgTags(): void
    {
        // Skip: modifyRteField() calls GeneralUtility::getIndpEnv() which requires TYPO3 Environment
        self::markTestSkipped('Test requires functional test setup (Environment initialization)');
    }

    #[Test]
    public function getSafeIpForExternalFetchBlocksPrivateIpv4Ranges(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('getSafeIpForExternalFetch');

        // Test private IPv4 ranges
        $privateUrls = [
            'http://10.0.0.1/image.jpg',       // 10.0.0.0/8
            'http://172.16.0.1/image.jpg',     // 172.16.0.0/12
            'http://192.168.1.1/image.jpg',    // 192.168.0.0/16
            'http://127.0.0.1/image.jpg',      // Loopback
            'http://169.254.1.1/image.jpg',    // Link-local
        ];

        foreach ($privateUrls as $url) {
            $result = $method->invoke($this->subject, $url);
            self::assertNull($result, "Expected {$url} to be blocked");
        }
    }

    #[Test]
    public function getSafeIpForExternalFetchBlocksCloudMetadataEndpoints(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('getSafeIpForExternalFetch');
        // Test cloud metadata endpoints
        $metadataUrls = [
            'http://169.254.169.254/latest/meta-data',
            'http://metadata.google.internal/computeMetadata/v1/',
        ];

        foreach ($metadataUrls as $url) {
            $result = $method->invoke($this->subject, $url);
            self::assertNull($result, "Expected {$url} to be blocked");
        }
    }

    #[Test]
    public function getSafeIpForExternalFetchReturnsNullForInvalidUrl(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('getSafeIpForExternalFetch');
        // Test invalid URLs
        $result = $method->invoke($this->subject, 'not-a-valid-url');
        self::assertNull($result);
    }

    #[Test]
    public function getSafeIpForExternalFetchReturnsNullForUrlWithoutHost(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('getSafeIpForExternalFetch');
        // Test URL without host
        $result = $method->invoke($this->subject, '/relative/path/image.jpg');
        self::assertNull($result);
    }

    #[Test]
    public function isValidImageMimeTypeAcceptsJpegImages(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('isValidImageMimeType');
        // Create minimal valid JPEG data (JPEG magic bytes: FF D8 FF)
        $jpegData = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";

        $result = $method->invoke($this->subject, $jpegData);
        self::assertTrue($result);
    }

    #[Test]
    public function isValidImageMimeTypeAcceptsPngImages(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('isValidImageMimeType');
        // Create minimal valid PNG data (PNG magic bytes)
        $pngData = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x00\x00\x00\x00:~\x9bU\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82";

        $result = $method->invoke($this->subject, $pngData);
        self::assertTrue($result);
    }

    #[Test]
    public function isValidImageMimeTypeRejectsSvgImages(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('isValidImageMimeType');
        // SVG content
        $svgData = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100"/></svg>';

        $result = $method->invoke($this->subject, $svgData);
        self::assertFalse($result, 'SVG should be rejected per ADR-003');
    }

    #[Test]
    public function isValidImageMimeTypeRejectsHtmlContent(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('isValidImageMimeType');
        // HTML content masquerading as image
        $htmlData = '<html><body><script>alert("xss")</script></body></html>';

        $result = $method->invoke($this->subject, $htmlData);
        self::assertFalse($result);
    }

    #[Test]
    public function matchStyleAttributeExtractsWidthFromStyleAttribute(): void
    {
        $reflection     = new ReflectionClass($this->subject);
        $method         = $reflection->getMethod('matchStyleAttribute');
        $styleAttribute = 'width: 300px; height: 200px;';
        $result         = $method->invoke($this->subject, $styleAttribute, 'width');

        self::assertSame('300', $result);
    }

    #[Test]
    public function matchStyleAttributeExtractsHeightFromStyleAttribute(): void
    {
        $reflection     = new ReflectionClass($this->subject);
        $method         = $reflection->getMethod('matchStyleAttribute');
        $styleAttribute = 'width: 300px; height: 200px;';
        $result         = $method->invoke($this->subject, $styleAttribute, 'height');

        self::assertSame('200', $result);
    }

    #[Test]
    public function matchStyleAttributeHandlesStyleWithSpaces(): void
    {
        $reflection     = new ReflectionClass($this->subject);
        $method         = $reflection->getMethod('matchStyleAttribute');
        $styleAttribute = 'width  :   250  px;';
        $result         = $method->invoke($this->subject, $styleAttribute, 'width');

        self::assertSame('250', $result);
    }

    #[Test]
    public function matchStyleAttributeReturnsNullWhenAttributeNotFound(): void
    {
        $reflection     = new ReflectionClass($this->subject);
        $method         = $reflection->getMethod('matchStyleAttribute');
        $styleAttribute = 'color: red;';
        $result         = $method->invoke($this->subject, $styleAttribute, 'width');

        self::assertNull($result);
    }

    #[Test]
    public function matchStyleAttributeIsCaseInsensitive(): void
    {
        $reflection     = new ReflectionClass($this->subject);
        $method         = $reflection->getMethod('matchStyleAttribute');
        $styleAttribute = 'WIDTH: 150px;';
        $result         = $method->invoke($this->subject, $styleAttribute, 'width');

        self::assertSame('150', $result);
    }

    #[Test]
    public function extractFromAttributeValueOrStylePrefersStyleOverAttribute(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('extractFromAttributeValueOrStyle');
        $attributes = [
            'width' => '100',
            'style' => 'width: 200px;',
        ];

        $result = $method->invoke($this->subject, $attributes, 'width');
        self::assertSame('200', $result, 'Style attribute value should take precedence');
    }

    #[Test]
    public function extractFromAttributeValueOrStyleReturnsAttributeWhenNoStyle(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('extractFromAttributeValueOrStyle');
        $attributes = [
            'width' => '100',
        ];

        $result = $method->invoke($this->subject, $attributes, 'width');
        self::assertSame('100', $result);
    }

    #[Test]
    public function extractFromAttributeValueOrStyleReturnsNullWhenNotFound(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('extractFromAttributeValueOrStyle');
        $attributes = [
            'alt' => 'Image description',
        ];

        $result = $method->invoke($this->subject, $attributes, 'width');
        self::assertNull($result);
    }

    #[Test]
    public function getImageWidthFromAttributesReturnsIntegerWidth(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('getImageWidthFromAttributes');
        $attributes = ['width' => '250'];
        $result     = $method->invoke($this->subject, $attributes);

        // assertSame verifies both type and value
        self::assertSame(250, $result);
    }

    #[Test]
    public function getImageWidthFromAttributesReturnsZeroWhenNotSet(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('getImageWidthFromAttributes');
        $attributes = ['alt' => 'Image'];
        $result     = $method->invoke($this->subject, $attributes);

        self::assertSame(0, $result);
    }

    #[Test]
    public function getImageHeightFromAttributesReturnsIntegerHeight(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('getImageHeightFromAttributes');
        $attributes = ['height' => '180'];
        $result     = $method->invoke($this->subject, $attributes);

        // assertSame verifies both type and value
        self::assertSame(180, $result);
    }

    #[Test]
    public function getImageHeightFromAttributesReturnsZeroWhenNotSet(): void
    {
        $reflection = new ReflectionClass($this->subject);
        $method     = $reflection->getMethod('getImageHeightFromAttributes');
        $attributes = ['alt' => 'Image'];
        $result     = $method->invoke($this->subject, $attributes);

        self::assertSame(0, $result);
    }
}
