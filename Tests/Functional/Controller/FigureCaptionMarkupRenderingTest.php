<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\EventDispatcher\NoopEventDispatcher;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\AST\AstBuilder;
use TYPO3\CMS\Core\TypoScript\TypoScriptStringFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Renders captioned figures through lib.parseFunc_RTE as the frontend does:
 * the default TypoScript of EXT:frontend plus this extension's setup.typoscript.
 *
 * The figcaption keeps br, strong, b, em, i and a (with an allowed href);
 * everything else, including unsafe links, is reduced to text.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/969
 */
final class FigureCaptionMarkupRenderingTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    private const IMAGE = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" width="250" height="250" alt="Test">';

    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file.csv');

        // typoLink_URL() finds the site through SiteFinder, which reads the file
        $siteDir = Environment::getConfigPath() . '/sites/test';
        GeneralUtility::mkdir_deep($siteDir);
        file_put_contents(
            $siteDir . '/config.yaml',
            "rootPageId: 1\nbase: 'http://localhost/'\nlanguages:\n  -\n    languageId: 0\n    title: English\n    locale: en_US.UTF-8\n    base: '/'\n",
        );

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

        $this->request = (new ServerRequest('http://localhost/'))
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function htmlSanitizeProvider(): array
    {
        return [
            'core htmlSanitize enabled (default)' => [true],
            'core htmlSanitize disabled'          => [false],
        ];
    }

    #[Test]
    #[DataProvider('htmlSanitizeProvider')]
    public function captionKeepsLineBreaksAndBoldText(bool $htmlSanitize): void
    {
        $result = $this->renderThroughParseFuncRte(
            '<figure class="image">' . self::IMAGE
            . '<figcaption>Line1<br>Line2 <strong>bold</strong></figcaption></figure>',
            $htmlSanitize,
        );

        self::assertMatchesRegularExpression(
            '#<figcaption>Line1<br ?/?>Line2 <strong>bold</strong></figcaption>#',
            $result,
            'Caption must keep <br> and <strong>. Result: ' . $result,
        );
    }

    #[Test]
    #[DataProvider('htmlSanitizeProvider')]
    public function captionKeepsSpaceBetweenAdjacentFormattedWords(bool $htmlSanitize): void
    {
        $result = $this->renderThroughParseFuncRte(
            '<figure class="image">' . self::IMAGE
            . '<figcaption><strong>Bold</strong> <em>italic</em></figcaption></figure>',
            $htmlSanitize,
        );

        self::assertStringContainsString('<figcaption><strong>Bold</strong> <em>italic</em></figcaption>', $result, 'Result: ' . $result);
    }

    #[Test]
    #[DataProvider('htmlSanitizeProvider')]
    public function captionKeepsNonAsciiText(bool $htmlSanitize): void
    {
        $result = $this->renderThroughParseFuncRte(
            '<figure class="image">' . self::IMAGE
            . '<figcaption>Grüße&nbsp;<strong>Ä</strong></figcaption></figure>',
            $htmlSanitize,
        );

        self::assertMatchesRegularExpression('#<figcaption>Grüße(\x{00A0}|&nbsp;)<strong>Ä</strong></figcaption>#u', $result, 'Result: ' . $result);
    }

    #[Test]
    #[DataProvider('htmlSanitizeProvider')]
    public function visuallyEmptyCaptionRendersNoFigure(bool $htmlSanitize): void
    {
        $result = $this->renderThroughParseFuncRte(
            '<figure class="image image-left">' . self::IMAGE
            . '<figcaption><strong> </strong><br></figcaption></figure>',
            $htmlSanitize,
        );

        self::assertStringNotContainsString('<figcaption', $result, 'Result: ' . $result);
        self::assertStringContainsString('image-left', $result, 'Alignment class moves to the <img>. Result: ' . $result);
    }

    #[Test]
    #[DataProvider('htmlSanitizeProvider')]
    public function captionDropsJavascriptLinkButKeepsItsText(bool $htmlSanitize): void
    {
        $result = $this->renderThroughParseFuncRte(
            '<figure class="image">' . self::IMAGE
            . '<figcaption>Photo by <a href="javascript:alert(1)">Evil</a></figcaption></figure>',
            $htmlSanitize,
        );

        self::assertStringNotContainsStringIgnoringCase('javascript:', $result, 'Result: ' . $result);
        self::assertStringContainsString('<figcaption>Photo by Evil</figcaption>', $result, 'Result: ' . $result);
    }

    #[Test]
    #[DataProvider('htmlSanitizeProvider')]
    public function captionKeepsSafeLinkAndAddsNoreferrerForBlankTarget(bool $htmlSanitize): void
    {
        $result = $this->renderThroughParseFuncRte(
            '<figure class="image">' . self::IMAGE
            . '<figcaption>Photo by <a href="https://example.com/author" target="_blank" class="x" onclick="alert(1)">Author</a></figcaption></figure>',
            $htmlSanitize,
        );

        self::assertStringContainsString(
            '<a href="https://example.com/author" target="_blank" rel="noreferrer">Author</a>',
            $result,
            'Result: ' . $result,
        );
        self::assertStringNotContainsString('onclick', $result, 'Result: ' . $result);
    }

    #[Test]
    #[DataProvider('htmlSanitizeProvider')]
    public function captionResolvesT3PageLink(bool $htmlSanitize): void
    {
        $result = $this->renderThroughParseFuncRte(
            '<figure class="image">' . self::IMAGE
            . '<figcaption>See <a href="t3://page?uid=1">home</a></figcaption></figure>',
            $htmlSanitize,
        );

        self::assertStringNotContainsString('t3://', $result, 'Result: ' . $result);
        self::assertMatchesRegularExpression('#<figcaption>See <a href="/[^"]*">home</a></figcaption>#', $result, 'Result: ' . $result);
    }

    #[Test]
    #[DataProvider('htmlSanitizeProvider')]
    public function captionUnresolvableT3LinkBecomesText(bool $htmlSanitize): void
    {
        $result = $this->renderThroughParseFuncRte(
            '<figure class="image">' . self::IMAGE
            . '<figcaption>See <a href="t3://page?uid=999">missing</a></figcaption></figure>',
            $htmlSanitize,
        );

        self::assertStringNotContainsString('t3://', $result, 'Result: ' . $result);
        self::assertStringContainsString('<figcaption>See missing</figcaption>', $result, 'Result: ' . $result);
    }

    #[Test]
    #[DataProvider('htmlSanitizeProvider')]
    public function plainCaptionSpecialCharactersAreEncodedOnce(bool $htmlSanitize): void
    {
        $result = $this->renderThroughParseFuncRte(
            '<figure class="image">' . self::IMAGE
            . '<figcaption>Tom &amp; Jerry &lt;3 &quot;quoted&quot;</figcaption></figure>',
            $htmlSanitize,
        );

        self::assertMatchesRegularExpression(
            '#<figcaption>Tom &amp; Jerry &lt;3 (&quot;|")quoted(&quot;|")</figcaption>#',
            $result,
            'Result: ' . $result,
        );
    }

    private function renderThroughParseFuncRte(string $html, bool $htmlSanitize): string
    {
        $confVars = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        self::assertIsArray($confVars);
        $frontendConf = $confVars['FE'] ?? null;
        self::assertIsArray($frontendConf);
        $defaultSetup = $frontendConf['defaultTypoScript_setup'] ?? null;
        self::assertIsString($defaultSetup, 'EXT:frontend default TypoScript (lib.parseFunc_RTE) must be registered');
        $extensionSetup = file_get_contents(__DIR__ . '/../../../Configuration/TypoScript/ImageRendering/setup.typoscript');
        self::assertIsString($extensionSetup);

        $typoScript = $this->get(TypoScriptStringFactory::class)
            ->parseFromString($defaultSetup . "\n" . $extensionSetup, new AstBuilder(new NoopEventDispatcher()))
            ->toArray();

        $lib = $typoScript['lib.'] ?? null;
        self::assertIsArray($lib);
        $parseFuncRte = $lib['parseFunc_RTE.'] ?? null;
        self::assertIsArray($parseFuncRte, 'lib.parseFunc_RTE must be defined');

        if (!$htmlSanitize) {
            $parseFuncRte['htmlSanitize'] = '0';
        }

        /** @var ContentObjectRenderer $cObj */
        $cObj = $this->get(ContentObjectRenderer::class);
        $cObj->setRequest($this->request);

        return $cObj->parseFunc($html, $parseFuncRte);
    }
}
