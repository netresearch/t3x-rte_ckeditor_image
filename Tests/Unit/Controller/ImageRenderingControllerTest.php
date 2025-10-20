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
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TYPO3\CMS\Core\Resource\File;

/**
 * Test case for ImageRenderingController.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
class ImageRenderingControllerTest extends TestCase
{
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
        $controller = new ImageRenderingController();

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
        $controller = new ImageRenderingController();

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
        $controller = new ImageRenderingController();

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
        $controller = new ImageRenderingController();

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
        $controller = new ImageRenderingController();

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
        $controller = new ImageRenderingController();

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
        $controller = new ImageRenderingController();

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
        $controller = new ImageRenderingController();

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
}
