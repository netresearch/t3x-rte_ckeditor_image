<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Listener\FileOperation;

use Netresearch\RteCKEditorImage\Listener\FileOperation\UpdateImageReferences;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileRenamedEvent;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for UpdateImageReferences event listener.
 *
 * Tests the full chain: event → sys_refindex lookup → HTML parsing → SQL update.
 */
class UpdateImageReferencesTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    #[Test]
    public function fileRenameUpdatesSrcInBodytext(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FileRenameImport.csv');

        $listener        = $this->get(UpdateImageReferences::class);
        $resourceFactory = $this->get(ResourceFactory::class);

        // Get the file and simulate rename (file is already at new location in FAL)
        $file = $resourceFactory->getFileObject(1);

        $event = new AfterFileRenamedEvent($file, 'old-name.jpg');
        $listener->handleFileRenamed($event);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/FileRenameResult.csv');
    }

    #[Test]
    public function fileMovedUpdatesSrcInBodytext(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FileMoveImport.csv');

        $listener        = $this->get(UpdateImageReferences::class);
        $resourceFactory = $this->get(ResourceFactory::class);

        $file         = $resourceFactory->getFileObject(1);
        $targetFolder = $resourceFactory->getFolderObjectFromCombinedIdentifier('1:/subdir/');
        $sourceFolder = $resourceFactory->getFolderObjectFromCombinedIdentifier('1:/');

        $event = new AfterFileMovedEvent($file, $targetFolder, $sourceFolder);
        $listener->handleFileMoved($event);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/FileMoveResult.csv');
    }

    #[Test]
    public function multipleRecordsUpdatedForSameFile(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/MultipleRecordsImport.csv');

        $listener        = $this->get(UpdateImageReferences::class);
        $resourceFactory = $this->get(ResourceFactory::class);

        $file = $resourceFactory->getFileObject(1);

        $event = new AfterFileRenamedEvent($file, 'old.jpg');
        $listener->handleFileRenamed($event);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/MultipleRecordsResult.csv');
    }

    #[Test]
    public function unrelatedRecordsUntouched(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/UnrelatedRecordsImport.csv');

        $listener        = $this->get(UpdateImageReferences::class);
        $resourceFactory = $this->get(ResourceFactory::class);

        $file = $resourceFactory->getFileObject(1);

        $event = new AfterFileRenamedEvent($file, 'old.jpg');
        $listener->handleFileRenamed($event);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/UnrelatedRecordsResult.csv');
    }
}
