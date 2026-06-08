<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Controller;

use Netresearch\RteCKEditorImage\Controller\SelectImageController;
use ReflectionClass;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for SelectImageController::isFileAccessibleByUser().
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/749
 */
class SelectImageControllerTest extends UnitTestCase
{
    private ?SelectImageController $subject = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->getMockBuilder(SelectImageController::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    private function getSubject(): SelectImageController
    {
        self::assertNotNull($this->subject);

        return $this->subject;
    }

    /**
     * Call protected method using reflection.
     *
     * @param array<int, mixed> $parameters
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserDeniesAccessWithoutBackendUser(): void
    {
        unset($GLOBALS['BE_USER']);

        $file = $this->createMock(File::class);

        $result = $this->invokeMethod($this->getSubject(), 'isFileAccessibleByUser', [$file]);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserDeniesAccessWithoutTableSelectPermission(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->with('tables_select', 'sys_file')
            ->willReturn(false);

        $GLOBALS['BE_USER'] = $backendUser;

        $file = $this->createMock(File::class);

        $result = $this->invokeMethod($this->getSubject(), 'isFileAccessibleByUser', [$file]);

        self::assertFalse($result);
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserGrantsAccessForNonAdminWithReadPermission(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->with('tables_select', 'sys_file')
            ->willReturn(true);
        $backendUser->method('isAdmin')
            ->willReturn(false);

        $GLOBALS['BE_USER'] = $backendUser;

        $file = $this->createMock(File::class);
        $file->method('checkActionPermission')
            ->with('read')
            ->willReturn(true);

        $result = $this->invokeMethod($this->getSubject(), 'isFileAccessibleByUser', [$file]);

        self::assertTrue($result, 'Non-admin user with read permission should have access');
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserDeniesAccessForNonAdminWithoutReadPermission(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->with('tables_select', 'sys_file')
            ->willReturn(true);
        $backendUser->method('isAdmin')
            ->willReturn(false);

        $GLOBALS['BE_USER'] = $backendUser;

        $file = $this->createMock(File::class);
        $file->method('checkActionPermission')
            ->with('read')
            ->willReturn(false);

        $result = $this->invokeMethod($this->getSubject(), 'isFileAccessibleByUser', [$file]);

        self::assertFalse($result, 'Non-admin user without read permission should be denied');
    }

    /**
     * @test
     */
    public function isFileAccessibleByUserGrantsAccessForAdmin(): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->with('tables_select', 'sys_file')
            ->willReturn(true);
        $backendUser->method('isAdmin')
            ->willReturn(true);

        $GLOBALS['BE_USER'] = $backendUser;

        $file = $this->createMock(File::class);
        $file->method('checkActionPermission')
            ->with('read')
            ->willReturn(true);

        $result = $this->invokeMethod($this->getSubject(), 'isFileAccessibleByUser', [$file]);

        self::assertTrue($result, 'Admin user should always have access');
    }

    /**
     * Helper to invoke calculateDisplayDimensions and return a typed result.
     *
     * @return array<string, int>
     */
    private function invokeCalculateDisplayDimensions(
        int $originalWidth,
        int $originalHeight,
        int $maxWidth,
        int $maxHeight,
    ): array {
        $result = $this->invokeMethod(
            $this->getSubject(),
            'calculateDisplayDimensions',
            [$originalWidth, $originalHeight, $maxWidth, $maxHeight],
        );
        self::assertIsArray($result);

        /** @var array<string, int> $result */
        return $result;
    }

    /**
     * Regression test for issue #846: a portrait image larger than both
     * maxWidth and maxHeight must keep its aspect ratio instead of being
     * clamped to maxWidth x maxHeight (which squashed it into a square).
     *
     * @test
     */
    public function calculateDisplayDimensionsPreservesAspectRatioWhenBothLimitsExceeded(): void
    {
        $result = $this->invokeCalculateDisplayDimensions(2000, 3000, 1000, 1000);

        // Shared scale factor 1000/3000 -> 667 x 1000, NOT 1000 x 1000.
        self::assertSame(667, $result['width']);
        self::assertSame(1000, $result['height']);
        self::assertLessThanOrEqual(1000, $result['width']);
        self::assertLessThanOrEqual(1000, $result['height']);
    }

    /**
     * @test
     */
    public function calculateDisplayDimensionsKeepsOriginalWhenWithinLimits(): void
    {
        $result = $this->invokeCalculateDisplayDimensions(800, 600, 1000, 1000);

        self::assertSame(800, $result['width']);
        self::assertSame(600, $result['height']);
    }

    /**
     * @test
     */
    public function calculateDisplayDimensionsScalesLandscapeByWidth(): void
    {
        $result = $this->invokeCalculateDisplayDimensions(4000, 1000, 1000, 1000);

        // Scale factor 1000/4000 = 0.25 -> 1000 x 250.
        self::assertSame(1000, $result['width']);
        self::assertSame(250, $result['height']);
    }

    /**
     * @test
     */
    public function calculateDisplayDimensionsScalesPortraitWithDefaultHeightLimit(): void
    {
        // Default config (maxWidth 1920, maxHeight 9999): only the width is
        // exceeded, so the height must scale down proportionally too.
        $result = $this->invokeCalculateDisplayDimensions(2000, 3000, 1920, 9999);

        self::assertSame(1920, $result['width']);
        self::assertSame(2880, $result['height']);
    }

    /**
     * @test
     */
    public function calculateDisplayDimensionsNeverReturnsZeroForExtremeAspectRatio(): void
    {
        // 10000 x 1 scaled to fit 1000 x 1000: the height would round to 0,
        // which is invalid for image processing. It must be clamped to 1px.
        $result = $this->invokeCalculateDisplayDimensions(10000, 1, 1000, 1000);

        self::assertSame(1000, $result['width']);
        self::assertSame(1, $result['height']);
    }

    /**
     * @test
     */
    public function calculateDisplayDimensionsFallsBackToClampingForMissingDimensions(): void
    {
        // Unknown intrinsic size (0): cannot compute a ratio, fall back to
        // independent clamping without dividing by zero.
        $result = $this->invokeCalculateDisplayDimensions(0, 0, 1000, 1000);

        self::assertSame(0, $result['width']);
        self::assertSame(0, $result['height']);
    }
}
