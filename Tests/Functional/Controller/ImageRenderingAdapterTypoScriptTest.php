<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Controller;

use Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for ImageRenderingAdapter TypoScript integration.
 *
 * Tests the actual TypoScript preUserFunc path to ensure the adapter
 * is callable via TypoScript (requires #[AsAllowedCallable] in TYPO3 v14+).
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.0/Breaking-108054-EnforceExplicitOpt-inForTypoScriptTSconfigCallables.html
 */
final class ImageRenderingAdapterTypoScriptTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    /** @phpstan-ignore property.uninitialized (initialized in setUp) */
    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();

        // Import test data
        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file.csv');

        // Create a minimal site configuration
        $site = new Site('test', 1, [
            'base'      => 'http://localhost/',
            'languages' => [
                [
                    'languageId' => 0,
                    'title'      => 'English',
                    'locale'     => 'en_US.UTF-8',
                    'base'       => '/',
                ],
            ],
        ]);

        // Create request with site
        $this->request = (new ServerRequest())
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());
    }

    #[Test]
    public function adapterIsCallableFromTypoScriptContext(): void
    {
        // Get adapter from DI container (same as TypoScript would)
        $adapter = $this->get(ImageRenderingAdapter::class);

        // Create ContentObjectRenderer (simulates TypoScript context)
        /** @var ContentObjectRenderer $contentObjectRenderer */
        $contentObjectRenderer             = $this->get(ContentObjectRenderer::class);
        $contentObjectRenderer->parameters = [
            'src'    => '/test.jpg',
            'alt'    => 'Test Image',
            'width'  => '800',
            'height' => '600',
        ];

        // Set ContentObjectRenderer (as TYPO3 does before calling preUserFunc)
        $adapter->setContentObjectRenderer($contentObjectRenderer);

        // Call the method that TypoScript uses via preUserFunc
        // In TYPO3 v14+, this would throw AllowedCallableException if #[AsAllowedCallable] is missing
        $result = $adapter->renderImageAttributes(null, [], $this->request);

        // External image (no file-uid) should render with original src
        self::assertIsString($result);
    }

    #[Test]
    public function renderImagesMethodIsCallableFromTypoScriptContext(): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        $contentObjectRenderer = $this->get(ContentObjectRenderer::class);

        // Simulate link content with embedded image
        $linkContent = '<img src="/test.jpg" alt="Test" />';

        // Mock getCurrentVal to return link content
        $reflection = new ReflectionClass($contentObjectRenderer);
        if ($reflection->hasProperty('currentValKey')) {
            $contentObjectRenderer->setCurrentVal($linkContent);
        }

        $adapter->setContentObjectRenderer($contentObjectRenderer);

        // Call the method used by tags.a.preUserFunc
        $result = $adapter->renderImages(null, [], $this->request);

        self::assertIsString($result);
    }

    #[Test]
    public function adapterHasAsAllowedCallableAttribute(): void
    {
        $reflection = new ReflectionClass(ImageRenderingAdapter::class);

        // Check renderImageAttributes method
        $renderImageAttributesMethod = $reflection->getMethod('renderImageAttributes');
        $attributes                  = $renderImageAttributesMethod->getAttributes();

        $hasAsAllowedCallable = false;

        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AsAllowedCallable')) {
                $hasAsAllowedCallable = true;

                break;
            }
        }

        self::assertTrue(
            $hasAsAllowedCallable,
            'Method renderImageAttributes must have #[AsAllowedCallable] attribute for TYPO3 v14+ compatibility',
        );

        // Check renderImages method
        $renderImagesMethod = $reflection->getMethod('renderImages');
        $attributes         = $renderImagesMethod->getAttributes();

        $hasAsAllowedCallable = false;

        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AsAllowedCallable')) {
                $hasAsAllowedCallable = true;

                break;
            }
        }

        self::assertTrue(
            $hasAsAllowedCallable,
            'Method renderImages must have #[AsAllowedCallable] attribute for TYPO3 v14+ compatibility',
        );

        // Check renderInlineLink method (primary tags.a handler)
        $renderInlineLinkMethod = $reflection->getMethod('renderInlineLink');
        $attributes             = $renderInlineLinkMethod->getAttributes();

        $hasAsAllowedCallable = false;

        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AsAllowedCallable')) {
                $hasAsAllowedCallable = true;

                break;
            }
        }

        self::assertTrue(
            $hasAsAllowedCallable,
            'Method renderInlineLink must have #[AsAllowedCallable] attribute for TYPO3 v14+ compatibility',
        );
    }
}
