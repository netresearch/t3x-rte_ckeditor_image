<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresFieldWithoutTypeInConfig(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['bodytext' => 'content'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration without 'type' key
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'rows' => 5,
        ];

        // Processor should NOT be called
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
        self::assertSame(['bodytext' => 'content'], $fieldArray);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresNonTextTypeField(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['header' => 'A header'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration with type 'input'
        $GLOBALS['TCA']['tt_content']['columns']['header']['config'] = [
            'type' => 'input',
        ];

        // Processor should NOT be called
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
        self::assertSame(['header' => 'A header'], $fieldArray);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresFieldWithoutEnableRichtext(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['bodytext' => 'plain text'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration without enableRichtext
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type' => 'text',
            'rows' => 10,
        ];

        // Processor should NOT be called
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
        self::assertSame(['bodytext' => 'plain text'], $fieldArray);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresFieldWithEnableRichtextFalse(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['bodytext' => 'plain text'];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration with enableRichtext = false
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type'           => 'text',
            'enableRichtext' => false,
        ];

        // Processor should NOT be called
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
        self::assertSame(['bodytext' => 'plain text'], $fieldArray);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresNullFieldValue(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status     = 'update';
        $table      = 'tt_content';
        $id         = '123';
        $fieldArray = ['bodytext' => null];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration with enableRichtext = true
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];

        // Processor should NOT be called for null values
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

        // Field array should remain unchanged when value is null
        self::assertNull($fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayCallsProcessorForRteField(): void
    {
        if (method_exists(\TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem::class, 'getRow')) {
            self::markTestSkipped('Test requires functional test setup for TYPO3 v14+');
        }

        $status         = 'update';
        $table          = 'tt_content';
        $id             = '123';
        $originalValue  = '<p>Some <img src="test.jpg" /> content</p>';
        $processedValue = '<p>Some <img src="processed.jpg" /> content</p>';
        $fieldArray     = ['bodytext' => $originalValue];

        /** @var DataHandler&MockObject $dataHandlerMock */
        $dataHandlerMock = $this->createMock(DataHandler::class);

        // Mock TCA configuration with enableRichtext = true
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];

        // Processor should be called with the original value
        $this->imageProcessorMock
            ->expects(self::once())
            ->method('process')
            ->with($originalValue)
            ->willReturn($processedValue);

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandlerMock,
        );

        // Field array should be updated with processed value
        self::assertSame($processedValue, $fieldArray['bodytext']);
    }
}
