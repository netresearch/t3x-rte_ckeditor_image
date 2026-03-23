<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Controller;

use Netresearch\RteCKEditorImage\Controller\SelectImageController;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for SelectImageController.
 *
 * Coverage notes:
 * - getMaxDimensions(): Tested here (security improvements in PR #299)
 * - mainAction() expandFolder: Tested in Unit tests (issue #290 follow-up)
 *   The expandFolder logic is comprehensively unit tested because:
 *   1. It doesn't require TYPO3 infrastructure for logic verification
 *   2. Testing the full ElementBrowser flow requires complex backend setup
 *   3. Unit tests verify all edge cases: preservation, setting, exceptions, null user
 *
 * @see \Netresearch\RteCKEditorImage\Tests\Unit\Controller\SelectImageControllerTest
 */
#[AllowMockObjectsWithoutExpectations]
class SelectImageControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    private SelectImageController $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->getMockBuilder(SelectImageController::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object            $object     Instantiated object that we will run method on
     * @param string            $methodName Method name to call
     * @param array<int, mixed> $parameters Array of parameters to pass into method
     *
     * @return mixed Method return
     *
     * @throws ReflectionException
     */
    private function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Get class constant value using reflection.
     *
     * @param string $constantName
     *
     * @return mixed
     *
     * @throws ReflectionException
     */
    private function getConstant(string $constantName): mixed
    {
        $reflection = new ReflectionClass(SelectImageController::class);

        return $reflection->getConstant($constantName);
    }

    /**
     * Helper to invoke getMaxDimensions and return a typed array.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, int>
     */
    private function invokeGetMaxDimensions(array $params): array
    {
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [$params]);
        self::assertIsArray($result);

        /** @var array<string, int> $result */
        return $result;
    }

    /**
     * Helper to invoke calculateDisplayDimensions and return a typed array.
     *
     * @return array<string, int>
     */
    private function invokeCalculateDisplayDimensions(
        int $originalWidth,
        int $originalHeight,
        int $maxWidth,
        int $maxHeight,
    ): array {
        $result = $this->invokeMethod($this->subject, 'calculateDisplayDimensions', [
            $originalWidth,
            $originalHeight,
            $maxWidth,
            $maxHeight,
        ]);
        self::assertIsArray($result);

        /** @var array<string, int> $result */
        return $result;
    }

    #[Test]
    public function getMaxDimensionsReturnsDefaultsWhenTSConfigMissing(): void
    {
        // Call with PID 0 (root page) which should use defaults if no TSConfig is set
        $result = $this->invokeGetMaxDimensions(['pid' => 0]);

        self::assertArrayHasKey('width', $result);
        self::assertArrayHasKey('height', $result);
        self::assertEquals(1920, $result['width'], 'Default width should be 1920');
        self::assertEquals(9999, $result['height'], 'Default height should be 9999');
    }

    #[Test]
    public function getMaxDimensionsHandlesEmptyConfigurationName(): void
    {
        // Empty configuration name should fallback to 'default'
        $result = $this->invokeGetMaxDimensions(['pid' => 0, 'richtextConfigurationName' => '']);

        self::assertEquals(1920, $result['width']);
        self::assertEquals(9999, $result['height']);
    }

    #[Test]
    public function getMaxDimensionsHandlesMissingPid(): void
    {
        // Missing PID should default to 0
        $result = $this->invokeGetMaxDimensions([]);

        self::assertEquals(1920, $result['width']);
        self::assertEquals(9999, $result['height']);
    }

    #[Test]
    public function getMaxDimensionsEnforcesMinimumBounds(): void
    {
        $result = $this->invokeGetMaxDimensions(['pid' => 0]);

        self::assertGreaterThanOrEqual(1, $result['width'], 'Width should be at least 1');
        self::assertGreaterThanOrEqual(1, $result['height'], 'Height should be at least 1');
    }

    #[Test]
    public function getMaxDimensionsEnforcesMaximumBounds(): void
    {
        // The method should clamp values to 10000 maximum to prevent resource exhaustion
        $result = $this->invokeGetMaxDimensions(['pid' => 0]);

        self::assertLessThanOrEqual(10000, $result['width'], 'Width should not exceed 10000');
        self::assertLessThanOrEqual(10000, $result['height'], 'Height should not exceed 10000');
    }

    #[Test]
    public function getMaxDimensionsReturnsIntegerValues(): void
    {
        $result = $this->invokeGetMaxDimensions(['pid' => 0]);

        self::assertIsInt($result['width'], 'Width should be an integer');
        self::assertIsInt($result['height'], 'Height should be an integer');
    }

    #[Test]
    public function getMaxDimensionsReturnsArrayWithCorrectKeys(): void
    {
        $result = $this->invokeGetMaxDimensions(['pid' => 0]);

        self::assertCount(2, $result);
        self::assertArrayHasKey('width', $result);
        self::assertArrayHasKey('height', $result);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function classConstantsHaveExpectedValues(): void
    {
        self::assertEquals(1, $this->getConstant('IMAGE_MIN_DIMENSION'));
        self::assertEquals(10000, $this->getConstant('IMAGE_MAX_DIMENSION'));
        self::assertEquals(1920, $this->getConstant('IMAGE_DEFAULT_MAX_WIDTH'));
        self::assertEquals(9999, $this->getConstant('IMAGE_DEFAULT_MAX_HEIGHT'));
    }

    #[Test]
    public function getMaxDimensionsHandlesNullRichtextConfigurationName(): void
    {
        // Null configuration name should fallback to 'default'
        $result = $this->invokeGetMaxDimensions(['pid' => 0, 'richtextConfigurationName' => null]);

        self::assertEquals(1920, $result['width']);
        self::assertEquals(9999, $result['height']);
    }

    #[Test]
    public function getMaxDimensionsPreventsResourceExhaustion(): void
    {
        // Verify that returned dimensions won't cause memory exhaustion
        // 10000x10000 ≈ 400MB is the documented safe maximum
        $result = $this->invokeGetMaxDimensions(['pid' => 0]);

        $maxArea = $result['width'] * $result['height'];
        self::assertLessThanOrEqual(
            100000000, // 10000 * 10000
            $maxArea,
            'Dimensions should not allow memory exhaustion (max 10000x10000)',
        );
    }

    // ========================================================================
    // calculateDisplayDimensions Tests
    // ========================================================================

    #[Test]
    public function calculateDisplayDimensionsReturnsOriginalForSmallImage(): void
    {
        // Image smaller than max limits should return original dimensions
        $result = $this->invokeCalculateDisplayDimensions(800, 600, 1920, 1080);

        self::assertEquals(800, $result['width']);
        self::assertEquals(600, $result['height']);
    }

    #[Test]
    public function calculateDisplayDimensionsScalesDownWideImage(): void
    {
        // Wide image exceeding maxWidth should scale proportionally
        $result = $this->invokeCalculateDisplayDimensions(3840, 2160, 1920, 1080);

        self::assertArrayHasKey('width', $result);
        self::assertArrayHasKey('height', $result);
        self::assertLessThanOrEqual(1920, $result['width']);
        self::assertLessThanOrEqual(1080, $result['height']);
        // Aspect ratio should be preserved: 3840/2160 ≈ 1.78
        $width  = $result['width'];
        $height = $result['height'];
        self::assertGreaterThan(0, $height, 'Height must be greater than zero for ratio calculation');
        $aspectRatio = $width / $height;
        self::assertEqualsWithDelta(1.78, $aspectRatio, 0.01);
    }

    #[Test]
    public function calculateDisplayDimensionsScalesDownTallImage(): void
    {
        // Tall image exceeding maxHeight should scale proportionally
        $result = $this->invokeCalculateDisplayDimensions(800, 2000, 1920, 1080);

        self::assertLessThanOrEqual(1920, $result['width']);
        self::assertLessThanOrEqual(1080, $result['height']);
    }

    #[Test]
    public function calculateDisplayDimensionsHandlesSquareImage(): void
    {
        $result = $this->invokeCalculateDisplayDimensions(1000, 1000, 500, 500);

        self::assertEquals(500, $result['width']);
        self::assertEquals(500, $result['height']);
    }

    #[Test]
    public function calculateDisplayDimensionsHandlesExtremelyWideImage(): void
    {
        // Panorama image: very wide, short height
        $result = $this->invokeCalculateDisplayDimensions(10000, 500, 1920, 1080);

        self::assertLessThanOrEqual(1920, $result['width']);
        // Height should scale proportionally (10000/500 = 20, so 1920/20 = 96)
        self::assertLessThan(500, $result['height']);
    }

    #[Test]
    public function calculateDisplayDimensionsHandlesExtremelyTallImage(): void
    {
        // Very tall image: narrow width, tall height
        $result = $this->invokeCalculateDisplayDimensions(500, 10000, 1920, 1080);

        self::assertLessThanOrEqual(1080, $result['height']);
        self::assertLessThan(500, $result['width']);
    }

    #[Test]
    public function calculateDisplayDimensionsPreservesAspectRatio(): void
    {
        // 16:9 aspect ratio
        $result = $this->invokeCalculateDisplayDimensions(3200, 1800, 800, 600);

        self::assertArrayHasKey('width', $result);
        self::assertArrayHasKey('height', $result);
        // Original aspect ratio: 3200/1800 ≈ 1.78
        $width  = $result['width'];
        $height = $result['height'];
        self::assertGreaterThan(0, $height, 'Height must be greater than zero for ratio calculation');
        $aspectRatio = $width / $height;
        self::assertEqualsWithDelta(1.78, $aspectRatio, 0.01);
    }

    // ========================================================================
    // getTranslations Tests (PR #575 - Click Behavior UI)
    // ========================================================================

    /**
     * Test that getTranslations returns the new Click Behavior translation keys.
     *
     * This test covers the new translation keys added for the Click Behavior UI
     * in PR #575 to fix issue #565 (duplicate links when images wrapped in <a> tags).
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/565
     */
    #[Test]
    public function getTranslationsContainsClickBehaviorKeys(): void
    {
        /** @var array<string, string|null> $result */
        $result = $this->invokeMethod($this->subject, 'getTranslations');

        self::assertIsArray($result);

        // Click Behavior UI translation keys (PR #575)
        // These are the new keys added to fix issue #565
        $clickBehaviorKeys = [
            'clickBehavior',
            'clickBehaviorNone',
            'clickBehaviorEnlarge',
            'clickBehaviorLink',
            'linkUrl',
            'linkTarget',
            'linkTitle',
            'linkCssClass',
            'browse',
            'linkTargetDefault',
            'linkTargetBlank',
            'linkTargetTop',
        ];

        foreach ($clickBehaviorKeys as $key) {
            self::assertArrayHasKey(
                $key,
                $result,
                sprintf('Click Behavior translation key "%s" should be present in getTranslations() result', $key),
            );
        }
    }

    /**
     * Test that getTranslations returns an array with string keys.
     *
     * This ensures the method returns a properly structured array for JavaScript consumption.
     */
    #[Test]
    public function getTranslationsReturnsArrayWithStringKeys(): void
    {
        /** @var array<string, string|null> $result */
        $result = $this->invokeMethod($this->subject, 'getTranslations');

        self::assertIsArray($result);
        self::assertNotEmpty($result, 'getTranslations() should return a non-empty array');

        // Verify all keys are strings
        foreach (array_keys($result) as $key) {
            self::assertIsString($key, 'All translation keys should be strings');
        }
    }
}
