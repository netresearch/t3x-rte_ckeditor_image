/**
 * TYPO3 TypoLink format parser/encoder.
 *
 * Pure string utilities with no external dependencies, extracted from
 * typo3image.js so they can be imported directly by unit tests without
 * pulling in the CKEditor module graph.
 *
 * TypoLink format (order is crucial!): url target class title additionalParams
 * - Empty values use "-" as placeholder
 * - Values with spaces are enclosed in double quotes
 * - Backslash is used as escape character
 *
 * @see TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService
 */

/**
 * Parse a TYPO3 TypoLink string into its components.
 *
 * Examples:
 *   - "t3://page?uid=1"
 *   - "t3://page?uid=1 _blank"
 *   - "t3://page?uid=1 _blank my-class"
 *   - "t3://page?uid=1 _blank my-class "Click here""
 *   - "t3://page?uid=1 - - "Click here""
 *   - "t3://page?uid=1 _blank my-class "Click here" &foo=bar"
 *
 * @param {string} typoLink - The TypoLink string to parse
 * @return {Object} Object with href, target, class, title, additionalParams properties
 */
export function parseTypoLink(typoLink) {
    const result = {
        href: '',
        target: '',
        class: '',
        title: '',
        additionalParams: ''
    };

    if (!typoLink || typeof typoLink !== 'string') {
        return result;
    }

    typoLink = typoLink.trim();
    if (typoLink === '') {
        return result;
    }

    // Parse using CSV-like logic (space delimiter, quote enclosure, backslash escape)
    // This mimics PHP's str_getcsv($typoLink, ' ', '"', '\\')
    const parts = parseTypoLinkParts(typoLink);

    // Order: url, target, class, title, additionalParams
    if (parts.length > 0 && parts[0] !== '-') {
        result.href = parts[0];
    }
    if (parts.length > 1 && parts[1] !== '-') {
        result.target = parts[1];
    }
    if (parts.length > 2 && parts[2] !== '-') {
        result.class = parts[2];
    }
    if (parts.length > 3 && parts[3] !== '-') {
        result.title = parts[3];
    }
    if (parts.length > 4 && parts[4] !== '-') {
        result.additionalParams = parts[4];
    }

    return result;
}

/**
 * Parse TypoLink string into parts using CSV-like logic.
 * Handles quoted strings with spaces and escaped characters.
 *
 * @param {string} str - The TypoLink string
 * @return {string[]} Array of parsed parts
 */
export function parseTypoLinkParts(str) {
    const parts = [];
    let current = '';
    let inQuotes = false;
    let i = 0;

    while (i < str.length) {
        const char = str[i];

        if (char === '\\' && i + 1 < str.length) {
            // Escape sequence - include next character literally
            current += str[i + 1];
            i += 2;
            continue;
        }

        if (char === '"') {
            inQuotes = !inQuotes;
            i++;
            continue;
        }

        if (char === ' ' && !inQuotes) {
            // Delimiter - save current part and start new one
            if (current !== '' || parts.length > 0) {
                parts.push(current);
                current = '';
            }
            i++;
            continue;
        }

        current += char;
        i++;
    }

    // Don't forget the last part
    if (current !== '' || parts.length > 0) {
        parts.push(current);
    }

    return parts;
}

/**
 * Quote a TypoLink value if it contains characters that need escaping.
 * Hoisted to module scope (per Sonar S7721) so it isn't recreated on
 * every call to encodeTypoLink and is independently testable if needed.
 *
 * @param {string} value - The raw value to quote
 * @return {string} The (possibly quoted/escaped) value
 */
function quoteIfNeeded(value) {
    if (value === '-') {
        return '-';
    }
    // Quote if contains space, quote, or backslash
    if (value.includes(' ') || value.includes('"') || value.includes('\\')) {
        // Escape backslashes and quotes
        const escaped = value.replaceAll('\\', '\\\\').replaceAll('"', '\\"');
        return `"${escaped}"`;
    }
    return value;
}

/**
 * Encode link data into TypoLink format.
 * This is the reverse of parseTypoLink.
 *
 * Format: url target class title additionalParams
 * - Empty values use '-' placeholder
 * - Values with spaces are quoted
 *
 * @param {Object} linkData - Object with href, target, class, title, additionalParams
 * @return {string} TypoLink string
 */
export function encodeTypoLink(linkData) {
    const url = linkData.href || '';
    const target = linkData.target || '-';
    const cssClass = linkData.class || '-';
    const title = linkData.title || '-';
    const additionalParams = linkData.additionalParams || '-';

    // If URL is empty, return empty string
    if (!url) {
        return '';
    }

    // Build TypoLink parts array
    const parts = [url];

    // Only include parts up to the last non-empty value
    // This keeps the output minimal while still correct
    const hasAdditionalParams = additionalParams !== '-';
    const hasTitle = title !== '-' || hasAdditionalParams;
    const hasClass = cssClass !== '-' || hasTitle;
    const hasTarget = target !== '-' || hasClass;

    if (hasTarget) {
        parts.push(quoteIfNeeded(target));
    }
    if (hasClass) {
        parts.push(quoteIfNeeded(cssClass));
    }
    if (hasTitle) {
        parts.push(quoteIfNeeded(title));
    }
    if (hasAdditionalParams) {
        parts.push(quoteIfNeeded(additionalParams));
    }

    return parts.join(' ');
}
