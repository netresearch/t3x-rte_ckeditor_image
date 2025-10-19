<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Controller;

use Netresearch\RteCKEditorImage\Controller\ImageRenderingController;
use Netresearch\RteCKEditorImage\Utils\ProcessedFilesHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for ImageRenderingController.
 *
 * @covers \Netresearch\RteCKEditorImage\Controller\ImageRenderingController
 */
final class ImageRenderingControllerTest extends UnitTestCase
{
    private ImageRenderingController $subject;

    /** @var ResourceFactory&MockObject */
    private ResourceFactory $resourceFactoryMock;

    /** @var ProcessedFilesHandler&MockObject */
    private ProcessedFilesHandler $processedFilesHandlerMock;

    /** @var LogManager&MockObject */
    private LogManager $logManagerMock;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ResourceFactory&MockObject $resourceFactoryMock */
        $resourceFactoryMock = $this->createMock(ResourceFactory::class);

        /** @var ProcessedFilesHandler&MockObject $processedFilesHandlerMock */
        $processedFilesHandlerMock = $this->createMock(ProcessedFilesHandler::class);

        /** @var LogManager&MockObject $logManagerMock */
        $logManagerMock = $this->createMock(LogManager::class);

        $this->resourceFactoryMock       = $resourceFactoryMock;
        $this->processedFilesHandlerMock = $processedFilesHandlerMock;
        $this->logManagerMock            = $logManagerMock;

        $this->subject = new ImageRenderingController(
            $this->resourceFactoryMock,
            $this->processedFilesHandlerMock,
            $this->logManagerMock,
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
    public function isExternalImageReturnsTrueForHttpsUrls(): void
    {
        $result = $this->callProtectedMethod('isExternalImage', ['https://example.com/image.jpg']);

        self::assertTrue($result);
    }

    #[Test]
    public function isExternalImageReturnsTrueForHttpUrls(): void
    {
        $result = $this->callProtectedMethod('isExternalImage', ['http://example.com/image.jpg']);

        self::assertTrue($result);
    }

    #[Test]
    public function isExternalImageReturnsTrueForProtocolRelativeUrls(): void
    {
        $result = $this->callProtectedMethod('isExternalImage', ['//example.com/image.jpg']);

        self::assertTrue($result);
    }

    #[Test]
    public function isExternalImageReturnsFalseForLocalPaths(): void
    {
        $result = $this->callProtectedMethod('isExternalImage', ['/fileadmin/images/test.jpg']);

        self::assertFalse($result);
    }

    #[Test]
    public function isExternalImageReturnsFalseForRelativePaths(): void
    {
        $result = $this->callProtectedMethod('isExternalImage', ['fileadmin/images/test.jpg']);

        self::assertFalse($result);
    }

    #[Test]
    public function isExternalImageReturnsFalseForBackendProcessingUrls(): void
    {
        // Backend processing URLs should be reprocessed, not treated as external
        $result = $this->callProtectedMethod('isExternalImage', ['/typo3/image/process?token=abc123']);

        self::assertFalse($result);
    }

    #[Test]
    public function isExternalImageHandlesEmptyString(): void
    {
        $result = $this->callProtectedMethod('isExternalImage', ['']);

        self::assertFalse($result);
    }

    #[Test]
    public function isExternalImageHandlesCaseSensitivity(): void
    {
        // HTTP check should be case-insensitive
        $result1 = $this->callProtectedMethod('isExternalImage', ['HTTP://EXAMPLE.COM/IMAGE.JPG']);
        $result2 = $this->callProtectedMethod('isExternalImage', ['HtTpS://example.com/image.jpg']);

        self::assertTrue($result1);
        self::assertTrue($result2);
    }

    #[Test]
    public function getAttributeValueReturnsAttributeValueWhenPresent(): void
    {
        $attributes = ['alt' => 'Test alt text', 'title' => 'Test title'];

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);

        $result = $this->callProtectedMethod('getAttributeValue', ['alt', $attributes, $fileMock]);

        self::assertSame('Test alt text', $result);
    }

    #[Test]
    public function getAttributeValueReturnsFilePropertyWhenAttributeNotPresent(): void
    {
        $attributes = ['title' => 'Test title'];

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')->with('alt')->willReturn('File alt text');

        $result = $this->callProtectedMethod('getAttributeValue', ['alt', $attributes, $fileMock]);

        self::assertSame('File alt text', $result);
    }

    #[Test]
    public function getAttributeValueReturnsEmptyStringWhenBothMissing(): void
    {
        $attributes = [];

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')->with('alt')->willReturn(null);

        $result = $this->callProtectedMethod('getAttributeValue', ['alt', $attributes, $fileMock]);

        self::assertSame('', $result);
    }

    #[Test]
    public function getAttributeValuePrefersAttributeOverFileProperty(): void
    {
        $attributes = ['alt' => 'Attribute alt'];

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')->with('alt')->willReturn('File alt');

        $result = $this->callProtectedMethod('getAttributeValue', ['alt', $attributes, $fileMock]);

        self::assertSame('Attribute alt', $result);
    }

    #[Test]
    public function getAttributeValueHandlesNumericValues(): void
    {
        $attributes = ['width' => 800];

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);

        $result = $this->callProtectedMethod('getAttributeValue', ['width', $attributes, $fileMock]);

        self::assertSame('800', $result);
    }

    #[Test]
    public function getAttributeValueHandlesEmptyStringAttribute(): void
    {
        $attributes = ['alt' => ''];

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);

        $result = $this->callProtectedMethod('getAttributeValue', ['alt', $attributes, $fileMock]);

        self::assertSame('', $result);
    }

    #[Test]
    public function getAttributeValueCastsNullToEmptyString(): void
    {
        $attributes = [];

        /** @var File&MockObject $fileMock */
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')->willReturn(null);

        $result = $this->callProtectedMethod('getAttributeValue', ['alt', $attributes, $fileMock]);

        self::assertSame('', $result);
    }

    #[Test]
    public function getImageAttributesReturnsEmptyArrayWhenCObjIsNull(): void
    {
        // cObj is null by default in setUp
        $result = $this->callProtectedMethod('getImageAttributes', []);

        self::assertSame([], $result);
    }

    #[Test]
    public function getImageAttributesReturnsParametersFromCObj(): void
    {
        $expectedParams = [
            'src'   => '/path/to/image.jpg',
            'alt'   => 'Test image',
            'width' => '800',
        ];

        /** @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer&MockObject $cObjMock */
        $cObjMock             = $this->createMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
        $cObjMock->parameters = $expectedParams;

        $this->subject->setContentObjectRenderer($cObjMock);

        $result = $this->callProtectedMethod('getImageAttributes', []);

        self::assertSame($expectedParams, $result);
    }

    #[Test]
    public function getImageAttributesReturnsEmptyArrayWhenParametersNotSet(): void
    {
        /** @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer&MockObject $cObjMock */
        $cObjMock = $this->createMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);
        // Don't set parameters property

        $this->subject->setContentObjectRenderer($cObjMock);

        $result = $this->callProtectedMethod('getImageAttributes', []);

        self::assertSame([], $result);
    }

    #[Test]
    public function setContentObjectRendererStoresInstance(): void
    {
        /** @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer&MockObject $cObjMock */
        $cObjMock = $this->createMock(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class);

        $this->subject->setContentObjectRenderer($cObjMock);

        // Verify by calling getImageAttributes which uses cObj
        $cObjMock->parameters = ['test' => 'value'];
        $result               = $this->callProtectedMethod('getImageAttributes', []);

        self::assertSame(['test' => 'value'], $result);
    }
}
