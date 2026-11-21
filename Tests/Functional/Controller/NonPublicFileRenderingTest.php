<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Controller;

use Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\AST\AstBuilder;
use TYPO3\CMS\Core\TypoScript\TypoScriptStringFactory;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Images from a non-public storage must not reach the frontend.
 *
 * Runs RTE content through lib.parseFunc_RTE built from the core default
 * TypoScript plus this extension's setup.typoscript, so every adapter entry
 * point (tags.img, tags.a, externalBlocks.figure) is exercised as configured.
 *
 * @author  Netresearch DTT GmbH
 * @license AGPL-3.0-or-later
 */
final class NonPublicFileRenderingTest extends FunctionalTestCase
{
    /**
     * Stored src of the image in the non-public storage: what RteImageProcessor
     * wrote at save time. It must not appear in the frontend output.
     */
    private const BLOCKED_SRC = '/protected/_processed_/csm_secret-image_0123456789.jpg';

    private const BLOCKED_IMG = '<img src="' . self::BLOCKED_SRC . '" data-htmlarea-file-uid="2"'
        . ' data-htmlarea-file-table="sys_file" width="100" height="100" alt="Secret">';

    private const BLOCKED_INLINE_IMG = '<img class="image-inline" src="' . self::BLOCKED_SRC . '"'
        . ' data-htmlarea-file-uid="2" data-htmlarea-file-table="sys_file" width="100" height="100" alt="Secret">';

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
        'typo3/cms-frontend',
    ];

    private ServerRequest $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/non_public_storage.csv');

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

        $request = new ServerRequest('http://localhost/', 'GET', null, [], [
            'HTTP_HOST'   => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'SCRIPT_NAME' => '/index.php',
            'REQUEST_URI' => '/',
        ]);

        $this->request = $request
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request))
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedImageContentProvider(): iterable
    {
        yield 'standalone image' => ['<p>Before ' . self::BLOCKED_IMG . ' after</p>'];
        yield 'image with caption in figure' => [
            '<figure class="image">' . self::BLOCKED_IMG . '<figcaption>Secret caption</figcaption></figure>',
        ];
        yield 'linked image in figure' => [
            '<figure class="image"><a href="https://example.com/">' . self::BLOCKED_IMG . '</a>'
            . '<figcaption>Secret caption</figcaption></figure>',
        ];
        yield 'inline image inside link' => [
            '<p>Text <a href="https://example.com/">' . self::BLOCKED_INLINE_IMG . '</a> more</p>',
        ];
        yield 'image with data-caption outside figure' => [
            '<p><img src="' . self::BLOCKED_SRC . '" data-htmlarea-file-uid="2" data-htmlarea-file-table="sys_file"'
            . ' width="100" height="100" alt="Secret" data-caption="Secret caption"></p>',
        ];
    }

    #[Test]
    #[DataProvider('blockedImageContentProvider')]
    public function imageFromNonPublicStorageIsNotRendered(string $content): void
    {
        $result = $this->renderRteContent($content);

        self::assertStringNotContainsString(
            self::BLOCKED_SRC,
            $result,
            'The URL of a file in a non-public storage must not appear in the frontend output. Result: ' . $result,
        );
        self::assertStringNotContainsString(
            'secret-image',
            $result,
            'No reference to the non-public file may remain in the output. Result: ' . $result,
        );
        self::assertStringNotContainsString('<img', $result, 'No <img> may be rendered for it. Result: ' . $result);
        self::assertStringNotContainsString(
            'Secret caption',
            $result,
            'The caption of a blocked image must not be rendered either. Result: ' . $result,
        );
    }

    #[Test]
    public function surroundingContentOfBlockedImageIsKept(): void
    {
        $result = $this->renderRteContent('<p>Before ' . self::BLOCKED_IMG . ' after</p><p>Next paragraph</p>');

        self::assertStringContainsString('Before', $result, 'Result: ' . $result);
        self::assertStringContainsString('after', $result, 'Result: ' . $result);
        self::assertStringContainsString('Next paragraph', $result, 'Result: ' . $result);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedImageInLinkProvider(): iterable
    {
        yield 'inline image' => [self::BLOCKED_INLINE_IMG];
        yield 'block image (no image-inline class)' => [self::BLOCKED_IMG];
    }

    /**
     * renderImages() is the tags.a handler for custom TypoScript (not the default setup).
     */
    #[Test]
    #[DataProvider('blockedImageInLinkProvider')]
    public function renderImagesDoesNotRenderImageFromNonPublicStorage(string $image): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);
        $cObj    = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);

        $adapter->setContentObjectRenderer($cObj);

        $cObj->parameters = ['href' => 'https://example.com/'];
        $cObj->setCurrentVal('Link text ' . $image);

        $result = $adapter->renderImages(null, [], $this->request);

        self::assertStringNotContainsString(self::BLOCKED_SRC, $result, 'Result: ' . $result);
        self::assertStringNotContainsString('<img', $result, 'Result: ' . $result);
    }

    /**
     * renderLink() is the externalBlocks.a handler for custom TypoScript (not the default setup).
     */
    #[Test]
    #[DataProvider('blockedImageInLinkProvider')]
    public function renderLinkDoesNotRenderImageFromNonPublicStorage(string $image): void
    {
        $adapter = $this->get(ImageRenderingAdapter::class);
        $cObj    = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);

        $adapter->setContentObjectRenderer($cObj);

        $result = $adapter->renderLink('<a href="https://example.com/">Link text ' . $image . '</a>', [], $this->request);

        self::assertStringNotContainsString(self::BLOCKED_SRC, $result, 'Result: ' . $result);
        self::assertStringNotContainsString('<img', $result, 'Result: ' . $result);
    }

    #[Test]
    public function imageFromPublicStorageIsStillRendered(): void
    {
        $result = $this->renderRteContent(
            '<p>Text <img src="/fileadmin/test-image.jpg" data-htmlarea-file-uid="1"'
            . ' data-htmlarea-file-table="sys_file" width="100" height="100" alt="Public"></p>',
        );

        self::assertStringContainsString('<img', $result, 'A public image must still be rendered. Result: ' . $result);
        self::assertStringContainsString('alt="Public"', $result, 'Result: ' . $result);
    }

    #[Test]
    public function imageWithMissingFileKeepsItsOriginalTag(): void
    {
        $result = $this->renderRteContent(
            '<p>Text <img src="/fileadmin/missing.jpg" data-htmlarea-file-uid="999"'
            . ' data-htmlarea-file-table="sys_file" width="100" height="100" alt="Missing"></p>',
        );

        self::assertStringContainsString(
            'src="/fileadmin/missing.jpg"',
            $result,
            'An image whose file does not exist keeps its original tag. Result: ' . $result,
        );
    }

    #[Test]
    public function figureWithMissingFileKeepsItsOriginalMarkup(): void
    {
        $result = $this->renderRteContent(
            '<figure class="image"><img src="/fileadmin/missing.jpg" data-htmlarea-file-uid="999"'
            . ' width="100" height="100" alt="Missing"><figcaption>Missing caption</figcaption></figure>',
        );

        self::assertStringContainsString('src="/fileadmin/missing.jpg"', $result, 'Result: ' . $result);
        self::assertStringContainsString('Missing caption', $result, 'Result: ' . $result);
    }

    private function renderRteContent(string $content): string
    {
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);

        return $cObj->parseFunc($content, $this->getParseFuncRteConfiguration());
    }

    /**
     * lib.parseFunc_RTE as the frontend builds it: core default TypoScript
     * (EXT:frontend) followed by this extension's setup.typoscript.
     *
     * @return array<string, mixed>
     */
    private function getParseFuncRteConfiguration(): array
    {
        $confVars = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        self::assertIsArray($confVars);
        $frontendConf = $confVars['FE'] ?? null;
        self::assertIsArray($frontendConf);
        $defaultSetup   = $frontendConf['defaultTypoScript_setup'] ?? null;
        $extensionSetup = file_get_contents(
            __DIR__ . '/../../../Configuration/TypoScript/ImageRendering/setup.typoscript',
        );

        self::assertIsString($defaultSetup);
        self::assertIsString($extensionSetup);

        $setup = $this->get(TypoScriptStringFactory::class)
            ->parseFromString($defaultSetup . "\n" . $extensionSetup, new AstBuilder(new NoopEventDispatcher()))
            ->toArray();

        $lib = $setup['lib.'] ?? null;
        self::assertIsArray($lib);
        $parseFuncRte = $lib['parseFunc_RTE.'] ?? null;
        self::assertIsArray($parseFuncRte);
        self::assertArrayHasKey('externalBlocks.', $parseFuncRte, 'Core default lib.parseFunc_RTE must be loaded');

        /** @var array<string, mixed> $parseFuncRte */
        return $parseFuncRte;
    }
}
