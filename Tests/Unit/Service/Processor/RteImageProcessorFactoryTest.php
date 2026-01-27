<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service\Processor;

use Netresearch\RteCKEditorImage\Service\Builder\ImageTagBuilderInterface;
use Netresearch\RteCKEditorImage\Service\Environment\EnvironmentInfoInterface;
use Netresearch\RteCKEditorImage\Service\Fetcher\ExternalImageFetcherInterface;
use Netresearch\RteCKEditorImage\Service\Parser\ImageTagParserInterface;
use Netresearch\RteCKEditorImage\Service\Processor\RteImageProcessor;
use Netresearch\RteCKEditorImage\Service\Processor\RteImageProcessorFactory;
use Netresearch\RteCKEditorImage\Service\Processor\RteImageProcessorInterface;
use Netresearch\RteCKEditorImage\Service\Resolver\ImageFileResolverInterface;
use Netresearch\RteCKEditorImage\Service\Security\SecurityValidatorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Resource\DefaultUploadFolderResolver;

/**
 * Test case for RteImageProcessorFactory.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(RteImageProcessorFactory::class)]
class RteImageProcessorFactoryTest extends TestCase
{
    private ImageTagParserInterface&MockObject $parser;
    private ImageTagBuilderInterface&MockObject $builder;
    private ImageFileResolverInterface&MockObject $fileResolver;
    private ExternalImageFetcherInterface&MockObject $externalFetcher;
    private EnvironmentInfoInterface&MockObject $environmentInfo;
    private SecurityValidatorInterface&MockObject $securityValidator;
    private Context&MockObject $context;
    private DefaultUploadFolderResolver&MockObject $uploadFolderResolver;
    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser                 = $this->createMock(ImageTagParserInterface::class);
        $this->builder                = $this->createMock(ImageTagBuilderInterface::class);
        $this->fileResolver           = $this->createMock(ImageFileResolverInterface::class);
        $this->externalFetcher        = $this->createMock(ExternalImageFetcherInterface::class);
        $this->environmentInfo        = $this->createMock(EnvironmentInfoInterface::class);
        $this->securityValidator      = $this->createMock(SecurityValidatorInterface::class);
        $this->context                = $this->createMock(Context::class);
        $this->uploadFolderResolver   = $this->createMock(DefaultUploadFolderResolver::class);
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->logger                 = $this->createMock(LoggerInterface::class);
    }

    private function createFactory(): RteImageProcessorFactory
    {
        return new RteImageProcessorFactory(
            $this->parser,
            $this->builder,
            $this->fileResolver,
            $this->externalFetcher,
            $this->environmentInfo,
            $this->securityValidator,
            $this->context,
            $this->uploadFolderResolver,
            $this->extensionConfiguration,
            $this->logger,
        );
    }

    // ========================================================================
    // create() Tests
    // ========================================================================

    #[Test]
    public function createReturnsRteImageProcessorInterface(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with('rte_ckeditor_image', 'fetchExternalImages')
            ->willReturn(false);

        $factory = $this->createFactory();
        $result  = $factory->create();

        self::assertInstanceOf(RteImageProcessorInterface::class, $result);
    }

    #[Test]
    public function createReturnsRteImageProcessorInstance(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with('rte_ckeditor_image', 'fetchExternalImages')
            ->willReturn(false);

        $factory = $this->createFactory();
        $result  = $factory->create();

        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    // ========================================================================
    // Configuration Reading Tests
    // ========================================================================

    #[Test]
    public function createReadsFetchExternalImagesConfiguration(): void
    {
        $this->extensionConfiguration
            ->expects($this->once())
            ->method('get')
            ->with('rte_ckeditor_image', 'fetchExternalImages')
            ->willReturn(true);

        $factory = $this->createFactory();
        $result  = $factory->create();

        // Configuration was read and processor was created
        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    #[Test]
    public function createHandlesTrueConfigurationValue(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->willReturn(true);

        $factory = $this->createFactory();
        $result  = $factory->create();

        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    #[Test]
    public function createHandlesFalseConfigurationValue(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->willReturn(false);

        $factory = $this->createFactory();
        $result  = $factory->create();

        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    #[Test]
    public function createHandlesStringTrueConfigurationValue(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->willReturn('1');

        $factory = $this->createFactory();
        $result  = $factory->create();

        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    #[Test]
    public function createHandlesStringFalseConfigurationValue(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->willReturn('0');

        $factory = $this->createFactory();
        $result  = $factory->create();

        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    #[Test]
    public function createHandlesIntegerConfigurationValue(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->willReturn(1);

        $factory = $this->createFactory();
        $result  = $factory->create();

        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    // ========================================================================
    // Error Handling Tests
    // ========================================================================

    #[Test]
    public function createDefaultsToFalseOnConfigurationException(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->willThrowException(new RuntimeException('Configuration not found'));

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Failed to read fetchExternalImages configuration, using default value',
                self::callback(static function (mixed $context): bool {
                    return is_array($context)
                        && isset($context['exception'])
                        && is_string($context['exception'])
                        && str_contains($context['exception'], 'Configuration not found');
                }),
            );

        $factory = $this->createFactory();
        $result  = $factory->create();

        // Should still return a processor with default value (false)
        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    #[Test]
    public function createHandlesInvalidReturnTypeGracefully(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->willReturn(null);

        $factory = $this->createFactory();
        $result  = $factory->create();

        // null should be cast to false (bool)
        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    #[Test]
    public function createHandlesArrayConfigurationValue(): void
    {
        // Edge case: configuration returns an array instead of scalar
        $this->extensionConfiguration
            ->method('get')
            ->willReturn(['some' => 'value']);

        $factory = $this->createFactory();
        $result  = $factory->create();

        // Array should be cast to true (non-empty array = truthy)
        self::assertInstanceOf(RteImageProcessor::class, $result);
    }

    // ========================================================================
    // Multiple Calls Tests
    // ========================================================================

    #[Test]
    public function createReturnsNewInstanceEachTime(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->willReturn(false);

        $factory = $this->createFactory();

        $result1 = $factory->create();
        $result2 = $factory->create();

        // Each call should create a new instance
        self::assertNotSame($result1, $result2);
    }

    #[Test]
    public function createReadsConfigurationEachTime(): void
    {
        $this->extensionConfiguration
            ->expects($this->exactly(2))
            ->method('get')
            ->with('rte_ckeditor_image', 'fetchExternalImages')
            ->willReturn(false);

        $factory = $this->createFactory();

        $factory->create();
        $factory->create();

        // Assertion is in the expects() call above
    }
}
