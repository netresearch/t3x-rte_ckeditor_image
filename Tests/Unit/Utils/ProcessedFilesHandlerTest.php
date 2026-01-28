<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Utils;

use Exception;
use Netresearch\RteCKEditorImage\Utils\ProcessedFilesHandler;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for ProcessedFilesHandler.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(ProcessedFilesHandler::class)]
final class ProcessedFilesHandlerTest extends UnitTestCase
{
    private ProcessedFilesHandler $subject;

    /** @var ImageService&MockObject */
    private ImageService $imageServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock for ImageService dependency
        /** @var ImageService&MockObject $imageServiceMock */
        $imageServiceMock = $this->createMock(ImageService::class);

        $this->imageServiceMock = $imageServiceMock;
        $this->subject          = new ProcessedFilesHandler($this->imageServiceMock);
    }

    #[Test]
    public function createProcessedFileReturnsProcessedFileOnSuccess(): void
    {
        $fileMock          = $this->createMock(File::class);
        $processedFileMock = $this->createMock(ProcessedFile::class);

        $this->imageServiceMock
            ->expects(self::once())
            ->method('applyProcessingInstructions')
            ->with($fileMock, ['width' => '200c', 'height' => '200c'])
            ->willReturn($processedFileMock);

        $imageConfiguration = [
            'width'  => '200c',
            'height' => '200c',
        ];

        $result = $this->subject->createProcessedFile($fileMock, $imageConfiguration);

        self::assertSame($processedFileMock, $result);
    }

    #[Test]
    public function createProcessedFileThrowsExceptionWhenProcessingFails(): void
    {
        $fileMock = $this->createMock(File::class);

        $this->imageServiceMock
            ->expects(self::once())
            ->method('applyProcessingInstructions')
            ->willThrowException(new Exception('Processing failed'));

        $imageConfiguration = ['width' => '100c'];

        $this->expectException(Exception::class);
        $this->expectExceptionCode(1716565499);
        $this->expectExceptionMessage('Could not create processed file');

        $this->subject->createProcessedFile($fileMock, $imageConfiguration);
    }

    #[Test]
    public function createProcessedFilePassesConfigurationCorrectly(): void
    {
        $fileMock          = $this->createMock(File::class);
        $processedFileMock = $this->createMock(ProcessedFile::class);

        $expectedConfig = [
            'width'  => '800c',
            'height' => '600c',
            'crop'   => '{"x":10,"y":10,"width":780,"height":580}',
        ];

        $this->imageServiceMock
            ->expects(self::once())
            ->method('applyProcessingInstructions')
            ->with($fileMock, $expectedConfig)
            ->willReturn($processedFileMock);

        $result = $this->subject->createProcessedFile($fileMock, $expectedConfig);

        self::assertInstanceOf(ProcessedFile::class, $result);
    }

    #[Test]
    public function createProcessedFileHandlesEmptyConfiguration(): void
    {
        $fileMock          = $this->createMock(File::class);
        $processedFileMock = $this->createMock(ProcessedFile::class);

        $this->imageServiceMock
            ->expects(self::once())
            ->method('applyProcessingInstructions')
            ->with($fileMock, [])
            ->willReturn($processedFileMock);

        $result = $this->subject->createProcessedFile($fileMock, []);

        self::assertSame($processedFileMock, $result);
    }

    #[Test]
    public function createProcessedFileHandlesComplexConfiguration(): void
    {
        $fileMock          = $this->createMock(File::class);
        $processedFileMock = $this->createMock(ProcessedFile::class);

        $complexConfig = [
            'width'     => '1920m',
            'height'    => '1080m',
            'minWidth'  => '800',
            'minHeight' => '600',
            'maxWidth'  => '2000',
            'maxHeight' => '1500',
            'crop'      => '{"x":0,"y":0,"width":1920,"height":1080}',
        ];

        $this->imageServiceMock
            ->expects(self::once())
            ->method('applyProcessingInstructions')
            ->with($fileMock, $complexConfig)
            ->willReturn($processedFileMock);

        $result = $this->subject->createProcessedFile($fileMock, $complexConfig);

        self::assertInstanceOf(ProcessedFile::class, $result);
    }
}
