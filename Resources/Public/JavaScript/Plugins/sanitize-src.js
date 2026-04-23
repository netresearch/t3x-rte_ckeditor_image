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
 * Normalise an image src attribute value.
 *
 * Rejects the stringified corruption forms "undefined" and "null" which can
 * round-trip through model/view conversion when an upstream path loses the
 * value. Returning null lets callers omit the attribute so the frontend can
 * reconstruct it from data-htmlarea-file-uid instead of persisting garbage.
 *
 * @param {*} value Raw attribute value from view or model.
 * @return {string|null} Trimmed string, or null if the value is missing/corrupted.
 */
export function sanitizeSrc(value) {
    if (value === null || value === undefined) {
        return null;
    }

    const trimmed = String(value).trim();
    if (trimmed === '' || trimmed === 'undefined' || trimmed === 'null') {
        return null;
    }

    return trimmed;
}
