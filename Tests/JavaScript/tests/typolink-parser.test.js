/**
 * Unit tests for TypoLink parsing functions.
 *
 * Tests the parseTypoLink and parseTypoLinkParts functions that parse
 * TYPO3's TypoLink format into structured objects.
 *
 * TypoLink format: url target class title additionalParams
 * - Values are space-separated
 * - Quoted strings can contain spaces
 * - Backslash is the escape character
 * - Empty values use '-' placeholder
 *
 * @see TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService
 */

import { describe, it, expect } from 'vitest';

/**
 * Parse TypoLink string into parts using CSV-like logic.
 * Handles quoted strings with spaces and escaped characters.
 *
 * This is a copy of the production code for testing purposes.
 * @see Resources/Public/JavaScript/Plugins/typo3image.js
 *
 * @param {string} str - The TypoLink string
 * @return {string[]} Array of parsed parts
 */
function parseTypoLinkParts(str) {
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
 * Parse TypoLink format into structured object.
 *
 * CRITICAL: Order is url, target, class, title, additionalParams
 * This matches TYPO3's TypoLinkCodecService.php
 *
 * @param {string} typoLink - The TypoLink string
 * @return {Object} Parsed link data with href, target, class, title, additionalParams
 */
function parseTypoLink(typoLink) {
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
function encodeTypoLink(linkData) {
    const url = linkData.href || '';
    const target = linkData.target || '-';
    const cssClass = linkData.class || '-';
    const title = linkData.title || '-';
    const additionalParams = linkData.additionalParams || '-';

    // If URL is empty, return empty string
    if (!url) {
        return '';
    }

    // Quote values that contain spaces or special characters
    const quoteIfNeeded = function(value) {
        if (value === '-') {
            return '-';
        }
        // Quote if contains space, quote, or backslash
        if (value.indexOf(' ') !== -1 || value.indexOf('"') !== -1 || value.indexOf('\\') !== -1) {
            // Escape backslashes and quotes
            const escaped = value.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            return '"' + escaped + '"';
        }
        return value;
    };

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

describe('TypoLink Parser', () => {

    describe('parseTypoLinkParts', () => {
        it('should split simple space-separated values', () => {
            const result = parseTypoLinkParts('url target class title params');
            expect(result).toEqual(['url', 'target', 'class', 'title', 'params']);
        });

        it('should handle quoted strings with spaces', () => {
            const result = parseTypoLinkParts('url target class "My Link Title" params');
            expect(result).toEqual(['url', 'target', 'class', 'My Link Title', 'params']);
        });

        it('should handle escaped quotes in strings', () => {
            const result = parseTypoLinkParts('url target class "Title with \\"quotes\\"" params');
            expect(result).toEqual(['url', 'target', 'class', 'Title with "quotes"', 'params']);
        });

        it('should handle escaped backslashes', () => {
            const result = parseTypoLinkParts('url target class "Path: C:\\\\Users" params');
            expect(result).toEqual(['url', 'target', 'class', 'Path: C:\\Users', 'params']);
        });

        it('should handle empty parts as empty strings', () => {
            const result = parseTypoLinkParts('url - class - params');
            expect(result).toEqual(['url', '-', 'class', '-', 'params']);
        });

        it('should handle URL only', () => {
            const result = parseTypoLinkParts('t3://page?uid=1');
            expect(result).toEqual(['t3://page?uid=1']);
        });

        it('should handle multiple consecutive spaces', () => {
            const result = parseTypoLinkParts('url  target   class');
            // Multiple spaces should create empty parts
            expect(result).toEqual(['url', '', 'target', '', '', 'class']);
        });

        it('should handle empty string', () => {
            const result = parseTypoLinkParts('');
            expect(result).toEqual([]);
        });
    });

    describe('parseTypoLink - Field Order', () => {
        /**
         * CRITICAL TEST: This test would have caught the bug where
         * class and title were swapped.
         *
         * TypoLink order is: url target class title additionalParams
         * NOT: url target title class additionalParams
         */
        it('should parse fields in correct order: url target class title additionalParams', () => {
            const input = 't3://page?uid=1 _blank my-css-class "My Link Title" &param=value';
            const result = parseTypoLink(input);

            expect(result.href).toBe('t3://page?uid=1');
            expect(result.target).toBe('_blank');
            expect(result.class).toBe('my-css-class');
            expect(result.title).toBe('My Link Title');
            expect(result.additionalParams).toBe('&param=value');
        });

        it('should not confuse class with title when both are present', () => {
            // This is the bug case: previously class was parsed as title
            const input = 'https://example.com _self button "Click me" &foo=bar';
            const result = parseTypoLink(input);

            // Class should be 'button', NOT 'Click me'
            expect(result.class).toBe('button');
            // Title should be 'Click me', NOT 'button'
            expect(result.title).toBe('Click me');
        });

        it('should correctly identify class when title is empty', () => {
            const input = 'https://example.com _blank highlight - -';
            const result = parseTypoLink(input);

            expect(result.href).toBe('https://example.com');
            expect(result.target).toBe('_blank');
            expect(result.class).toBe('highlight');
            expect(result.title).toBe(''); // Empty because of '-'
            expect(result.additionalParams).toBe('');
        });

        it('should correctly identify title when class is empty', () => {
            const input = 'https://example.com _blank - "Important Link" -';
            const result = parseTypoLink(input);

            expect(result.href).toBe('https://example.com');
            expect(result.target).toBe('_blank');
            expect(result.class).toBe(''); // Empty because of '-'
            expect(result.title).toBe('Important Link');
            expect(result.additionalParams).toBe('');
        });
    });

    describe('parseTypoLink - URL Formats', () => {
        it('should handle TYPO3 page links', () => {
            const result = parseTypoLink('t3://page?uid=42');
            expect(result.href).toBe('t3://page?uid=42');
        });

        it('should handle TYPO3 file links', () => {
            const result = parseTypoLink('t3://file?uid=123');
            expect(result.href).toBe('t3://file?uid=123');
        });

        it('should handle external URLs', () => {
            const result = parseTypoLink('https://www.example.com/path?query=value');
            expect(result.href).toBe('https://www.example.com/path?query=value');
        });

        it('should handle email links', () => {
            const result = parseTypoLink('mailto:info@example.com');
            expect(result.href).toBe('mailto:info@example.com');
        });

        it('should handle telephone links', () => {
            const result = parseTypoLink('tel:+1234567890');
            expect(result.href).toBe('tel:+1234567890');
        });
    });

    describe('parseTypoLink - Target Values', () => {
        it('should handle _blank target', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank');
            expect(result.target).toBe('_blank');
        });

        it('should handle _self target', () => {
            const result = parseTypoLink('t3://page?uid=1 _self');
            expect(result.target).toBe('_self');
        });

        it('should handle _top target', () => {
            const result = parseTypoLink('t3://page?uid=1 _top');
            expect(result.target).toBe('_top');
        });

        it('should handle _parent target', () => {
            const result = parseTypoLink('t3://page?uid=1 _parent');
            expect(result.target).toBe('_parent');
        });

        it('should handle custom frame target', () => {
            const result = parseTypoLink('t3://page?uid=1 myCustomFrame');
            expect(result.target).toBe('myCustomFrame');
        });

        it('should handle nav_frame target (free text)', () => {
            const result = parseTypoLink('t3://page?uid=1 nav_frame');
            expect(result.target).toBe('nav_frame');
        });

        it('should handle content_frame target (free text)', () => {
            const result = parseTypoLink('t3://page?uid=1 content_frame my-class "Title"');
            expect(result.target).toBe('content_frame');
            expect(result.class).toBe('my-class');
            expect(result.title).toBe('Title');
        });

        it('should handle arbitrary frame names', () => {
            const result = parseTypoLink('t3://page?uid=1 my_custom_iframe_name');
            expect(result.target).toBe('my_custom_iframe_name');
        });

        it('should handle empty target with dash', () => {
            const result = parseTypoLink('t3://page?uid=1 -');
            expect(result.target).toBe('');
        });
    });

    describe('parseTypoLink - CSS Classes', () => {
        it('should handle single CSS class', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank my-class');
            expect(result.class).toBe('my-class');
        });

        it('should handle multiple CSS classes (space in quotes)', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank "btn btn-primary"');
            expect(result.class).toBe('btn btn-primary');
        });

        it('should handle empty class with dash', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank -');
            expect(result.class).toBe('');
        });
    });

    describe('parseTypoLink - Title', () => {
        it('should handle simple title without spaces', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank my-class SimpleTitle');
            expect(result.title).toBe('SimpleTitle');
        });

        it('should handle title with spaces (quoted)', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank my-class "Title With Spaces"');
            expect(result.title).toBe('Title With Spaces');
        });

        it('should handle title with special characters', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank my-class "Title: (Test) - Version 1.0!"');
            expect(result.title).toBe('Title: (Test) - Version 1.0!');
        });

        it('should handle empty title with dash', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank my-class -');
            expect(result.title).toBe('');
        });
    });

    describe('parseTypoLink - Additional Parameters', () => {
        it('should handle single additional parameter', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank my-class title &param=value');
            expect(result.additionalParams).toBe('&param=value');
        });

        it('should handle multiple additional parameters', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank my-class title "&foo=1&bar=2"');
            expect(result.additionalParams).toBe('&foo=1&bar=2');
        });

        it('should handle complex additional parameters with special chars', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank - - "&L=1&type=123&tx_news[news]=42"');
            expect(result.additionalParams).toBe('&L=1&type=123&tx_news[news]=42');
        });

        it('should handle additional parameters without quotes', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank - - &simple=value');
            expect(result.additionalParams).toBe('&simple=value');
        });

        it('should handle empty additionalParams with dash', () => {
            const result = parseTypoLink('t3://page?uid=1 _blank my-class title -');
            expect(result.additionalParams).toBe('');
        });
    });

    describe('parseTypoLink - Edge Cases', () => {
        it('should return empty object for null input', () => {
            const result = parseTypoLink(null);
            expect(result).toEqual({
                href: '',
                target: '',
                class: '',
                title: '',
                additionalParams: ''
            });
        });

        it('should return empty object for undefined input', () => {
            const result = parseTypoLink(undefined);
            expect(result).toEqual({
                href: '',
                target: '',
                class: '',
                title: '',
                additionalParams: ''
            });
        });

        it('should return empty object for empty string', () => {
            const result = parseTypoLink('');
            expect(result).toEqual({
                href: '',
                target: '',
                class: '',
                title: '',
                additionalParams: ''
            });
        });

        it('should return empty object for whitespace-only string', () => {
            const result = parseTypoLink('   ');
            expect(result).toEqual({
                href: '',
                target: '',
                class: '',
                title: '',
                additionalParams: ''
            });
        });

        it('should handle URL-only input', () => {
            const result = parseTypoLink('t3://page?uid=1');
            expect(result.href).toBe('t3://page?uid=1');
            expect(result.target).toBe('');
            expect(result.class).toBe('');
            expect(result.title).toBe('');
            expect(result.additionalParams).toBe('');
        });

        it('should handle all dashes (all empty values)', () => {
            const result = parseTypoLink('t3://page?uid=1 - - - -');
            expect(result.href).toBe('t3://page?uid=1');
            expect(result.target).toBe('');
            expect(result.class).toBe('');
            expect(result.title).toBe('');
            expect(result.additionalParams).toBe('');
        });
    });

    describe('parseTypoLink - Real-World Examples', () => {
        it('should handle typical page link with target blank', () => {
            const input = 't3://page?uid=42 _blank - "Read more about us" -';
            const result = parseTypoLink(input);

            expect(result.href).toBe('t3://page?uid=42');
            expect(result.target).toBe('_blank');
            expect(result.class).toBe('');
            expect(result.title).toBe('Read more about us');
            expect(result.additionalParams).toBe('');
        });

        it('should handle external link with all attributes', () => {
            const input = 'https://example.com _blank external-link "External Site" &utm_source=website';
            const result = parseTypoLink(input);

            expect(result.href).toBe('https://example.com');
            expect(result.target).toBe('_blank');
            expect(result.class).toBe('external-link');
            expect(result.title).toBe('External Site');
            expect(result.additionalParams).toBe('&utm_source=website');
        });

        it('should handle file download link', () => {
            const input = 't3://file?uid=789 _blank download-link "Download PDF" -';
            const result = parseTypoLink(input);

            expect(result.href).toBe('t3://file?uid=789');
            expect(result.target).toBe('_blank');
            expect(result.class).toBe('download-link');
            expect(result.title).toBe('Download PDF');
        });

        it('should handle complex title with special characters and spaces', () => {
            const input = 't3://page?uid=1 _blank btn "Click here for more info (optional)!" -';
            const result = parseTypoLink(input);

            expect(result.title).toBe('Click here for more info (optional)!');
            expect(result.class).toBe('btn');
        });
    });

    describe('Regression Tests', () => {
        /**
         * Regression test for the bug where class and title were swapped.
         * User reported:
         * - link title: empty
         * - link css class: holds link title
         * - link target: correct
         *
         * Root cause: parser was reading title at index 2 and class at index 3,
         * but correct order is class at index 2 and title at index 3.
         */
        it('should NOT put title in class field (regression #565-typolink)', () => {
            // Simulate what the link browser returns
            const typoLinkFromBrowser = 't3://page?uid=1 _blank button-primary "Click Me!"';
            const result = parseTypoLink(typoLinkFromBrowser);

            // Class should be 'button-primary', NOT 'Click Me!'
            expect(result.class).not.toBe('Click Me!');
            expect(result.class).toBe('button-primary');

            // Title should be 'Click Me!', NOT 'button-primary'
            expect(result.title).not.toBe('button-primary');
            expect(result.title).toBe('Click Me!');
        });

        it('should NOT leave title empty when provided (regression #565-typolink)', () => {
            const typoLinkFromBrowser = 't3://page?uid=1 _self my-class "This is the title" &param=1';
            const result = parseTypoLink(typoLinkFromBrowser);

            // Title should NOT be empty
            expect(result.title).not.toBe('');
            expect(result.title).toBe('This is the title');
        });

        it('should correctly parse additionalParams (regression #565-typolink)', () => {
            const typoLinkFromBrowser = 't3://page?uid=1 _blank - - "&foo=bar&baz=qux"';
            const result = parseTypoLink(typoLinkFromBrowser);

            // additionalParams should contain the full parameter string
            expect(result.additionalParams).toBe('&foo=bar&baz=qux');
        });
    });
});

