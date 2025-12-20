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
}
