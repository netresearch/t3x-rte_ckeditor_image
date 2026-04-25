<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service;

use Netresearch\RteCKEditorImage\Service\SecurityRelComputer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the rel-computation helper used by the Fluid Link path.
 *
 * The Fluid Link.html partial constructs <a> directly, bypassing TYPO3
 * typolink — so we mirror LinkFactory::addSecurityRelValues() in PHP and
 * lock its semantics here.
 *
 * @covers \Netresearch\RteCKEditorImage\Service\SecurityRelComputer
 */
final class SecurityRelComputerTest extends TestCase
{
    /**
     * @return iterable<string, array{0: ?string, 1: string, 2: ?string, 3: ?string}>
     */
    public static function computeProvider(): iterable
    {
        // === Adds noreferrer for external + new browsing context ===
        yield 'http _blank gets noreferrer' => [
            '_blank', 'http://example.com', null, 'noreferrer',
        ];
        yield 'https _blank gets noreferrer' => [
            '_blank', 'https://example.com/path', null, 'noreferrer',
        ];
        yield 'protocol-relative _blank gets noreferrer' => [
            '_blank', '//example.com/path', null, 'noreferrer',
        ];
        yield 'uppercase HTTPS scheme matched' => [
            '_blank', 'HTTPS://EXAMPLE.COM', null, 'noreferrer',
        ];
        yield 'leading whitespace url still external' => [
            '_blank', '  https://example.com', null, 'noreferrer',
        ];
        yield 'custom target name like custom_window' => [
            'custom_window', 'https://example.com', null, 'noreferrer',
        ];

        // === Does NOT add noreferrer ===
        yield 'no target → null' => [
            null, 'https://example.com', null, null,
        ];
        yield 'empty target → null' => [
            '', 'https://example.com', null, null,
        ];
        yield '_self target → null' => [
            '_self', 'https://example.com', null, null,
        ];
        yield '_parent target → null' => [
            '_parent', 'https://example.com', null, null,
        ];
        yield '_top target → null' => [
            '_top', 'https://example.com', null, null,
        ];
        yield 'absolute path is internal — no rel' => [
            '_blank', '/fileadmin/foo.jpg', null, null,
        ];
        yield 'fragment is internal — no rel' => [
            '_blank', '#section', null, null,
        ];
        yield 'mailto is non-browsing-context handler — no rel' => [
            '_blank', 'mailto:foo@example.com', null, null,
        ];
        yield 'tel is non-browsing-context handler — no rel' => [
            '_blank', 'tel:+1234567890', null, null,
        ];

        // === Preserves existing rel tokens ===
        yield 'preserves nofollow on external _blank' => [
            '_blank', 'https://example.com', 'nofollow', 'nofollow noreferrer',
        ];
        yield 'preserves multiple tokens' => [
            '_blank', 'https://example.com', 'nofollow sponsored', 'nofollow sponsored noreferrer',
        ];
        yield 'preserves noopener and adds noreferrer' => [
            '_blank', 'https://example.com', 'noopener', 'noopener noreferrer',
        ];
        yield 'idempotent — does not duplicate noreferrer' => [
            '_blank', 'https://example.com', 'noreferrer', 'noreferrer',
        ];
        yield 'preserves source rel even when not external' => [
            '_self', 'https://example.com', 'nofollow', 'nofollow',
        ];
        yield 'preserves source rel for internal url with _blank' => [
            '_blank', '/fileadmin/foo.jpg', 'nofollow', 'nofollow',
        ];
        yield 'lowercases existing tokens' => [
            '_blank', 'https://example.com', 'NoFollow', 'nofollow noreferrer',
        ];
        yield 'deduplicates duplicate source tokens' => [
            '_blank', 'https://example.com', 'nofollow nofollow', 'nofollow noreferrer',
        ];
        yield 'collapses extra whitespace in source' => [
            '_blank', 'https://example.com', '  nofollow   sponsored  ', 'nofollow sponsored noreferrer',
        ];

        // === Edge cases ===
        yield 'null url with empty existing → null' => [
            '_blank', '', null, null,
        ];
        yield 'empty existing string → null' => [
            '_blank', '/internal', '', null,
        ];
    }

    #[Test]
    #[DataProvider('computeProvider')]
    public function computeMatchesExpectedRel(?string $target, string $url, ?string $existing, ?string $expected): void
    {
        self::assertSame(
            $expected,
            SecurityRelComputer::compute($target, $url, $existing),
        );
    }

    /**
     * @return iterable<string, array{0: ?string, 1: list<string>}>
     */
    public static function parseTokensProvider(): iterable
    {
        yield 'null' => [null, []];
        yield 'empty string' => ['', []];
        yield 'whitespace only' => ['   ', []];
        yield 'single token' => ['nofollow', ['nofollow']];
        yield 'multiple tokens' => ['nofollow sponsored', ['nofollow', 'sponsored']];
        yield 'tab separated' => ["nofollow\tsponsored", ['nofollow', 'sponsored']];
        yield 'newline separated' => ["nofollow\nsponsored", ['nofollow', 'sponsored']];
        yield 'lowercase normalization' => ['NOFOLLOW SPONSORED', ['nofollow', 'sponsored']];
        yield 'deduplication' => ['nofollow nofollow', ['nofollow']];
        yield 'extra whitespace' => ['  a   b  c  ', ['a', 'b', 'c']];
    }

    /**
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('parseTokensProvider')]
    public function parseTokensYieldsExpectedList(?string $value, array $expected): void
    {
        self::assertSame($expected, SecurityRelComputer::parseTokens($value));
    }
}
