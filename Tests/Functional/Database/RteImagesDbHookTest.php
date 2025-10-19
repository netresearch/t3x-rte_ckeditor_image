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
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\DefaultUploadFolderResolver;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for RteImagesDbHook DataHandler integration.
 *
 * @covers \Netresearch\RteCKEditorImage\Database\RteImagesDbHook
 */
final class RteImagesDbHookTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    private RteImagesDbHook $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content.csv');

        // Get services from container with proper dependency injection
        $this->subject = new RteImagesDbHook(
            $this->get(ExtensionConfiguration::class),
            $this->get(LogManager::class),
            $this->get(ResourceFactory::class),
            $this->get(Context::class),
            $this->get(RequestFactory::class),
            $this->get(DefaultUploadFolderResolver::class),
        );
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesRteField(): void
    {
        $status       = 'update';
        $table        = 'tt_content';
        $id           = '1';
        $fieldArray   = [
            'bodytext' => '<p>Test content with <img src="image.jpg" width="300" height="200" /></p>',
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for RTE field
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler
        );

        // Field should still contain content (hook processes RTE fields)
        self::assertNotEmpty($fieldArray['bodytext']);
        self::assertStringContainsString('Test content', $fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayIgnoresNonRteField(): void
    {
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
        $GLOBALS['TCA']['tt_content']['columns']['header']['config'] = [
            'type' => 'input',
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler
        );

        // Non-RTE field should remain unchanged
        self::assertSame($originalText, $fieldArray['header']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesNewRecord(): void
    {
        $status     = 'new';
        $table      = 'tt_content';
        $id         = 'NEW123456';
        $fieldArray = [
            'bodytext' => '<p>New record content</p>',
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA for RTE field
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler
        );

        // New record field should be processed
        self::assertNotEmpty($fieldArray['bodytext']);
        self::assertStringContainsString('New record content', $fieldArray['bodytext']);
    }

    #[Test]
    public function processDatamapPostProcessFieldArrayHandlesMultipleFields(): void
    {
        $status     = 'update';
        $table      = 'tt_content';
        $id         = '1';
        $fieldArray = [
            'header'   => 'Test Header',
            'bodytext' => '<p>Test body with <img src="test.jpg" /></p>',
        ];

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->get(DataHandler::class);

        // Configure TCA
        $GLOBALS['TCA']['tt_content']['columns']['header']['config']   = [
            'type' => 'input',
        ];
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config'] = [
            'type'           => 'text',
            'enableRichtext' => true,
        ];

        $this->subject->processDatamap_postProcessFieldArray(
            $status,
            $table,
            $id,
            $fieldArray,
            $dataHandler
        );

        // Both fields should be present
        self::assertSame('Test Header', $fieldArray['header']);
        self::assertNotEmpty($fieldArray['bodytext']);
    }

    #[Test]
    public function hookIsRegisteredInGlobals(): void
    {
        // Verify hook is properly registered in TYPO3_CONF_VARS
        self::assertArrayHasKey('SC_OPTIONS', $GLOBALS['TYPO3_CONF_VARS']);
        self::assertArrayHasKey(
            't3lib/class.t3lib_tcemain.php',
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']
        );
        self::assertArrayHasKey(
            'processDatamapClass',
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']
        );

        $registeredHooks = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'];

        // RteImagesDbHook should be registered
        self::assertContains(RteImagesDbHook::class, $registeredHooks);
    }
}
