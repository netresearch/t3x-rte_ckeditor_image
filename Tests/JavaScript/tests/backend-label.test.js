/**
 * Regression tests: resolving a backend label must never throw when
 * `TYPO3.lang` is missing.
 *
 * Background: the image dialog's "Browse..." button built the link browser
 * modal with `TYPO3.lang['RTE.titleLinkBrowser']`. The CKEditor plugin runs
 * in the content frame, where `TYPO3.lang` can be undefined, so the lookup
 * threw a TypeError before the modal was ever created and the link browser
 * silently never opened.
 */

import { describe, it, expect, afterEach } from 'vitest';
import { getBackendLabel } from '../../../Resources/Public/JavaScript/Plugins/backend-label.js';

const KEY = 'RTE.titleLinkBrowser';

/**
 * Point `globalThis.top` at a stand-in frame for the duration of a test.
 * jsdom makes `top` self-referential, so it has to be replaced explicitly.
 */
function stubTopWindow(value) {
  Object.defineProperty(globalThis, 'top', {
    value,
    configurable: true,
    writable: true,
  });
}

afterEach(() => {
  delete globalThis.TYPO3;
  stubTopWindow(globalThis);
});

describe('getBackendLabel', () => {
  it('returns the label from the current frame', () => {
    globalThis.TYPO3 = { lang: { [KEY]: 'Link browser' } };

    expect(getBackendLabel(KEY, 'Link')).toBe('Link browser');
  });

  it('falls back to the top frame when the current frame has no labels', () => {
    stubTopWindow({ TYPO3: { lang: { [KEY]: 'Link browser' } } });

    expect(getBackendLabel(KEY, 'Link')).toBe('Link browser');
  });

  it('returns the fallback when TYPO3.lang is undefined', () => {
    globalThis.TYPO3 = {};
    stubTopWindow({ TYPO3: {} });

    expect(getBackendLabel(KEY, 'Link')).toBe('Link');
  });

  it('returns the fallback when TYPO3 itself is undefined', () => {
    stubTopWindow(null);

    expect(getBackendLabel(KEY, 'Link')).toBe('Link');
  });

  it('returns the fallback for an unknown key', () => {
    globalThis.TYPO3 = { lang: { 'RTE.somethingElse': 'Other' } };

    expect(getBackendLabel(KEY, 'Link')).toBe('Link');
  });

  it('skips empty labels so the fallback stays meaningful', () => {
    globalThis.TYPO3 = { lang: { [KEY]: '' } };
    stubTopWindow({ TYPO3: { lang: { [KEY]: '' } } });

    expect(getBackendLabel(KEY, 'Link')).toBe('Link');
  });

  it('ignores non-string label values', () => {
    globalThis.TYPO3 = { lang: { [KEY]: 42 } };
    stubTopWindow({ TYPO3: { lang: {} } });

    expect(getBackendLabel(KEY, 'Link')).toBe('Link');
  });

  it('returns an empty string when no fallback is given', () => {
    stubTopWindow(null);

    expect(getBackendLabel(KEY)).toBe('');
  });

  it('survives a cross-origin top frame denying property access', () => {
    // `top` itself is cross-origin readable; anything on it is not
    stubTopWindow(Object.defineProperty({}, 'TYPO3', {
      get() {
        throw new DOMException('cross-origin', 'SecurityError');
      },
    }));

    expect(() => getBackendLabel(KEY, 'Link')).not.toThrow();
    expect(getBackendLabel(KEY, 'Link')).toBe('Link');
  });

  it('still prefers the current frame when the top frame is cross-origin', () => {
    globalThis.TYPO3 = { lang: { [KEY]: 'Link browser' } };
    stubTopWindow(Object.defineProperty({}, 'TYPO3', {
      get() {
        throw new DOMException('cross-origin', 'SecurityError');
      },
    }));

    expect(getBackendLabel(KEY, 'Link')).toBe('Link browser');
  });

  it('survives a top accessor that throws', () => {
    Object.defineProperty(globalThis, 'top', {
      get() {
        throw new DOMException('cross-origin', 'SecurityError');
      },
      configurable: true,
    });

    expect(() => getBackendLabel(KEY, 'Link')).not.toThrow();
    expect(getBackendLabel(KEY, 'Link')).toBe('Link');
  });
});
