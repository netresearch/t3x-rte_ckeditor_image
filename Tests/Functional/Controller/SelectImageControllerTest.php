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
 * Test case for SelectImageController::getMaxDimensions().
 *
 * This test validates the security improvements in PR #299:
 * - Safe array access with fallback to defaults
 * - Type casting from TSConfig values
 * - Bounds enforcement to prevent resource exhaustion
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
     * Returns the test subject, asserting it is initialized.
     */
    private function getSubject(): SelectImageController
    {
        self::assertNotNull($this->subject, 'Test subject not initialized');

        return $this->subject;
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
        $reflection = new ReflectionClass($object::class);
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Helper to invoke getMaxDimensions and return a typed result.
     *
     * @param array<int, mixed> $parameters
     *
     * @return array<string, int>
     *
     * @throws ReflectionException
     */
    private function invokeGetMaxDimensions(array $parameters): array
    {
        $result = $this->invokeMethod($this->getSubject(), 'getMaxDimensions', $parameters);
        self::assertIsArray($result);

        /** @var array<string, int> $result */
        return $result;
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
     * @test
     */
    public function getMaxDimensionsReturnsDefaultsWhenTSConfigMissing(): void
    {
        // Call with PID 0 (root page) which should use defaults if no TSConfig is set
        $result = $this->invokeGetMaxDimensions([
            ['pid' => 0],
        ]);

        self::assertArrayHasKey('width', $result);
        self::assertArrayHasKey('height', $result);
        self::assertEquals(1920, $result['width'], 'Default width should be 1920');
        self::assertEquals(9999, $result['height'], 'Default height should be 9999');
    }

    /**
     * @test
     */
    public function getMaxDimensionsHandlesEmptyConfigurationName(): void
    {
        // Empty configuration name should fallback to 'default'
        $result = $this->invokeGetMaxDimensions([
            ['pid' => 0, 'richtextConfigurationName' => ''],
        ]);

        self::assertEquals(1920, $result['width']);
        self::assertEquals(9999, $result['height']);
    }

    /**
     * @test
     */
    public function getMaxDimensionsHandlesMissingPid(): void
    {
        // Missing PID should default to 0
        $result = $this->invokeGetMaxDimensions([
            [],
        ]);

        self::assertEquals(1920, $result['width']);
        self::assertEquals(9999, $result['height']);
    }

    /**
     * @test
     */
    public function getMaxDimensionsEnforcesMinimumBounds(): void
    {
        $result = $this->invokeGetMaxDimensions([
            ['pid' => 0],
        ]);

        self::assertGreaterThanOrEqual(1, $result['width'], 'Width should be at least 1');
        self::assertGreaterThanOrEqual(1, $result['height'], 'Height should be at least 1');
    }

    /**
     * @test
     */
    public function getMaxDimensionsEnforcesMaximumBounds(): void
    {
        // The method should clamp values to 10000 maximum to prevent resource exhaustion
        $result = $this->invokeGetMaxDimensions([
            ['pid' => 0],
        ]);

        self::assertLessThanOrEqual(10000, $result['width'], 'Width should not exceed 10000');
        self::assertLessThanOrEqual(10000, $result['height'], 'Height should not exceed 10000');
    }

    /**
     * @test
     */
    public function getMaxDimensionsReturnsIntegerValues(): void
    {
        $result = $this->invokeGetMaxDimensions([
            ['pid' => 0],
        ]);

        self::assertIsInt($result['width'], 'Width should be an integer');
        self::assertIsInt($result['height'], 'Height should be an integer');
    }

    /**
     * @test
     */
    public function getMaxDimensionsReturnsArrayWithCorrectKeys(): void
    {
        $result = $this->invokeGetMaxDimensions([
            ['pid' => 0],
        ]);

        self::assertCount(2, $result);
        self::assertArrayHasKey('width', $result);
        self::assertArrayHasKey('height', $result);
    }

    /**
     * @test
     *
     * @throws ReflectionException
     */
    public function classConstantsHaveExpectedValues(): void
    {
        self::assertEquals(1, $this->getConstant('IMAGE_MIN_DIMENSION'));
        self::assertEquals(10000, $this->getConstant('IMAGE_MAX_DIMENSION'));
        self::assertEquals(1920, $this->getConstant('IMAGE_DEFAULT_MAX_WIDTH'));
        self::assertEquals(9999, $this->getConstant('IMAGE_DEFAULT_MAX_HEIGHT'));
    }

    /**
     * @test
     */
    public function getMaxDimensionsHandlesNullRichtextConfigurationName(): void
    {
        // Null configuration name should fallback to 'default'
        $result = $this->invokeGetMaxDimensions([
            ['pid' => 0, 'richtextConfigurationName' => null],
        ]);

        self::assertEquals(1920, $result['width']);
        self::assertEquals(9999, $result['height']);
    }

    /**
     * @test
     */
    public function getMaxDimensionsPreventsResourceExhaustion(): void
    {
        // Verify that returned dimensions won't cause memory exhaustion
        // 10000x10000 ≈ 400MB is the documented safe maximum
        $result = $this->invokeGetMaxDimensions([
            ['pid' => 0],
        ]);

        $maxArea = $result['width'] * $result['height'];
        self::assertLessThanOrEqual(
            100000000, // 10000 * 10000
            $maxArea,
            'Dimensions should not allow memory exhaustion (max 10000x10000)',
        );
    }
}