describe('TypoLink Encoder', () => {

    describe('encodeTypoLink - Basic Encoding', () => {
        it('should encode URL-only link', () => {
            const result = encodeTypoLink({ href: 't3://page?uid=1' });
            expect(result).toBe('t3://page?uid=1');
        });

        it('should encode link with target', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '_blank'
            });
            expect(result).toBe('t3://page?uid=1 _blank');
        });

        it('should encode link with target and class', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '_blank',
                class: 'my-class'
            });
            expect(result).toBe('t3://page?uid=1 _blank my-class');
        });

        it('should encode link with all attributes', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '_blank',
                class: 'my-class',
                title: 'My Title',
                additionalParams: '&param=value'
            });
            // Note: additionalParams doesn't need quoting (no spaces)
            expect(result).toBe('t3://page?uid=1 _blank my-class "My Title" &param=value');
        });

        it('should return empty string for empty href', () => {
            const result = encodeTypoLink({ href: '' });
            expect(result).toBe('');
        });

        it('should return empty string for undefined href', () => {
            const result = encodeTypoLink({});
            expect(result).toBe('');
        });
    });

    describe('encodeTypoLink - Empty Value Placeholders', () => {
        it('should use dash for empty target when class is present', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '',
                class: 'my-class'
            });
            expect(result).toBe('t3://page?uid=1 - my-class');
        });

        it('should use dash for empty class when title is present', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '_blank',
                class: '',
                title: 'My Title'
            });
            expect(result).toBe('t3://page?uid=1 _blank - "My Title"');
        });

        it('should use dashes for all empty values before last', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '',
                class: '',
                title: '',
                additionalParams: '&param=1'
            });
            // Note: additionalParams doesn't need quoting (no spaces)
            expect(result).toBe('t3://page?uid=1 - - - &param=1');
        });
    });

    describe('encodeTypoLink - Quoting', () => {
        it('should quote title with spaces', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '_blank',
                class: 'btn',
                title: 'Click here for more'
            });
            expect(result).toBe('t3://page?uid=1 _blank btn "Click here for more"');
        });

        it('should quote class with spaces', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '_blank',
                class: 'btn btn-primary'
            });
            expect(result).toBe('t3://page?uid=1 _blank "btn btn-primary"');
        });

        it('should escape quotes in values', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '_blank',
                class: 'btn',
                title: 'Click "here"'
            });
            expect(result).toBe('t3://page?uid=1 _blank btn "Click \\"here\\""');
        });

        it('should escape backslashes in values', () => {
            const result = encodeTypoLink({
                href: 't3://page?uid=1',
                target: '_blank',
                class: 'btn',
                title: 'Path: C:\\Users'
            });
            expect(result).toBe('t3://page?uid=1 _blank btn "Path: C:\\\\Users"');
        });
    });

    describe('encodeTypoLink - Round-Trip', () => {
        it('should encode and decode simple link', () => {
            const original = {
                href: 't3://page?uid=42',
                target: '_blank',
                class: 'my-class',
                title: 'My Title',
                additionalParams: ''
            };
            const encoded = encodeTypoLink(original);
            const decoded = parseTypoLink(encoded);

            expect(decoded.href).toBe(original.href);
            expect(decoded.target).toBe(original.target);
            expect(decoded.class).toBe(original.class);
            expect(decoded.title).toBe(original.title);
        });

        it('should encode and decode link with spaces in title', () => {
            const original = {
                href: 'https://example.com',
                target: '_self',
                class: 'external',
                title: 'Visit our website for more information',
                additionalParams: ''
            };
            const encoded = encodeTypoLink(original);
            const decoded = parseTypoLink(encoded);

            expect(decoded.href).toBe(original.href);
            expect(decoded.target).toBe(original.target);
            expect(decoded.class).toBe(original.class);
            expect(decoded.title).toBe(original.title);
        });

        it('should encode and decode link with all empty except href', () => {
            const original = {
                href: 't3://page?uid=1',
                target: '',
                class: '',
                title: '',
                additionalParams: ''
            };
            const encoded = encodeTypoLink(original);
            const decoded = parseTypoLink(encoded);

            expect(decoded.href).toBe(original.href);
            expect(decoded.target).toBe('');
            expect(decoded.class).toBe('');
            expect(decoded.title).toBe('');
        });

        it('should encode and decode link with free text target (nav_frame)', () => {
            const original = {
                href: 't3://page?uid=1',
                target: 'nav_frame',
                class: 'navigation-link',
                title: 'Open in navigation',
                additionalParams: ''
            };
            const encoded = encodeTypoLink(original);
            const decoded = parseTypoLink(encoded);

            expect(decoded.href).toBe(original.href);
            expect(decoded.target).toBe('nav_frame');
            expect(decoded.class).toBe(original.class);
            expect(decoded.title).toBe(original.title);
        });

        it('should encode and decode link with content_frame target', () => {
            const original = {
                href: 't3://page?uid=42',
                target: 'content_frame',
                class: '',
                title: '',
                additionalParams: ''
            };
            const encoded = encodeTypoLink(original);
            const decoded = parseTypoLink(encoded);

            expect(decoded.href).toBe(original.href);
            expect(decoded.target).toBe('content_frame');
        });

        it('should encode and decode link with additional params', () => {
            const original = {
                href: 't3://page?uid=1',
                target: '_blank',
                class: '',
                title: '',
                additionalParams: '&L=1&type=123'
            };
            const encoded = encodeTypoLink(original);
            const decoded = parseTypoLink(encoded);

            expect(decoded.href).toBe(original.href);
            expect(decoded.target).toBe(original.target);
            expect(decoded.additionalParams).toBe('&L=1&type=123');
        });

        it('should encode and decode link with complex additional params', () => {
            const original = {
                href: 't3://page?uid=1',
                target: 'my_iframe',
                class: 'special',
                title: 'Link Title',
                additionalParams: '&tx_news_pi1[news]=42&cHash=abc123'
            };
            const encoded = encodeTypoLink(original);
            const decoded = parseTypoLink(encoded);

            expect(decoded.href).toBe(original.href);
            expect(decoded.target).toBe('my_iframe');
            expect(decoded.class).toBe('special');
            expect(decoded.title).toBe('Link Title');
            expect(decoded.additionalParams).toBe('&tx_news_pi1[news]=42&cHash=abc123');
        });

        it('should encode and decode link with special characters', () => {
            const original = {
                href: 't3://page?uid=1',
                target: '_blank',
                class: 'btn',
                title: 'Title with "quotes" and \\backslash',
                additionalParams: '&foo=bar'
            };
            const encoded = encodeTypoLink(original);
            const decoded = parseTypoLink(encoded);

            expect(decoded.href).toBe(original.href);
            expect(decoded.target).toBe(original.target);
            expect(decoded.class).toBe(original.class);
            expect(decoded.title).toBe(original.title);
            expect(decoded.additionalParams).toBe(original.additionalParams);
        });
    });

    describe('encodeTypoLink - Real Dialog Scenarios', () => {
        /**
         * Test that simulates clicking Browse button with existing link data.
         * This was the user-reported bug: when reopening link browser,
         * target/title/class fields were empty.
         */
        it('should preserve all fields when reopening link browser', () => {
            // Simulate current dialog state
            const dialogState = {
                href: 't3://page?uid=42',
                target: '_blank',
                class: 'highlight-link',
                title: 'Read more about this topic',
                additionalParams: ''
            };

            // Encode to pass to link browser
            const typoLink = encodeTypoLink(dialogState);

            // Verify all parts are present
            expect(typoLink).toContain('t3://page?uid=42');
            expect(typoLink).toContain('_blank');
            expect(typoLink).toContain('highlight-link');
            expect(typoLink).toContain('Read more about this topic');

            // Verify round-trip preserves data
            const parsed = parseTypoLink(typoLink);
            expect(parsed.href).toBe(dialogState.href);
            expect(parsed.target).toBe(dialogState.target);
            expect(parsed.class).toBe(dialogState.class);
            expect(parsed.title).toBe(dialogState.title);
        });

        it('should handle dialog with only URL set', () => {
            const dialogState = {
                href: 'https://example.com',
                target: '',
                class: '',
                title: '',
                additionalParams: ''
            };

            const typoLink = encodeTypoLink(dialogState);

            // Should only contain URL, no trailing dashes
            expect(typoLink).toBe('https://example.com');
        });

        it('should handle dialog with URL and target', () => {
            const dialogState = {
                href: 't3://page?uid=1',
                target: '_top',
                class: '',
                title: '',
                additionalParams: ''
            };

            const typoLink = encodeTypoLink(dialogState);

            // Should contain URL and target only
            expect(typoLink).toBe('t3://page?uid=1 _top');
        });
    });
});
