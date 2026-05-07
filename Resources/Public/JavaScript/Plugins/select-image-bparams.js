/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Build the 4-element `bparams` array passed to the FileBrowser via the
 * `&bparams=<a>|<b>|<c>|<d>` query parameter. The fourth slot carries the
 * comma-separated list of allowed file extensions; an empty value triggers
 * the server-side fallback to
 * `$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']` in
 * `SelectImageController`.
 *
 * Wiring `allowedExtensions` (configured via the YAML key
 * `editor.externalPlugins.typo3image.allowedExtensions`) into bparams[3]
 * makes the documented configuration effective again — the CKEditor 5
 * rewrite had dropped this line, silently overriding admins' choices with
 * the global GFX default.
 *
 * The `|`-joined output is consumed verbatim by TYPO3's ElementBrowser
 * (cf. `AbstractElementBrowser::getBParamDataAttributes`); the raw
 * separator is the established backend convention, so callers should join
 * with `bparams.join('|')` and not URL-encode (PSR-7 would decode `%7C`
 * back to `|` before `explode('|', ...)`, defeating any encoding).
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/821
 *
 * @param {string|undefined|null} allowedExtensions - Comma-separated list,
 *   e.g. `"jpg,jpeg,png,webp"`. The contract is a string; `null` and
 *   `undefined` are tolerated for callers that read the YAML preset
 *   directly. Any other falsy value (`''`, `0`, `false`, `NaN`) is
 *   defensively coerced to the empty string so the server-side default
 *   always applies, never `undefined`/`'null'` substrings in the URL.
 * @return {string[]} 4-element array suitable for `bparams.join('|')`.
 */
export function buildSelectImageBparams(allowedExtensions) {
    let slot = '';
    if (typeof allowedExtensions === 'string') {
        slot = allowedExtensions;
    } else if (allowedExtensions !== undefined && allowedExtensions !== null) {
        // Surface admin misconfiguration without aborting the editor: e.g. a
        // YAML preset that yields an array (`allowedExtensions: [jpg, png]`)
        // would otherwise silently fall back to GFX.imagefile_ext.
        // eslint-disable-next-line no-console
        console.warn(
            'rte_ckeditor_image: typo3image.allowedExtensions ignored — expected string, got '
            + (typeof allowedExtensions)
        );
    }
    return ['', '', '', slot];
}
