<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Controller;

use Netresearch\RteCKEditorImage\Controller\ImageRenderingController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for ImageRenderingController FAL integration.
 */
#[CoversClass(ImageRenderingController::class)]
final class ImageRenderingControllerTest extends FunctionalTestCase
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

        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file.csv');
    }

    private function getStorage(): ResourceStorage
    {
        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = $this->get(ResourceFactory::class);

        return $resourceFactory->getStorageObject(1);
    }

    #[Test]
    public function storageIsAccessible(): void
    {
        $storage = $this->getStorage();

        self::assertInstanceOf(ResourceStorage::class, $storage);
        self::assertTrue($storage->isOnline());
    }

    #[Test]
    public function canRetrieveFileFromStorage(): void
    {
        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = $this->get(ResourceFactory::class);

        // Get file from test data
        $file = $resourceFactory->getFileObject(1);

        self::assertInstanceOf(File::class, $file);
        self::assertSame('test-image.jpg', $file->getName());
    }

    #[Test]
    public function canAccessStorageRootFolder(): void
    {
        $storage    = $this->getStorage();
        $rootFolder = $storage->getRootLevelFolder();

        self::assertInstanceOf(Folder::class, $rootFolder);
        self::assertSame('/', $rootFolder->getIdentifier());
    }
}
