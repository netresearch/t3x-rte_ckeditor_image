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
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Test case for ImageRenderingController.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
class ImageRenderingControllerTest extends TestCase
{
    /**
     * Helper method to create controller with mocked dependencies.
     *
     * @return ImageRenderingController
     */
    protected function createController(): ImageRenderingController
    {
        $resourceFactory       = $this->createMock(ResourceFactory::class);
        $processedFilesHandler = $this->createMock(ProcessedFilesHandler::class);
        $logManager            = $this->createMock(LogManager::class);
        $logger                = $this->createMock(\TYPO3\CMS\Core\Log\Logger::class);

        // Mock LogManager to return proper Logger instance
        $logManager->method('getLogger')->willReturn($logger);

        return new ImageRenderingController(
            $resourceFactory,
            $processedFilesHandler,
            $logManager,
        );
    }

    /**
     * Helper method to call protected methods for testing.
     *
     * @param object  $object     Object instance
     * @param string  $methodName Method name to call
     * @param mixed[] $parameters Parameters to pass
     *
     * @return mixed
     */
    protected function callProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionMethod($object::class, $methodName);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $parameters);
    }

    public function testShouldSkipProcessingReturnsTrueWhenNoScaleEnabled(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getProperty')->willReturnMap([
            ['width', 800],
            ['height', 600],
        ]);

        $imageConfig = ['width' => 1920, 'height' => 1080]; // Different dimensions
        $noScale     = true; // noScale enabled - should skip regardless of dimensions

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
        ]);

        self::assertTrue($result, 'Should skip processing when noScale is enabled');
    }

    public function testShouldSkipProcessingReturnsTrueWhenDimensionsMatchExactly(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getProperty')->willReturnMap([
            ['width', 1920],
            ['height', 1080],
        ]);

        $imageConfig = ['width' => 1920, 'height' => 1080]; // Exact match
        $noScale     = false; // Auto-detection should work

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
        ]);

        self::assertTrue($result, 'Should skip processing when dimensions match exactly (auto-optimization)');
    }

    public function testShouldSkipProcessingReturnsTrueWhenNoDimensionsRequested(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getProperty')->willReturnMap([
            ['width', 1920],
            ['height', 1080],
        ]);

        $imageConfig = ['width' => 0, 'height' => 0]; // No dimensions
        $noScale     = false;

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
        ]);

        self::assertTrue($result, 'Should skip processing when no dimensions are requested');
    }

    public function testShouldSkipProcessingReturnsFalseWhenDimensionsDiffer(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getProperty')->willReturnMap([
            ['width', 1920],
            ['height', 1080],
        ]);

        $imageConfig = ['width' => 800, 'height' => 600]; // Different dimensions
        $noScale     = false;

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
        ]);

        self::assertFalse($result, 'Should not skip processing when dimensions differ');
    }

    public function testShouldSkipProcessingReturnsFalseWhenOnlyWidthMatches(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getProperty')->willReturnMap([
            ['width', 1920],
            ['height', 1080],
        ]);

        $imageConfig = ['width' => 1920, 'height' => 600]; // Only width matches
        $noScale     = false;

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
        ]);

        self::assertFalse($result, 'Should not skip processing when only width matches');
    }

    public function testShouldSkipProcessingReturnsFalseWhenOnlyHeightMatches(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getProperty')->willReturnMap([
            ['width', 1920],
            ['height', 1080],
        ]);

        $imageConfig = ['width' => 800, 'height' => 1080]; // Only height matches
        $noScale     = false;

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
        ]);

        self::assertFalse($result, 'Should not skip processing when only height matches');
    }

    public function testShouldSkipProcessingHandlesMissingDimensionsGracefully(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getProperty')->willReturn(null); // No dimensions on file

        $imageConfig = ['width' => 800, 'height' => 600];
        $noScale     = false;

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
        ]);

        self::assertFalse($result, 'Should not skip processing when original file has no dimensions');
    }

    public function testShouldSkipProcessingHandlesEmptyConfiguration(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getProperty')->willReturnMap([
            ['width', 1920],
            ['height', 1080],
        ]);

        $imageConfig = []; // Empty configuration
        $noScale     = false;

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
        ]);

        self::assertTrue($result, 'Should skip processing when configuration is empty (no dimensions requested)');
    }

    public function testShouldSkipProcessingReturnsTrueForSvgFiles(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getExtension')->willReturn('svg');
        $file->method('getProperty')->willReturnMap([
            ['width', 100],
            ['height', 100],
        ]);

        $imageConfig = ['width' => 200, 'height' => 200]; // Different dimensions
        $noScale     = false; // Not explicitly enabled

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
            0, // No file size limit
        ]);

        self::assertTrue($result, 'Should skip processing for SVG files regardless of dimensions');
    }

    public function testShouldSkipProcessingReturnsTrueForSvgUppercase(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getExtension')->willReturn('SVG'); // Uppercase
        $file->method('getProperty')->willReturnMap([
            ['width', 100],
            ['height', 100],
        ]);

        $imageConfig = ['width' => 200, 'height' => 200];
        $noScale     = false;

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
            0,
        ]);

        self::assertTrue($result, 'Should skip processing for SVG files with uppercase extension');
    }

    public function testShouldSkipProcessingRespectsFileSizeThreshold(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getExtension')->willReturn('jpg');
        $file->method('getProperty')->willReturnMap([
            ['width', 1920],
            ['height', 1080],
        ]);
        $file->method('getSize')->willReturn(3000000); // 3MB

        $imageConfig = ['width' => 1920, 'height' => 1080]; // Dimensions match
        $noScale     = false;
        $maxFileSize = 2000000; // 2MB threshold

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
            $maxFileSize,
        ]);

        self::assertFalse($result, 'Should not skip processing when file exceeds size threshold');
    }

    public function testShouldSkipProcessingWhenFileBelowThreshold(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getExtension')->willReturn('jpg');
        $file->method('getProperty')->willReturnMap([
            ['width', 1920],
            ['height', 1080],
        ]);
        $file->method('getSize')->willReturn(1500000); // 1.5MB

        $imageConfig = ['width' => 1920, 'height' => 1080]; // Dimensions match
        $noScale     = false;
        $maxFileSize = 2000000; // 2MB threshold

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
            $maxFileSize,
        ]);

        self::assertTrue($result, 'Should skip processing when file is below size threshold');
    }

    public function testShouldSkipProcessingIgnoresThresholdWhenZero(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getExtension')->willReturn('jpg');
        $file->method('getProperty')->willReturnMap([
            ['width', 1920],
            ['height', 1080],
        ]);
        $file->method('getSize')->willReturn(10000000); // 10MB (very large)

        $imageConfig = ['width' => 1920, 'height' => 1080]; // Dimensions match
        $noScale     = false;
        $maxFileSize = 0; // No threshold

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
            $maxFileSize,
        ]);

        self::assertTrue($result, 'Should skip processing when threshold is 0 (no limit)');
    }

    public function testShouldSkipProcessingSvgIgnoresFileSize(): void
    {
        $controller = $this->createController();

        $file = $this->createMock(File::class);
        $file->method('getExtension')->willReturn('svg');
        $file->method('getSize')->willReturn(5000000); // 5MB SVG
        $file->method('getProperty')->willReturnMap([
            ['width', 100],
            ['height', 100],
        ]);

        $imageConfig = ['width' => 200, 'height' => 200]; // Different dimensions
        $noScale     = false;
        $maxFileSize = 1000000; // 1MB threshold

        $result = $this->callProtectedMethod($controller, 'shouldSkipProcessing', [
            $file,
            $imageConfig,
            $noScale,
            $maxFileSize,
        ]);

        self::assertTrue($result, 'Should skip processing for SVG regardless of file size threshold');
    }

    public function testGetQualityMultiplierReturnsCorrectValueForNone(): void
    {
        $controller = $this->createController();

        $result = $this->callProtectedMethod($controller, 'getQualityMultiplier', ['none']);

        self::assertSame(1.0, $result);
    }

    public function testGetQualityMultiplierReturnsCorrectValueForLow(): void
    {
        $controller = $this->createController();

        $result = $this->callProtectedMethod($controller, 'getQualityMultiplier', ['low']);

        self::assertSame(0.9, $result);
    }

    public function testGetQualityMultiplierReturnsCorrectValueForStandard(): void
    {
        $controller = $this->createController();

        $result = $this->callProtectedMethod($controller, 'getQualityMultiplier', ['standard']);

        self::assertSame(1.0, $result);
    }

    public function testGetQualityMultiplierReturnsCorrectValueForRetina(): void
    {
        $controller = $this->createController();

        $result = $this->callProtectedMethod($controller, 'getQualityMultiplier', ['retina']);

        self::assertSame(2.0, $result);
    }

    public function testGetQualityMultiplierReturnsCorrectValueForUltra(): void
    {
        $controller = $this->createController();

        $result = $this->callProtectedMethod($controller, 'getQualityMultiplier', ['ultra']);

        self::assertSame(3.0, $result);
    }

    public function testGetQualityMultiplierReturnsCorrectValueForPrint(): void
    {
        $controller = $this->createController();

        $result = $this->callProtectedMethod($controller, 'getQualityMultiplier', ['print']);

        self::assertSame(6.0, $result);
    }

    public function testGetQualityMultiplierReturnsDefaultForEmptyString(): void
    {
        $controller = $this->createController();

        $result = $this->callProtectedMethod($controller, 'getQualityMultiplier', ['']);

        self::assertSame(1.0, $result);
    }

    public function testGetQualityMultiplierReturnsDefaultForInvalidValue(): void
    {
        $controller = $this->createController();

        $result = $this->callProtectedMethod($controller, 'getQualityMultiplier', ['invalid']);

        self::assertSame(1.0, $result, 'Should return default 1.0 for invalid quality value');
    }

    public function testGetQualityMultiplierHandlesNumericStrings(): void
    {
        $controller = $this->createController();

        $result = $this->callProtectedMethod($controller, 'getQualityMultiplier', ['2']);

        self::assertSame(1.0, $result, 'Should return default 1.0 for numeric string that is not a valid quality level');
    }
}
