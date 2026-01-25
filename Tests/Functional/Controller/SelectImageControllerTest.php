<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Controller;

use Netresearch\RteCKEditorImage\Controller\SelectImageController;
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
class SelectImageControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    private ?SelectImageController $subject = null;

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

    public function testGetMaxDimensionsReturnsDefaultsWhenTSConfigMissing(): void
    {
        // Call with PID 0 (root page) which should use defaults if no TSConfig is set
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [
            ['pid' => 0],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
        $this->assertEquals(1920, $result['width'], 'Default width should be 1920');
        $this->assertEquals(9999, $result['height'], 'Default height should be 9999');
    }

    public function testGetMaxDimensionsHandlesEmptyConfigurationName(): void
    {
        // Empty configuration name should fallback to 'default'
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [
            ['pid' => 0, 'richtextConfigurationName' => ''],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(1920, $result['width']);
        $this->assertEquals(9999, $result['height']);
    }

    public function testGetMaxDimensionsHandlesMissingPid(): void
    {
        // Missing PID should default to 0
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [
            [],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(1920, $result['width']);
        $this->assertEquals(9999, $result['height']);
    }

    public function testGetMaxDimensionsEnforcesMinimumBounds(): void
    {
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [
            ['pid' => 0],
        ]);

        $this->assertGreaterThanOrEqual(1, $result['width'], 'Width should be at least 1');
        $this->assertGreaterThanOrEqual(1, $result['height'], 'Height should be at least 1');
    }

    public function testGetMaxDimensionsEnforcesMaximumBounds(): void
    {
        // The method should clamp values to 10000 maximum to prevent resource exhaustion
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [
            ['pid' => 0],
        ]);

        $this->assertLessThanOrEqual(10000, $result['width'], 'Width should not exceed 10000');
        $this->assertLessThanOrEqual(10000, $result['height'], 'Height should not exceed 10000');
    }

    public function testGetMaxDimensionsReturnsIntegerValues(): void
    {
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [
            ['pid' => 0],
        ]);

        $this->assertIsInt($result['width'], 'Width should be an integer');
        $this->assertIsInt($result['height'], 'Height should be an integer');
    }

    public function testGetMaxDimensionsReturnsArrayWithCorrectKeys(): void
    {
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [
            ['pid' => 0],
        ]);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertArrayHasKey('width', $result);
        $this->assertArrayHasKey('height', $result);
    }

    /**
     * @throws ReflectionException
     */
    public function testClassConstantsHaveExpectedValues(): void
    {
        $this->assertEquals(1, $this->getConstant('IMAGE_MIN_DIMENSION'));
        $this->assertEquals(10000, $this->getConstant('IMAGE_MAX_DIMENSION'));
        $this->assertEquals(1920, $this->getConstant('IMAGE_DEFAULT_MAX_WIDTH'));
        $this->assertEquals(9999, $this->getConstant('IMAGE_DEFAULT_MAX_HEIGHT'));
    }

    public function testGetMaxDimensionsHandlesNullRichtextConfigurationName(): void
    {
        // Null configuration name should fallback to 'default'
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [
            ['pid' => 0, 'richtextConfigurationName' => null],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals(1920, $result['width']);
        $this->assertEquals(9999, $result['height']);
    }

    public function testGetMaxDimensionsPreventsResourceExhaustion(): void
    {
        // Verify that returned dimensions won't cause memory exhaustion
        // 10000x10000 ≈ 400MB is the documented safe maximum
        $result = $this->invokeMethod($this->subject, 'getMaxDimensions', [
            ['pid' => 0],
        ]);

        $maxArea = $result['width'] * $result['height'];
        $this->assertLessThanOrEqual(
            100000000, // 10000 * 10000
            $maxArea,
            'Dimensions should not allow memory exhaustion (max 10000x10000)',
        );
    }

    // ========================================================================
    // calculateDisplayDimensions Tests
    // ========================================================================

    public function testCalculateDisplayDimensionsReturnsOriginalForSmallImage(): void
    {
        self::assertNotNull($this->subject);
        // Image smaller than max limits should return original dimensions
        $result = $this->invokeMethod($this->subject, 'calculateDisplayDimensions', [
            800,  // originalWidth
            600,  // originalHeight
            1920, // maxWidth
            1080, // maxHeight
        ]);

        self::assertIsArray($result);
        self::assertEquals(800, $result['width']);
        self::assertEquals(600, $result['height']);
    }

    public function testCalculateDisplayDimensionsScalesDownWideImage(): void
    {
        self::assertNotNull($this->subject);
        // Wide image exceeding maxWidth should scale proportionally
        $result = $this->invokeMethod($this->subject, 'calculateDisplayDimensions', [
            3840, // originalWidth (exceeds maxWidth)
            2160, // originalHeight
            1920, // maxWidth
            1080, // maxHeight
        ]);

        self::assertIsArray($result);
        self::assertArrayHasKey('width', $result);
        self::assertArrayHasKey('height', $result);
        self::assertLessThanOrEqual(1920, $result['width']);
        self::assertLessThanOrEqual(1080, $result['height']);
        // Aspect ratio should be preserved: 3840/2160 ≈ 1.78
        $width  = (int) $result['width'];
        $height = (int) $result['height'];
        self::assertGreaterThan(0, $height, 'Height must be greater than zero for ratio calculation');
        $aspectRatio = $width / $height;
        self::assertEqualsWithDelta(1.78, $aspectRatio, 0.01);
    }

    public function testCalculateDisplayDimensionsScalesDownTallImage(): void
    {
        self::assertNotNull($this->subject);
        // Tall image exceeding maxHeight should scale proportionally
        $result = $this->invokeMethod($this->subject, 'calculateDisplayDimensions', [
            800,  // originalWidth
            2000, // originalHeight (exceeds maxHeight)
            1920, // maxWidth
            1080, // maxHeight
        ]);

        self::assertIsArray($result);
        self::assertLessThanOrEqual(1920, $result['width']);
        self::assertLessThanOrEqual(1080, $result['height']);
    }

    public function testCalculateDisplayDimensionsHandlesSquareImage(): void
    {
        self::assertNotNull($this->subject);
        $result = $this->invokeMethod($this->subject, 'calculateDisplayDimensions', [
            1000, // originalWidth
            1000, // originalHeight
            500,  // maxWidth
            500,  // maxHeight
        ]);

        self::assertIsArray($result);
        self::assertEquals(500, $result['width']);
        self::assertEquals(500, $result['height']);
    }

    public function testCalculateDisplayDimensionsHandlesExtremelyWideImage(): void
    {
        self::assertNotNull($this->subject);
        // Panorama image: very wide, short height
        $result = $this->invokeMethod($this->subject, 'calculateDisplayDimensions', [
            10000, // originalWidth
            500,   // originalHeight
            1920,  // maxWidth
            1080,  // maxHeight
        ]);

        self::assertIsArray($result);
        self::assertLessThanOrEqual(1920, $result['width']);
        // Height should scale proportionally (10000/500 = 20, so 1920/20 = 96)
        self::assertLessThan(500, $result['height']);
    }

    public function testCalculateDisplayDimensionsHandlesExtremelyTallImage(): void
    {
        self::assertNotNull($this->subject);
        // Very tall image: narrow width, tall height
        $result = $this->invokeMethod($this->subject, 'calculateDisplayDimensions', [
            500,   // originalWidth
            10000, // originalHeight
            1920,  // maxWidth
            1080,  // maxHeight
        ]);

        self::assertIsArray($result);
        self::assertLessThanOrEqual(1080, $result['height']);
        self::assertLessThan(500, $result['width']);
    }

    public function testCalculateDisplayDimensionsPreservesAspectRatio(): void
    {
        self::assertNotNull($this->subject);
        // 16:9 aspect ratio
        $result = $this->invokeMethod($this->subject, 'calculateDisplayDimensions', [
            3200, // originalWidth
            1800, // originalHeight
            800,  // maxWidth
            600,  // maxHeight
        ]);

        self::assertIsArray($result);
        self::assertArrayHasKey('width', $result);
        self::assertArrayHasKey('height', $result);
        // Original aspect ratio: 3200/1800 ≈ 1.78
        $width  = (int) $result['width'];
        $height = (int) $result['height'];
        self::assertGreaterThan(0, $height, 'Height must be greater than zero for ratio calculation');
        $aspectRatio = $width / $height;
        self::assertEqualsWithDelta(1.78, $aspectRatio, 0.01);
    }

    // ========================================================================
    // getTranslations Tests (via infoAction with translations request)
    // ========================================================================

    public function testGetTranslationsReturnsExpectedKeys(): void
    {
        self::assertNotNull($this->subject);
        // Create a real controller instance for testing getTranslations
        $result = $this->invokeMethod($this->subject, 'getTranslations', []);

        self::assertIsArray($result);

        // Verify essential translation keys exist
        $expectedKeys = [
            'override',
            'overrideNoDefault',
            'cssClass',
            'width',
            'height',
            'title',
            'alt',
            'clickToEnlarge',
            'enabled',
            'skipImageProcessing',
            'imageProperties',
            'cancel',
            'save',
            'insertImage',
            'noDefaultMetadata',
            'zoomHelp',
            'noScaleHelp',
            'zoom',
            'quality',
            'qualityNone',
            'qualityStandard',
            'qualityRetina',
            'qualityUltra',
            'qualityPrint',
        ];

        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $result, "Missing translation key: {$key}");
        }
    }

    public function testGetTranslationsReturnsQualityLabelsAndTooltips(): void
    {
        self::assertNotNull($this->subject);
        $result = $this->invokeMethod($this->subject, 'getTranslations', []);

        self::assertIsArray($result);

        // Quality levels with labels and tooltips
        $qualityKeys = [
            'qualityLowLabel',
            'qualityLowTooltip',
            'qualityStandardLabel',
            'qualityStandardTooltip',
            'qualityRetinaLabel',
            'qualityRetinaTooltip',
            'qualityUltraLabel',
            'qualityUltraTooltip',
            'qualityPrintLabel',
            'qualityPrintTooltip',
            'qualityExcessiveLabel',
            'qualityExcessiveTooltip',
        ];

        foreach ($qualityKeys as $key) {
            self::assertArrayHasKey($key, $result, "Missing quality translation key: {$key}");
        }
    }
}
