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
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for ImageRenderingController FAL integration.
 *
 * @covers \Netresearch\RteCKEditorImage\Controller\ImageRenderingController
 */
final class ImageRenderingControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    private ImageRenderingController $subject;
    private ResourceStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file.csv');

        $this->subject = $this->get(ImageRenderingController::class);

        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = $this->get(ResourceFactory::class);
        $this->storage   = $resourceFactory->getStorageObject(1);
    }

    #[Test]
    public function renderImageReturnsJsonResponse(): void
    {
        /** @var ServerRequestInterface $request */
        $request = $this->get(ServerRequestInterface::class);

        $response = $this->subject->renderImage($request);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertInstanceOf(JsonResponse::class, $response);
    }

    #[Test]
    public function renderImageLinkReturnsJsonResponse(): void
    {
        /** @var ServerRequestInterface $request */
        $request = $this->get(ServerRequestInterface::class);

        $response = $this->subject->renderImageLink($request);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertInstanceOf(JsonResponse::class, $response);
    }

    #[Test]
    public function storageIsAccessible(): void
    {
        self::assertInstanceOf(ResourceStorage::class, $this->storage);
        self::assertTrue($this->storage->isOnline());
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
        $rootFolder = $this->storage->getRootLevelFolder();

        self::assertInstanceOf(Folder::class, $rootFolder);
        self::assertSame('/', $rootFolder->getIdentifier());
    }
}
