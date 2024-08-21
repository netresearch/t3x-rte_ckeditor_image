<?php

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit;

use Netresearch\RteCKEditorImage\Controller\ImageLinkRenderingController;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Service\MagicImageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class ImageLinkRenderingControllerTest extends TestCase
{
    private ImageLinkRenderingController $imageLinkRenderingController;

    private MockObject $contentObjectRendererMock;

    private MockObject $resourceFactoryMock;

    private MockObject $magicImageServiceMock;

    private MockObject $loggerMock;

    protected function setUp(): void
    {
        $this->contentObjectRendererMock = $this->createMock(ContentObjectRenderer::class);
        $this->resourceFactoryMock = $this->createMock(ResourceFactory::class);
        $this->magicImageServiceMock = $this->createMock(MagicImageService::class);
        $this->loggerMock = $this->createMock(Logger::class);

        $this->imageLinkRenderingController = new ImageLinkRenderingController();
        $this->imageLinkRenderingController->setContentObjectRenderer($this->contentObjectRendererMock);

        GeneralUtility::setSingletonInstance(ResourceFactory::class, $this->resourceFactoryMock);
        GeneralUtility::addInstance(MagicImageService::class, $this->magicImageServiceMock);
        GeneralUtility::setSingletonInstance(LogManager::class, $this->createConfiguredMock(LogManager::class, [
            'getLogger' => $this->loggerMock,
        ]));
    }

    #[Test]
    public function renderImagesLazyLoadingAttribute(): void
    {
        $sampleContent = '<p><img src="image.jpg" data-htmlarea-file-uid="123"/></p>';
        $this->contentObjectRendererMock->method('getCurrentVal')->willReturn($sampleContent);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')->willReturnMap([
            ['width', 100],
            ['height', 100],
            ['title', 'Sample Title'],
            ['alt', 'Sample Alt'],
        ]);

        $this->resourceFactoryMock->method('getFileObject')->willReturn($fileMock);

        $processedFileMock = $this->createMock(File::class);
        $processedFileMock->method('getPublicUrl')->willReturn('processed-url');
        $processedFileMock->method('getProperty')->willReturnMap([
            ['width', 100],
            ['height', 100],
        ]);

        $this->magicImageServiceMock->method('createMagicImage')->willReturn($processedFileMock);

        $expectedOutput
            = '<img src="processed-url" title="Sample Title" alt="Sample Alt" width="100" height="100" loading="lazy" />';
        $actualOutput = $this->imageLinkRenderingController->renderImages(null, []);

        $this->assertSame($expectedOutput, $actualOutput);
    }

    #[Test]
    public function testRenderImages(): void
    {
        $sampleContent
            = '<img src=456 /> <p>some foo bar</p> <p><img src="image.jpg" data-htmlarea-file-uid="123"/></p> some foo bar';
        $this->contentObjectRendererMock->method('getCurrentVal')->willReturn($sampleContent);

        $fileMock = $this->createMock(File::class);
        $fileMock->method('getProperty')->willReturnMap([
            ['width', 100],
            ['height', 100],
            ['title', 'Sample Title'],
            ['alt', 'Sample Alt'],
        ]);

        $this->resourceFactoryMock->method('getFileObject')->willReturn($fileMock);

        $processedFileMock = $this->createMock(File::class);
        $processedFileMock->method('getPublicUrl')->willReturn('processed-url');
        $processedFileMock->method('getProperty')->willReturnMap([
            ['width', 100],
            ['height', 100],
        ]);

        $this->magicImageServiceMock->method('createMagicImage')->willReturn($processedFileMock);

        $expectedOutput
            = '<img src=456 /> <p>some foo bar</p> <img src="processed-url" title="Sample Title" alt="Sample Alt" width="100" height="100" loading="lazy" /> some foo bar';
        $actualOutput = $this->imageLinkRenderingController->renderImages(null, []);

        $this->assertSame($expectedOutput, $actualOutput);
    }
}
