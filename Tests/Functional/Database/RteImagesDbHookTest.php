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

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content.csv');
    }

    private function createSubject(): RteImagesDbHook
    {
        // Get services from container with proper dependency injection
        return new RteImagesDbHook(
            $this->get(ExtensionConfiguration::class),
            $this->get(LogManager::class),
            $this->get(ResourceFactory::class),
            $this->get(Context::class),
            $this->get(RequestFactory::class),
            $this->get(DefaultUploadFolderResolver::class),
        );
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
}
