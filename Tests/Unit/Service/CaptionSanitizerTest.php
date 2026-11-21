<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service;

use Netresearch\RteCKEditorImage\Service\CaptionSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Allowlist and XSS tests for figcaption markup.
 *
 * Allowed: br, strong, b, em, i, a (href, target, title, rel). Everything
 * else is reduced to its text content. The href filter decides which link
 * targets survive; the tests use the protocol allowlist of the resolver.
 */
#[CoversClass(CaptionSanitizer::class)]
final class CaptionSanitizerTest extends UnitTestCase
{
    private function sanitize(string $html): string
    {
        return CaptionSanitizer::sanitize($html, static function (string $href): ?string {
            $lower = strtolower($href);
            foreach (['http:', 'https:', 'mailto:', 'tel:'] as $protocol) {
                if (str_starts_with($lower, $protocol)) {
                    return $href;
                }
            }

            return str_starts_with($lower, '/') || !str_contains($lower, ':') ? $href : null;
        });
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function allowedMarkupProvider(): array
    {
        return [
            'plain text'              => ['Plain caption', 'Plain caption'],
            'surrounding whitespace'  => ['  Caption  ', 'Caption'],
            'line break'              => ['Line1<br>Line2', 'Line1<br>Line2'],
            'self-closing line break' => ['Line1<br/>Line2', 'Line1<br>Line2'],
            'strong'                  => ['Line2 <strong>bold</strong>', 'Line2 <strong>bold</strong>'],
            'b, em, i'                => ['<b>b</b> <em>em</em> <i>i</i>', '<b>b</b> <em>em</em> <i>i</i>'],
            'nested allowed'          => ['<strong><em>both</em></strong>', '<strong><em>both</em></strong>'],
            'uppercase tag'           => ['<STRONG>bold</STRONG>', '<strong>bold</strong>'],
            'link'                    => ['by <a href="https://example.com/a">Author</a>', 'by <a href="https://example.com/a">Author</a>'],
            'link with title'         => ['<a href="/path" title="T">x</a>', '<a href="/path" title="T">x</a>'],
            'mailto and tel'          => ['<a href="mailto:a@example.com">m</a> <a href="tel:+491">t</a>', '<a href="mailto:a@example.com">m</a> <a href="tel:+491">t</a>'],
            'formatted link text'     => ['<a href="/p"><strong>x</strong></a>', '<a href="/p"><strong>x</strong></a>'],
            'umlauts'                 => ['Grüße <b>ÄÖÜß</b>', 'Grüße <b>ÄÖÜß</b>'],
            'encoded special chars'   => ['Tom &amp; Jerry &lt;3 &quot;q&quot;', 'Tom &amp; Jerry &lt;3 "q"'],
            'bare ampersand'          => ['A & B', 'A &amp; B'],
            'attribute with quote'    => ['<a href="/p" title="a&quot;b">x</a>', '<a href="/p" title=\'a"b\'>x</a>'],
            'attribute with quotes'   => ['<a href="/p" title="a&quot;b\'c">x</a>', '<a href="/p" title="a&quot;b\'c">x</a>'],
        ];
    }

    #[Test]
    #[DataProvider('allowedMarkupProvider')]
    public function keepsAllowedInlineMarkup(string $input, string $expected): void
    {
        self::assertSame($expected, $this->sanitize($input));
    }

    #[Test]
    public function returnsEmptyStringForEmptyInput(): void
    {
        self::assertSame('', $this->sanitize(''));
        self::assertSame('', $this->sanitize('   '));
    }

    #[Test]
    public function blankTargetGetsNoreferrerForExternalLink(): void
    {
        self::assertSame(
            '<a href="https://example.com" target="_blank" rel="noreferrer">x</a>',
            $this->sanitize('<a href="https://example.com" target="_blank">x</a>'),
        );
    }

    #[Test]
    public function existingRelIsKeptAndNoreferrerAddedOnce(): void
    {
        self::assertSame(
            '<a href="https://example.com" target="_blank" rel="nofollow noreferrer">x</a>',
            $this->sanitize('<a href="https://example.com" target="_blank" rel="nofollow noreferrer">x</a>'),
        );
    }

    #[Test]
    public function hrefFilterReceivesTrimmedHrefAndItsResultIsUsed(): void
    {
        $seen   = [];
        $result = CaptionSanitizer::sanitize(
            '<a href="  t3://page?uid=1  ">home</a>',
            static function (string $href) use (&$seen): string {
                $seen[] = $href;

                return '/home';
            },
        );

        self::assertSame(['t3://page?uid=1'], $seen);
        self::assertSame('<a href="/home">home</a>', $result);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsafeMarkupProvider(): array
    {
        return [
            'img onerror'               => ['Caption<img src="x" onerror="alert(1)">', 'Caption'],
            'onclick on allowed tag'    => ['<strong onclick="alert(1)">x</strong>', '<strong>x</strong>'],
            'onmouseover on link'       => ['<a href="/p" onmouseover="alert(1)">x</a>', '<a href="/p">x</a>'],
            'style on allowed tag'      => ['<em style="color:red">x</em>', '<em>x</em>'],
            'style on link'             => ['<a href="/p" style="position:fixed">x</a>', '<a href="/p">x</a>'],
            'class and id dropped'      => ['<b class="c" id="i">x</b>', '<b>x</b>'],
            'span reduced to text'      => ['<span class="c" style="color:red">Styled</span>', 'Styled'],
            'paragraph reduced to text' => ['<p>Paragraph caption</p>', 'Paragraph caption'],
            'script reduced to text'    => ['Caption <script>alert(1)</script>', 'Caption alert(1)'],
            'style element as text'     => ['<style>body{display:none}</style>x', 'body{display:none}x'],
            'iframe dropped'            => ['<iframe src="https://evil.example"></iframe>x', 'x'],
            'svg onload'                => ['<svg onload="alert(1)"><circle/></svg>x', 'x'],
            'nested disallowed tags'    => ['<div><span><strong>x</strong></span></div>', 'x'],
            'allowed inside disallowed' => ['<u><b>bold</b> under</u>', 'bold under'],
            'unclosed allowed tag'      => ['<strong>bold', '<strong>bold</strong>'],
            'unclosed disallowed tag'   => ['x <span onclick="alert(1)">y', 'x y'],
            'unclosed attribute'        => ['<a href="https://example.com/a onclick=alert(1)>x', '<a href="https://example.com/a%20onclick=alert(1)&gt;x"></a>'],
            'comment dropped'           => ['a<!-- <script>alert(1)</script> -->b', 'ab'],
            'javascript href'           => ['<a href="javascript:alert(1)">Evil</a>', 'Evil'],
            'uppercase javascript href' => ['<a href="JaVaScRiPt:alert(1)">Evil</a>', 'Evil'],
            'entity-encoded javascript' => ['<a href="&#106;avascript:alert(1)">Evil</a>', 'Evil'],
            'hex-encoded javascript'    => ['<a href="&#x6A;&#x61;vascript&#x3A;alert(1)">Evil</a>', 'Evil'],
            'tab inside scheme'         => ["<a href=\"java\tscript:alert(1)\">Evil</a>", 'Evil'],
            'newline inside scheme'     => ["<a href=\"java\nscript:alert(1)\">Evil</a>", 'Evil'],
            'leading control character' => ["<a href=\"\x01javascript:alert(1)\">Evil</a>", 'Evil'],
            'data href'                 => ['<a href="data:text/html;base64,PHNjcmlwdD4=">Evil</a>', 'Evil'],
            'vbscript href'             => ['<a href="vbscript:msgbox(1)">Evil</a>', 'Evil'],
            'link without href'         => ['<a name="x">anchor</a>', 'anchor'],
            'empty href'                => ['<a href="">x</a>', 'x'],
            'rejected link keeps text'  => ['<a href="javascript:alert(1)"><strong>Evil</strong></a>', 'Evil'],
            'nested link'               => ['<a href="/a">x<a href="/b">y</a></a>', '<a href="/a">x</a><a href="/b">y</a>'],
            'encoded tag stays text'    => ['&lt;script&gt;alert(1)&lt;/script&gt;', '&lt;script&gt;alert(1)&lt;/script&gt;'],
            'figcaption breakout'       => ['x</figcaption><script>alert(1)</script>', 'xalert(1)'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeMarkupProvider')]
    public function reducesUnsafeMarkupToText(string $input, string $expected): void
    {
        self::assertSame($expected, $this->sanitize($input));
    }

    #[Test]
    public function colonEntityDoesNotProduceAScheme(): void
    {
        // libxml does not decode the HTML5 entity &colon;, so the href is a
        // relative path. It is written back with the ampersand encoded, so a
        // browser never sees a javascript: scheme.
        $result = $this->sanitize('<a href="javascript&colon;alert(1)">x</a>');

        self::assertStringNotContainsString('javascript:', $result);
        self::assertStringNotContainsString('&colon;', str_replace('&amp;colon;', '', $result));
    }

    #[Test]
    #[DataProvider('unsafeMarkupProvider')]
    #[DataProvider('allowedMarkupProvider')]
    public function sanitizingTwiceGivesTheSameResult(string $input, string $expected): void
    {
        $once = $this->sanitize($input);

        self::assertSame($expected, $once);
        self::assertSame($once, $this->sanitize($once));
    }
}
