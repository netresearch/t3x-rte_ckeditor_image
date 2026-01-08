<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service;

use Netresearch\RteCKEditorImage\Service\ImageResolverService;
use Netresearch\RteCKEditorImage\Utils\ProcessedFilesHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Test case for ImageResolverService.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class ImageResolverServiceTest extends TestCase
{
    private ImageResolverService $service;

    /** @var ResourceFactory&\PHPUnit\Framework\MockObject\MockObject */
    private ResourceFactory $resourceFactoryMock;

    /** @var ProcessedFilesHandler&\PHPUnit\Framework\MockObject\MockObject */
    private ProcessedFilesHandler $processedFilesHandlerMock;

    /** @var LogManager&\PHPUnit\Framework\MockObject\MockObject */
    private LogManager $logManagerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourceFactoryMock       = $this->createMock(ResourceFactory::class);
        $this->processedFilesHandlerMock = $this->createMock(ProcessedFilesHandler::class);
        $this->logManagerMock            = $this->createMock(LogManager::class);

        // Mock logger to prevent null reference
        $loggerMock = $this->createMock(\Psr\Log\LoggerInterface::class);
        $this->logManagerMock->method('getLogger')->willReturn($loggerMock);

        $this->service = new ImageResolverService(
            $this->resourceFactoryMock,
            $this->processedFilesHandlerMock,
            $this->logManagerMock,
        );
    }

    /**
     * Helper method to call private methods for testing.
     *
     * @param object  $object     Object instance
     * @param string  $methodName Method name to call
     * @param mixed[] $parameters Parameters to pass
     *
     * @return mixed
     */
    protected function callPrivateMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionMethod($object::class, $methodName);

        return $reflection->invokeArgs($object, $parameters);
    }

    /**
     * Create a mock File object with specified properties.
     *
     * @param array<string, mixed> $properties File properties
     *
     * @return File&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createFileMock(array $properties = []): File
    {
        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')
            ->willReturnCallback(static function (string $key) use ($properties): mixed {
                return $properties[$key] ?? null;
            });

        return $fileMock;
    }

    /**
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/502
     */
    #[Test]
    public function getAttributeValueReturnsEmptyStringWhenOverrideIsTrue(): void
    {
        // Regression test for #502: data-alt-override="true" with alt="" must return ""
        // Previously returned literal "true" instead of empty string
        $fileMock = $this->createFileMock(['alt' => 'File Alt Text']);

        $attributes = [
            'alt'               => '',
            'data-alt-override' => 'true',
        ];

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', ['alt', $attributes, $fileMock]);

        // The override flag "true" means "use the alt attribute as-is, don't fall back to file metadata"
        self::assertSame('', $result, 'When data-alt-override="true" and alt="", the result should be empty string');
    }

    /**
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/502
     */
    #[Test]
    public function getAttributeValueReturnsEmptyStringForTitleWhenOverrideIsTrue(): void
    {
        // Regression test for #502: same fix applies to title attribute
        $fileMock = $this->createFileMock(['title' => 'File Title Text']);

        $attributes = [
            'title'               => '',
            'data-title-override' => 'true',
        ];

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', ['title', $attributes, $fileMock]);

        self::assertSame('', $result, 'When data-title-override="true" and title="", the result should be empty string');
    }

    /**
     * Data provider for override attribute tests.
     *
     * @return array<string, array{attribute: string, attributes: array<string, string>, fileProperty: string, expected: string|null}>
     */
    public static function overrideAttributeDataProvider(): array
    {
        return [
            'alt override true with empty alt' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => '', 'data-alt-override' => 'true'],
                'fileProperty' => 'File Alt',
                'expected'     => '',
            ],
            'alt override true with explicit alt value' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => 'Explicit Alt', 'data-alt-override' => 'true'],
                'fileProperty' => 'File Alt',
                'expected'     => 'Explicit Alt',
            ],
            'alt override with custom value' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => '', 'data-alt-override' => 'Custom Override Alt'],
                'fileProperty' => 'File Alt',
                'expected'     => 'Custom Override Alt',
            ],
            'title override true with empty title' => [
                'attribute'    => 'title',
                'attributes'   => ['title' => '', 'data-title-override' => 'true'],
                'fileProperty' => 'File Title',
                'expected'     => '',
            ],
            'title override true with explicit title value' => [
                'attribute'    => 'title',
                'attributes'   => ['title' => 'Explicit Title', 'data-title-override' => 'true'],
                'fileProperty' => 'File Title',
                'expected'     => 'Explicit Title',
            ],
            'title override with custom value' => [
                'attribute'    => 'title',
                'attributes'   => ['title' => '', 'data-title-override' => 'Custom Override Title'],
                'fileProperty' => 'File Title',
                'expected'     => 'Custom Override Title',
            ],
            'no override falls back to attribute' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => 'My Alt'],
                'fileProperty' => 'File Alt',
                'expected'     => 'My Alt',
            ],
            'no override and no attribute falls back to file property' => [
                'attribute'    => 'alt',
                'attributes'   => [],
                'fileProperty' => 'File Alt',
                'expected'     => 'File Alt',
            ],
            'no override and empty attribute falls back to file property' => [
                'attribute'    => 'alt',
                'attributes'   => ['alt' => ''],
                'fileProperty' => 'File Alt',
                'expected'     => 'File Alt',
            ],
        ];
    }

    /**
     * @param array<string, string> $attributes
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/502
     */
    #[Test]
    #[DataProvider('overrideAttributeDataProvider')]
    public function getAttributeValueHandlesOverrideCorrectly(
        string $attribute,
        array $attributes,
        string $fileProperty,
        ?string $expected,
    ): void {
        $fileMock = $this->createFileMock([$attribute => $fileProperty]);

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', [$attribute, $attributes, $fileMock]);

        self::assertSame($expected, $result);
    }

    #[Test]
    public function getAttributeValueReturnsNullForEmptyAttributeName(): void
    {
        $fileMock = $this->createFileMock([]);

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', ['', [], $fileMock]);

        self::assertNull($result);
    }

    #[Test]
    public function getAttributeValueReturnsNullWhenNoValueAvailable(): void
    {
        $fileMock = $this->createFileMock([]); // No file properties

        $attributes = []; // No attributes

        $result = $this->callPrivateMethod($this->service, 'getAttributeValue', ['alt', $attributes, $fileMock]);

        self::assertNull($result);
    }
}
