/**
 * Regression tests: image src must never round-trip as the literal
 * strings "undefined"/"null" through upcast/downcast.
 *
 * Background: TYPO3 13 LTS backend RTE reports of <img src="undefined">
 * appearing after save. Guard applied in typo3image.js, mirrored here.
 */

import { describe, it, expect } from 'vitest';
import {
  MockViewElement,
  MockModelElement,
  MockWriter,
} from '../mocks/ckeditor-mocks.js';

/**
 * Mirror of sanitizeSrc from Resources/Public/JavaScript/Plugins/typo3image.js.
 * Keep in sync with the production helper.
 */
function sanitizeSrc(value) {
  if (value === null || value === undefined) {
    return null;
  }

  const trimmed = String(value).trim();
  if (trimmed === '' || trimmed === 'undefined' || trimmed === 'null') {
    return null;
  }

  return trimmed;
}

/**
 * Mirror of the downcast helper in typo3image.js: rebuilds the <img> view
 * attributes from a model element, dropping src when corrupted.
 */
function buildImageViewAttributes(modelElement) {
  const attributes = {
    'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
    'data-htmlarea-file-table': modelElement.getAttribute('fileTable'),
    'width': modelElement.getAttribute('width'),
    'height': modelElement.getAttribute('height'),
    'title': modelElement.getAttribute('title') || '',
    'alt': modelElement.getAttribute('alt') || '',
  };

  const cleanSrc = sanitizeSrc(modelElement.getAttribute('src'));
  if (cleanSrc !== null) {
    attributes['src'] = cleanSrc;
  }

  return attributes;
}

/**
 * Mirror of the upcast path: rebuilds model attributes from a view img,
 * dropping src when corrupted so the FE can rebuild it from fileUid.
 */
function buildImageModelAttributes(viewImg) {
  const imageAttributes = {
    fileUid: viewImg.getAttribute('data-htmlarea-file-uid'),
    fileTable: viewImg.getAttribute('data-htmlarea-file-table') || 'sys_file',
    width: viewImg.getAttribute('width') || '',
    height: viewImg.getAttribute('height') || '',
    alt: viewImg.getAttribute('alt') || '',
    title: viewImg.getAttribute('title') || '',
  };

  const cleanSrc = sanitizeSrc(viewImg.getAttribute('src'));
  if (cleanSrc !== null) {
    imageAttributes.src = cleanSrc;
  }

  return imageAttributes;
}

describe('sanitizeSrc', () => {
  it('returns null for null', () => {
    expect(sanitizeSrc(null)).toBeNull();
  });

  it('returns null for undefined', () => {
    expect(sanitizeSrc(undefined)).toBeNull();
  });

  it('returns null for empty string', () => {
    expect(sanitizeSrc('')).toBeNull();
  });

  it('returns null for whitespace-only string', () => {
    expect(sanitizeSrc('   ')).toBeNull();
  });

  it('returns null for the literal string "undefined"', () => {
    expect(sanitizeSrc('undefined')).toBeNull();
  });

  it('returns null for the literal string "null"', () => {
    expect(sanitizeSrc('null')).toBeNull();
  });

  it('trims valid URLs', () => {
    expect(sanitizeSrc('  /fileadmin/image.jpg  ')).toBe('/fileadmin/image.jpg');
  });

  it('passes absolute local paths through', () => {
    expect(sanitizeSrc('/fileadmin/image.jpg')).toBe('/fileadmin/image.jpg');
  });

  it('passes remote URLs through', () => {
    expect(sanitizeSrc('https://cdn.example.com/image.jpg')).toBe('https://cdn.example.com/image.jpg');
  });
});

