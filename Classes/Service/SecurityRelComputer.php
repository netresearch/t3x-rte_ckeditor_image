<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service;

/**
 * Pure helpers for computing the HTML `rel` attribute on editorial
 * `<a>` tags rendered by the Fluid Link partial.
 *
 * The Fluid Link.html partial constructs <a> directly (it does not go
 * through TYPO3 typolink), so the security rel attribute that
 * LinkFactory::addSecurityRelValues() would normally add for external
 * `target="_blank"` links has to be computed here.
 *
 * Both methods are pure functions — no I/O, no DI — to keep them
 * trivially unit-testable.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/799
 *
 * @internal
 */
final class SecurityRelComputer
{
    /**
     * Compute the rel attribute for an editorial link, mirroring TYPO3's
     * LinkFactory::addSecurityRelValues() semantics, while preserving any
     * rel tokens that came from the source `<a>` tag.
     *
     * Adds "noreferrer" to the rel set when target opens a new browsing
     * context AND the URL is external. "External" includes:
     *   - absolute http(s) URLs (`http://...`, `https://...`)
     *   - protocol-relative URLs (`//example.com/...`) — RFC 3986 §4.4.2,
     *     these inherit the page's scheme and resolve to a different host
     *     (single-slash paths like `/foo` are internal — startsWith `//`
     *     and `/` are matched in that order to disambiguate).
     *
     * Existing rel tokens from the source `<a>` (e.g. `nofollow`,
     * `sponsored`, `noopener`) are preserved through `parseTokens()`,
     * which lowercases, deduplicates, and collapses whitespace — so the
     * returned string is normalized rather than verbatim from the source.
     * "noreferrer" is added at most once; if the source already has it,
     * no duplicate is appended.
     *
     * Returns the (normalized) source rel for relative paths, fragments,
     * mailto:/tel:/t3:, and non-browsing-context targets — i.e. cases
     * where typolink wouldn't add security rel either. (t3:// URLs are
     * already resolved to absolute paths before reaching this method.)
     *
     * The HTML browsing-context keywords (`_self`, `_blank`, `_parent`,
     * `_top`) are matched case-insensitively per the HTML living standard
     * and after trimming, so values like `_SELF` or `  _self  ` are
     * correctly classified as same-context.
     *
     * @param string|null $target   Link target attribute
     * @param string      $url      Resolved link URL
     * @param string|null $existing Pre-existing rel value from the source `<a>` tag
     *
     * @return string|null Normalized merged rel value (or null when
     *                     neither security rel applies nor a source rel
     *                     was provided)
     */
    public static function compute(?string $target, string $url, ?string $existing = null): ?string
    {
        $existingTokens = self::parseTokens($existing);

        // Normalize target for the same-context comparison: HTML
        // browsing-context keywords are case-insensitive. Trim and
        // lowercase so values like `_SELF` or `  _Top  ` are still
        // recognized and short-circuit the security rel addition.
        $normalizedTarget = $target === null ? null : strtolower(trim($target));

        // Determine whether security rel should be added.
        $needsNoreferrer = false;
        if ($normalizedTarget !== null && !in_array($normalizedTarget, ['', '_self', '_parent', '_top'], true)) {
            // Trim and lowercase for stable scheme detection. Defensive even
            // if the URL has been validated upstream — we don't want surface
            // whitespace from RTE markup to silently change the predicate.
            $normalizedUrl = strtolower(trim($url));
            $needsNoreferrer
                = str_starts_with($normalizedUrl, 'http://')
                || str_starts_with($normalizedUrl, 'https://')
                // Protocol-relative URLs inherit the page's scheme and resolve
                // to a different host. RFC 3986 §4.4.2 calls these
                // "network-path references". `//foo` ⇒ external; `/foo` ⇒ internal.
                || str_starts_with($normalizedUrl, '//');
        }

        if ($needsNoreferrer && !in_array('noreferrer', $existingTokens, true)) {
            $existingTokens[] = 'noreferrer';
        }

        return $existingTokens === [] ? null : implode(' ', $existingTokens);
    }

    /**
     * Parse an HTML `rel` attribute value into a list of unique tokens.
     *
     * The rel attribute is space-separated per HTML living standard. Tokens
     * are case-insensitive but conventionally lowercase; we lowercase for
     * comparison stability and de-duplicate while preserving first-seen order.
     *
     * @param string|null $value Raw rel attribute value
     *
     * @return list<string> Unique lowercased tokens, empty when no value
     */
    public static function parseTokens(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $rawTokens = preg_split('/\s+/', strtolower($trimmed));
        if ($rawTokens === false) {
            return [];
        }

        $tokens = [];
        foreach ($rawTokens as $token) {
            if ($token !== '' && !in_array($token, $tokens, true)) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }
}
