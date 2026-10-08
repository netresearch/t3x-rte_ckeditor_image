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
 * Resolve a label from TYPO3's inline language labels.
 *
 * `TYPO3.lang` is only populated in frames that received the inline label
 * payload. The CKEditor plugin runs inside the content frame, where it can
 * be undefined, so indexing it directly throws a TypeError. Lookup order
 * mirrors `lll()` from `@typo3/core/lit-helper.js` — current frame first,
 * then the top frame — and is identical on TYPO3 v13 and v14.
 *
 * Each scope is read inside its own guard: on a cross-origin top frame,
 * `top` itself is readable (it is a cross-origin-accessible attribute)
 * while every other property access throws a SecurityError. Scopes are
 * resolved lazily so the top frame is only touched when the current frame
 * has no label.
 *
 * @param {string} key Inline label key, e.g. "RTE.titleLinkBrowser".
 * @param {string} [fallback] Returned when the label is unavailable.
 * @return {string} The resolved label, or the fallback.
 */
export function getBackendLabel(key, fallback = '') {
    for (const resolveScope of [() => globalThis, () => globalThis.top]) {
        try {
            const label = resolveScope()?.TYPO3?.lang?.[key];

            if (typeof label === 'string' && label !== '') {
                return label;
            }
        } catch {
            // cross-origin scope, keep looking
        }
    }

    return fallback;
}
