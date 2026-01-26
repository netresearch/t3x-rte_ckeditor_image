<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Database;

use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;
use Netresearch\RteCKEditorImage\Service\Processor\RteImageProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for RteImagesDbHook DataHandler integration.
 */
#[CoversClass(RteImagesDbHook::class)]
final class RteImagesDbHookTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content.csv');
    }

    private function createSubject(): RteImagesDbHook
    {
        // Get RteImagesDbHook from container with proper dependency injection
        return $this->get(RteImagesDbHook::class);
    }

    #[Test]
    public function hookCanBeCreatedFromContainer(): void
    {
        $subject = $this->createSubject();
        self::assertInstanceOf(RteImagesDbHook::class, $subject);
    }

    #[Test]
    public function imageProcessorCanBeCreatedFromContainer(): void
    {
        $processor = $this->get(RteImageProcessor::class);
        self::assertInstanceOf(RteImageProcessor::class, $processor);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresNonRteField(): void
    {
        $subject      = $this->createSubject();
        $status       = 'update';
        $table        = 'tt_content';
        $id           = '1';
        $originalText = 'Plain text without RTE';
        $fieldArray   = [
            'header' => $originalText,
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for non-RTE field
        /** @var array<string, mixed> $tcaConfig */
        $tcaConfig = [
            'type' => 'input',
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['header']['config'] = $tcaConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // Non-RTE field should remain unchanged
        self::assertSame($originalText, $fieldArray['header']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesNewRecord(): void
    {
        $subject    = $this->createSubject();
        $status     = 'new';
        $table      = 'tt_content';
        $id         = 'NEW123456';
        $fieldArray = [
            'bodytext' => '<p>New record content</p>',
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for RTE field
        /** @var array<string, mixed> $tcaConfig */
        $tcaConfig = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = $tcaConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // New record field should be processed
        self::assertArrayHasKey('bodytext', $fieldArray);
        self::assertIsString($fieldArray['bodytext']);
        self::assertNotEmpty($fieldArray['bodytext']);
        self::assertStringContainsString('New record content', $fieldArray['bodytext']);
    }

    #[Test]
    public function hookIsRegisteredInGlobals(): void
    {
        // Verify hook is properly registered in TYPO3_CONF_VARS
        // @phpstan-ignore-next-line argument.type
        self::assertArrayHasKey('SC_OPTIONS', $GLOBALS['TYPO3_CONF_VARS']);
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        self::assertIsArray($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']);
        self::assertArrayHasKey(
            't3lib/class.t3lib_tcemain.php',
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS'],
        );
        self::assertIsArray($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']);
        self::assertArrayHasKey(
            'processDatamapClass',
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php'],
        );

        $registeredHooks = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'];
        self::assertIsArray($registeredHooks);

        // RteImagesDbHook should be registered
        self::assertContains(RteImagesDbHook::class, $registeredHooks);
    }

    // ========================================================================
    // modifyRteField Tests (via processDatamap_postProcessFieldArray)
    // ========================================================================

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesTextTypeWithoutRichtext(): void
    {
        $subject      = $this->createSubject();
        $status       = 'update';
        $table        = 'tt_content';
        $id           = '1';
        $originalText = 'Plain text content';
        $fieldArray   = [
            'bodytext' => $originalText,
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for text field WITHOUT enableRichtext
        /** @var array<string, mixed> $tcaConfig */
        $tcaConfig = [
            'type' => 'text',
            'rows' => 10,
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = $tcaConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // Text without enableRichtext should remain unchanged
        self::assertSame($originalText, $fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesRichtextDisabled(): void
    {
        $subject      = $this->createSubject();
        $status       = 'update';
        $table        = 'tt_content';
        $id           = '1';
        $originalText = '<p>HTML content</p>';
        $fieldArray   = [
            'bodytext' => $originalText,
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for text field with enableRichtext = false
        /** @var array<string, mixed> $tcaConfig */
        $tcaConfig = [
            'type'           => 'text',
            'enableRichtext' => false,
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = $tcaConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // enableRichtext=false should remain unchanged
        self::assertSame($originalText, $fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesNullFieldValue(): void
    {
        $subject    = $this->createSubject();
        $status     = 'update';
        $table      = 'tt_content';
        $id         = '1';
        $fieldArray = [
            'bodytext' => null,
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for RTE field
        /** @var array<string, mixed> $tcaConfig */
        $tcaConfig = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = $tcaConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // Null value should remain null
        self::assertNull($fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesContentWithoutImages(): void
    {
        $subject    = $this->createSubject();
        $status     = 'update';
        $table      = 'tt_content';
        $id         = '1';
        $content    = '<p>This is a paragraph with <strong>bold</strong> text but no images.</p>';
        $fieldArray = [
            'bodytext' => $content,
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for RTE field
        /** @var array<string, mixed> $tcaConfig */
        $tcaConfig = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = $tcaConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // Content without images should be processed but remain essentially unchanged
        self::assertStringContainsString('This is a paragraph', $fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesEmptyString(): void
    {
        $subject    = $this->createSubject();
        $status     = 'update';
        $table      = 'tt_content';
        $id         = '1';
        $fieldArray = [
            'bodytext' => '',
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for RTE field
        /** @var array<string, mixed> $tcaConfig */
        $tcaConfig = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = $tcaConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // Empty string should remain empty
        self::assertSame('', $fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesMultipleRteFields(): void
    {
        $subject    = $this->createSubject();
        $status     = 'update';
        $table      = 'tt_content';
        $id         = '1';
        $fieldArray = [
            'bodytext'  => '<p>Body text content</p>',
            'header'    => 'Not an RTE field',
            'subheader' => 'Also not RTE',
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA
        /** @var array<string, mixed> $bodytextConfig */
        $bodytextConfig = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];
        /** @var array<string, mixed> $headerConfig */
        $headerConfig = [
            'type' => 'input',
        ];
        /** @var array<string, mixed> $subheaderConfig */
        $subheaderConfig = [
            'type' => 'input',
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = $bodytextConfig;
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['header']['config'] = $headerConfig;
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['subheader']['config'] = $subheaderConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // Non-RTE fields should remain unchanged
        self::assertSame('Not an RTE field', $fieldArray['header']);
        self::assertSame('Also not RTE', $fieldArray['subheader']);
        // RTE field should be processed
        self::assertStringContainsString('Body text content', $fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesDeleteStatus(): void
    {
        $subject    = $this->createSubject();
        $status     = 'delete';
        $table      = 'tt_content';
        $id         = '1';
        $fieldArray = [
            'bodytext' => '<p>Content to delete</p>',
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for RTE field
        /** @var array<string, mixed> $tcaConfig */
        $tcaConfig = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = $tcaConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // Delete operations should still process field values
        self::assertStringContainsString('Content to delete', $fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesFieldWithoutTypeKey(): void
    {
        $subject    = $this->createSubject();
        $status     = 'update';
        $table      = 'tt_content';
        $id         = '1';
        $fieldArray = [
            'bodytext' => 'Some content',
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA WITHOUT type key
        /** @var array<string, mixed> $tcaConfig */
        $tcaConfig = [
            'rows' => 10,
            'cols' => 80,
        ];
        // @phpstan-ignore-next-line offsetAccess.nonOffsetAccessible
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = $tcaConfig;

        $subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler,
        );

        // Field without type should remain unchanged
        self::assertSame('Some content', $fieldArray['bodytext']);
    }
}
