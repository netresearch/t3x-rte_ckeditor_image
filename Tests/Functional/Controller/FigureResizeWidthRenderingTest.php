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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for CKEditor 5 image resize rendering.
 *
 * CKEditor 5's resize handles store the chosen size as a `width` declaration on
 * the <figure> element (class `image_resized`) and leave the <img> at its
 * intrinsic pixel size. Before the fix for issue #863 the frontend replaced that
 * declaration with the computed `max-width`, so every resized image rendered at
 * full width.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/863
 */
final class FigureResizeWidthRenderingTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    private ServerRequestInterface $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file_storage.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/sys_file.csv');

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

        $this->request = (new ServerRequest())
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage());
    }

    /**
     * Render a stored RTE figure through the TypoScript entry point.
     */
    private function renderFigure(string $inputHtml): string
    {
        $adapter = $this->get(ImageRenderingAdapter::class);

        /** @var ContentObjectRenderer $contentObjectRenderer */
        $contentObjectRenderer = $this->get(ContentObjectRenderer::class);

        $contentObjectRenderer->setCurrentVal($inputHtml);
        $adapter->setContentObjectRenderer($contentObjectRenderer);

        /** @var string $result */
        $result = $adapter->renderFigure(null, [], $this->request);

        return $result;
    }

    /**
     * The reported case: a resized image with a caption.
     */
    #[Test]
    public function percentageResizeWithCaptionKeepsWidthOnFigure(): void
    {
        $result = $this->renderFigure(
            '<figure class="image image_resized" style="width:26.43%;">'
            . '<img src="/fileadmin/test-image.jpg" width="1334" height="1000" data-htmlarea-file-uid="1" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>',
        );

        // Asserted in full: the figure carries the author width while the <img>
        // is stretched to fill it. Without the `width:100%` on the image the
        // narrowed figure would simply be overflowed by the 1334px image, and a
        // substring assertion on the figure alone would not notice.
        self::assertSame(
            '<figure class="image image_resized" style="width:26.43%;max-width: 1334px">'
            . '<img src="fileadmin/test-image.jpg" alt="Test" width="1334" height="1000" decoding="async" style="width:100%;height:auto" />'
            . '<figcaption>Caption</figcaption>'
            . '</figure>',
            $result,
        );
    }

    /**
     * The computed max-width from #667 keeps captions from overflowing the image
     * and must survive alongside the restored author width.
     */
    #[Test]
    public function percentageResizeWithCaptionKeepsComputedMaxWidth(): void
    {
        $result = $this->renderFigure(
            '<figure class="image image_resized" style="width:26.43%;">'
            . '<img src="/fileadmin/test-image.jpg" width="1334" height="1000" data-htmlarea-file-uid="1" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>',
        );

        self::assertStringContainsString('max-width', $result, 'Result: ' . $result);
    }

    #[Test]
    public function percentageResizeWithCaptionKeepsResizedClass(): void
    {
        $result = $this->renderFigure(
            '<figure class="image image_resized" style="width:26.43%;">'
            . '<img src="/fileadmin/test-image.jpg" width="1334" height="1000" data-htmlarea-file-uid="1" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>',
        );

        self::assertStringContainsString('image_resized', $result, 'Result: ' . $result);
    }

    /**
     * Without a caption no <figure> wrapper is emitted (see #595), so the width
     * has to travel on the <img> instead.
     */
    #[Test]
    public function percentageResizeWithoutCaptionKeepsWidthOnImage(): void
    {
        $result = $this->renderFigure(
            '<figure class="image image_resized" style="width:26.43%;">'
            . '<img src="/fileadmin/test-image.jpg" width="1334" height="1000" data-htmlarea-file-uid="1" alt="Test">'
            . '</figure>',
        );

        // `height:auto` is required: the height attribute would otherwise pin the
        // image to its intrinsic 1000px and distort it once the width is relative.
        self::assertSame(
            '<img src="fileadmin/test-image.jpg" alt="Test" width="1334" height="1000" decoding="async"'
            . ' class="image image_resized" style="width:26.43%;height:auto" />',
            $result,
        );
    }

    #[Test]
    public function pixelResizeWithCaptionKeepsWidthOnFigure(): void
    {
        $result = $this->renderFigure(
            '<figure class="image image_resized" style="width:320px;">'
            . '<img src="/fileadmin/test-image.jpg" width="1334" height="1000" data-htmlarea-file-uid="1" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>',
        );

        self::assertStringContainsString('width:320px', $result, 'Result: ' . $result);
    }

    /**
     * Resizing a linked image must keep both the width and the link.
     */
    #[Test]
    public function percentageResizeOnLinkedImageKeepsWidthAndLink(): void
    {
        $result = $this->renderFigure(
            '<figure class="image image_resized" style="width:33%;">'
            . '<a href="https://example.com/target">'
            . '<img src="/fileadmin/test-image.jpg" width="1334" height="1000" data-htmlarea-file-uid="1" alt="Test">'
            . '</a>'
            . '<figcaption>Caption</figcaption>'
            . '</figure>',
        );

        self::assertStringContainsString('width:33%', $result, 'Result: ' . $result);
        self::assertStringContainsString('https://example.com/target', $result, 'Result: ' . $result);
    }

    /**
     * An image that was never resized must render exactly as before.
     */
    #[Test]
    public function unresizedImageWithCaptionGetsNoWidthDeclaration(): void
    {
        $result = $this->renderFigure(
            '<figure class="image">'
            . '<img src="/fileadmin/test-image.jpg" width="1334" height="1000" data-htmlarea-file-uid="1" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>',
        );

        self::assertStringNotContainsString('width:26.43%', $result, 'Result: ' . $result);
        self::assertStringContainsString('max-width', $result, 'Result: ' . $result);
    }

    /**
     * Declarations the author could smuggle into the stored style attribute.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function unsafeFigureStyleProvider(): array
    {
        return [
            'url in sibling declaration' => ['width:26%;background:url(//evil.example/x);', 'evil.example'],
            'behavior property'          => ['width:26%;behavior:url(//evil.example/x.htc);', 'evil.example'],
            'legacy IE expression'       => ['width:expression(alert(1));', 'expression'],
            'url as width value'         => ['width:url(//evil.example/x);', 'evil.example'],
            'position fixed overlay'     => ['width:26%;position:fixed;top:0;left:0;', 'position'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeFigureStyleProvider')]
    public function unsafeDeclarationsNeverReachTheOutput(string $style, string $forbidden): void
    {
        $result = $this->renderFigure(
            '<figure class="image image_resized" style="' . $style . '">'
            . '<img src="/fileadmin/test-image.jpg" width="1334" height="1000" data-htmlarea-file-uid="1" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>',
        );

        self::assertStringNotContainsString($forbidden, $result, 'Result: ' . $result);
    }

    /**
     * Re-rendering the frontend output must not accumulate or drop the width.
     */
    #[Test]
    public function renderingResizedFigureIsIdempotent(): void
    {
        $input = '<figure class="image image_resized" style="width:26.43%;">'
            . '<img src="/fileadmin/test-image.jpg" width="1334" height="1000" data-htmlarea-file-uid="1" alt="Test">'
            . '<figcaption>Caption</figcaption>'
            . '</figure>';

        $first  = $this->renderFigure($input);
        $second = $this->renderFigure($first);

        self::assertSame(
            substr_count($first, 'width:26.43%'),
            substr_count($second, 'width:26.43%'),
            'First: ' . $first . ' Second: ' . $second,
        );
    }
}
