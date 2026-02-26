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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
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
        self::assertInstanceOf(RteImagesDbHook::class, $this->subject);
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
}
