<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Database;

use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;
use Netresearch\RteCKEditorImage\Service\Processor\RteImageProcessorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for RteImagesDbHook.
 *
 * Note: Tests that require BackendUtility::getTCAtypeValue() are in the functional
 * test suite (Tests/Functional/Database/RteImagesDbHookTest.php) because they need
 * TYPO3's DI container to be properly bootstrapped.
 *
 * @see \Netresearch\RteCKEditorImage\Tests\Functional\Database\RteImagesDbHookTest
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(RteImagesDbHook::class)]
final class RteImagesDbHookTest extends UnitTestCase
{
    protected bool $resetSingletonInstances = true;

    private RteImagesDbHook $subject;

    /** @var RteImageProcessorInterface&MockObject */
    private MockObject $imageProcessorMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imageProcessorMock = $this->createMock(RteImageProcessorInterface::class);
        $this->subject            = new RteImagesDbHook($this->imageProcessorMock);
    }

    #[Test]
    public function constructorInitializesWithDependencyInjection(): void
    {
        // Verify the hook was created with the correct type
        self::assertSame(RteImagesDbHook::class, $this->subject::class);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresFieldNotInTca(): void
    {
        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['non_existing_field' => 'some value'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Ensure the field does not exist in TCA
        unset($GLOBALS['TCA']['tt_content']['columns']['non_existing_field']);

        // Processor should NOT be called for non-TCA fields
        $this->imageProcessorMock
            ->expects(self::never())
            ->method('process');

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // Field array should remain unchanged
        self::assertSame(['non_existing_field' => 'some value'], $fieldArray);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function rteTextFieldProvider(): iterable
    {
        yield 'no type' => [['enableRichtext' => true], false];
        yield 'type null' => [['type' => null, 'enableRichtext' => true], false];
        yield 'type input' => [['type' => 'input', 'enableRichtext' => true], false];
        yield 'text without enableRichtext' => [['type' => 'text'], false];
        yield 'text with enableRichtext null' => [['type' => 'text', 'enableRichtext' => null], false];
        yield 'text with enableRichtext false' => [['type' => 'text', 'enableRichtext' => false], false];
        yield 'text with enableRichtext string 1' => [['type' => 'text', 'enableRichtext' => '1'], false];
        yield 'text with enableRichtext true' => [['type' => 'text', 'enableRichtext' => true], true];
    }

    /**
     * @param array<string, mixed> $tcaFieldConf
     */
    #[Test]
    #[DataProvider('rteTextFieldProvider')]
    public function isRteTextFieldDetectsRichtextTextFields(array $tcaFieldConf, bool $expected): void
    {
        $method = new ReflectionMethod(RteImagesDbHook::class, 'isRteTextField');

        self::assertSame($expected, $method->invoke($this->subject, $tcaFieldConf));
    }
}