describe('downcast guard: model → view', () => {
  it('drops src attribute when model holds undefined', () => {
    const model = new MockModelElement('typo3image', {
      fileUid: '37139',
      fileTable: 'sys_file',
      width: '25',
      height: '18',
      src: undefined,
    });

    const attributes = buildImageViewAttributes(model);

    expect(attributes).not.toHaveProperty('src');
    expect(attributes['data-htmlarea-file-uid']).toBe('37139');
  });

  it('drops src attribute when model holds the literal string "undefined"', () => {
    const model = new MockModelElement('typo3image', {
      fileUid: '37139',
      src: 'undefined',
    });

    const attributes = buildImageViewAttributes(model);

    expect(attributes).not.toHaveProperty('src');
  });

  it('drops src attribute when model holds an empty string', () => {
    const model = new MockModelElement('typo3image', {
      fileUid: '37139',
      src: '',
    });

    const attributes = buildImageViewAttributes(model);

    expect(attributes).not.toHaveProperty('src');
  });

  it('preserves valid src unchanged', () => {
    const model = new MockModelElement('typo3image', {
      fileUid: '37139',
      src: '/fileadmin/user_upload/image.jpg',
    });

    const attributes = buildImageViewAttributes(model);

    expect(attributes.src).toBe('/fileadmin/user_upload/image.jpg');
  });

  it('writer never emits src="undefined" on a view element', () => {
    const writer = new MockWriter();
    const model = new MockModelElement('typo3image', {
      fileUid: '37139',
      src: 'undefined',
    });

    const view = writer.createEmptyElement('img', buildImageViewAttributes(model));

    expect(view.getAttribute('src')).toBeNull();
    expect(view.hasAttribute('src')).toBe(false);
  });
});

describe('upcast guard: view → model', () => {
  it('drops src when view has src="undefined" (corrupted prior save)', () => {
    const view = new MockViewElement('img', {
      'data-htmlarea-file-uid': '37139',
      'data-htmlarea-file-table': 'sys_file',
      src: 'undefined',
      width: '25',
      height: '18',
    });

    const modelAttributes = buildImageModelAttributes(view);

    expect(modelAttributes).not.toHaveProperty('src');
    expect(modelAttributes.fileUid).toBe('37139');
  });

  it('drops src when view has src="null"', () => {
    const view = new MockViewElement('img', {
      'data-htmlarea-file-uid': '37139',
      src: 'null',
    });

    const modelAttributes = buildImageModelAttributes(view);

    expect(modelAttributes).not.toHaveProperty('src');
  });

  it('preserves valid src in the view-to-model mapping', () => {
    const view = new MockViewElement('img', {
      'data-htmlarea-file-uid': '37139',
      src: '/fileadmin/user_upload/image.jpg',
    });

    const modelAttributes = buildImageModelAttributes(view);

    expect(modelAttributes.src).toBe('/fileadmin/user_upload/image.jpg');
  });
});

describe('full round-trip: corrupted input recovers cleanly', () => {
  it('src="undefined" in stored HTML does not round-trip to the next save', () => {
    // Simulate DB-stored markup that was corrupted by an earlier bug.
    const corruptedView = new MockViewElement('img', {
      'data-htmlarea-file-uid': '37139',
      'data-htmlarea-file-table': 'sys_file',
      src: 'undefined',
      width: '25',
      height: '18',
      alt: 'foo',
      title: 'foo',
    });

    // Upcast: load into model, with guard stripping the bad src.
    const modelAttributes = buildImageModelAttributes(corruptedView);
    const model = new MockModelElement('typo3image', modelAttributes);

    // Downcast: save the model back out. Must not re-emit src="undefined".
    const viewAttributes = buildImageViewAttributes(model);

    expect(viewAttributes).not.toHaveProperty('src');
    // Critical data that lets the FE renderer rebuild the URL is preserved.
    expect(viewAttributes['data-htmlarea-file-uid']).toBe('37139');
    expect(viewAttributes['data-htmlarea-file-table']).toBe('sys_file');
    expect(viewAttributes.width).toBe('25');
    expect(viewAttributes.height).toBe('18');
    expect(viewAttributes.alt).toBe('foo');
  });
});
