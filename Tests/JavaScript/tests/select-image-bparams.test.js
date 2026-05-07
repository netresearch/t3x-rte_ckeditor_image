/**
 * Unit tests for `buildSelectImageBparams`, the helper that builds the
 * `bparams` query value passed to the TYPO3 FileBrowser when `selectImage()`
 * opens its iframe modal.
 *
 * Issue #821: The CKEditor 5 plugin was hardcoding all four `bparams` slots
 * to empty strings, dropping the user-configured `allowedExtensions` value
 * (YAML: `editor.externalPlugins.typo3image.allowedExtensions`). The server
 * then fell back to `$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']`
 * for the FileBrowser's `allowedFileExtensions`, silently ignoring the
 * documented configuration option.
 *
 * Pre-CKE5 (commit 1cfe7a7), the legacy plugin set
 * `bparams[3] = editor.config.typo3image.allowedExtensions || ''`. The CKE5
 * rewrite dropped that line — these tests lock the contract back in.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/821
 * @see Resources/Public/JavaScript/Plugins/select-image-bparams.js
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
    buildSelectImageBparams,
} from '../../../Resources/Public/JavaScript/Plugins/select-image-bparams.js';

describe('buildSelectImageBparams (issue #821)', () => {

    describe('contract: 4-element array shape', () => {
        it('returns exactly 4 elements', () => {
            const result = buildSelectImageBparams('jpg,png');
            expect(result).toHaveLength(4);
        });

        it('keeps the first three slots as empty strings', () => {
            const result = buildSelectImageBparams('jpg,png');
            expect(result[0]).toBe('');
            expect(result[1]).toBe('');
            expect(result[2]).toBe('');
        });
    });

    describe('contract: bparams[3] carries allowedExtensions', () => {
        it('places the allowedExtensions string into bparams[3]', () => {
            const result = buildSelectImageBparams('jpg,jpeg,png,webp');
            expect(result[3]).toBe('jpg,jpeg,png,webp');
        });

        it('preserves a single-extension list verbatim', () => {
            const result = buildSelectImageBparams('svg');
            expect(result[3]).toBe('svg');
        });

        it('preserves whitespace and casing inside the list (server validates, JS does not normalize)', () => {
            const result = buildSelectImageBparams('JPG, PNG');
            expect(result[3]).toBe('JPG, PNG');
        });
    });

    describe('contract: empty bparams[3] for null/undefined/empty-string (preserves server fallback)', () => {
        let warnSpy;

        beforeEach(() => {
            warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
        });

        afterEach(() => {
            warnSpy.mockRestore();
        });

        it('emits empty string for undefined input (no warning)', () => {
            const result = buildSelectImageBparams(undefined);
            expect(result[3]).toBe('');
            expect(warnSpy).not.toHaveBeenCalled();
        });

        it('emits empty string for null input (no warning)', () => {
            const result = buildSelectImageBparams(null);
            expect(result[3]).toBe('');
            expect(warnSpy).not.toHaveBeenCalled();
        });

        it('emits empty string for empty-string input (no warning)', () => {
            const result = buildSelectImageBparams('');
            expect(result[3]).toBe('');
            expect(warnSpy).not.toHaveBeenCalled();
        });
    });

    describe('diagnostic: warns and falls back when admin config is non-string', () => {
        let warnSpy;

        beforeEach(() => {
            warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
        });

        afterEach(() => {
            warnSpy.mockRestore();
        });

        it('warns and emits empty bparams[3] for an array (typical YAML mishap)', () => {
            const result = buildSelectImageBparams(['jpg', 'png']);
            expect(result[3]).toBe('');
            expect(warnSpy).toHaveBeenCalledOnce();
            expect(warnSpy.mock.calls[0][0]).toMatch(/typo3image\.allowedExtensions ignored/);
            expect(warnSpy.mock.calls[0][0]).toMatch(/got object/);
        });

        it('warns for boolean false (defensive against unexpected types)', () => {
            const result = buildSelectImageBparams(false);
            expect(result[3]).toBe('');
            expect(warnSpy).toHaveBeenCalledOnce();
            expect(warnSpy.mock.calls[0][0]).toMatch(/got boolean/);
        });

        it('warns for numeric 0 (defensive against unexpected types)', () => {
            const result = buildSelectImageBparams(0);
            expect(result[3]).toBe('');
            expect(warnSpy).toHaveBeenCalledOnce();
            expect(warnSpy.mock.calls[0][0]).toMatch(/got number/);
        });
    });

    describe('regression: issue #821 — allowedExtensions reaches FileBrowser', () => {
        /**
         * BEFORE the fix, `selectImage()` used a hardcoded
         *   const bparams = ['', '', '', ''];
         * which discarded the user's `allowedExtensions` config. The server's
         * `SelectImageController` then replaced the empty 4th slot with
         * `$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']`, silently
         * overriding the documented YAML option.
         *
         * AFTER the fix, the helper threads `allowedExtensions` through to
         * bparams[3], so an admin who configures `allowedExtensions: "jpg,png"`
         * actually sees only those extensions in the file browser.
         */
        it('forwards the configured allowedExtensions into bparams[3] (NOT empty)', () => {
            const configured = 'jpg,jpeg,png';
            const result = buildSelectImageBparams(configured);

            // The bug: bparams[3] was always '' regardless of input.
            expect(result[3]).not.toBe('');
            expect(result[3]).toBe(configured);
        });

        it('does NOT mangle the list with separators or sorting', () => {
            // Order matters: the FileBrowser receives the list verbatim and
            // matches case-insensitively by simple substring on the comma list.
            const configured = 'png,jpg,gif';
            const result = buildSelectImageBparams(configured);
            expect(result[3]).toBe('png,jpg,gif');
        });
    });
});

describe('round-trip with bparams.join("|") (TYPO3 ElementBrowser convention)', () => {
    /**
     * The ElementBrowser bparams contract uses a raw `|`-joined query
     * value (cf. AbstractElementBrowser::getBParamDataAttributes); PSR-7
     * decodes once before `explode('|', ...)`, so URL-encoding the slots
     * would not survive the server-side parse anyway. Lock the join
     * behaviour here so a future refactor cannot silently break it.
     */
    it('joins to "|||jpg,png" when only allowedExtensions is set', () => {
        const result = buildSelectImageBparams('jpg,png').join('|');
        expect(result).toBe('|||jpg,png');
    });

    it('joins to "|||" when allowedExtensions is empty', () => {
        const result = buildSelectImageBparams('').join('|');
        expect(result).toBe('|||');
    });
});
