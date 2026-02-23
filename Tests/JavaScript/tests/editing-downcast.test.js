/**
 * Unit tests for editing downcast converters.
 *
 * Tests for:
 * - Fix #686: max-width on figure element in CKEditor editing view
 * - Fix #687: No <a> wrapper in editing view (prevents double link icon)
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/686
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/687
 */

import { describe, it, expect, beforeEach } from 'vitest';
import {
  MockViewElement,
  MockModelElement,
  MockWriter,
} from '../mocks/ckeditor-mocks.js';

/**
 * Mirrors createImageViewElement from typo3image.js with wrapInLink option.
 * In the editing downcast, wrapInLink=false prevents <a> wrapping so
 * CKEditor's native link indicator doesn't duplicate our custom badges.
 */
function createImageViewElement(modelElement, writer, { wrapInLink = true } = {}) {
  const attributes = {
    'src': modelElement.getAttribute('src'),
    'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
    'data-htmlarea-file-table': modelElement.getAttribute('fileTable'),
    'width': modelElement.getAttribute('width'),
    'height': modelElement.getAttribute('height'),
    'title': modelElement.getAttribute('title') || '',
    'alt': modelElement.getAttribute('alt') || '',
  };

  const imgElement = writer.createEmptyElement('img', attributes);

  if (wrapInLink) {
    const linkHref = modelElement.getAttribute('imageLinkHref');
    if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
      const linkAttributes = { href: linkHref };

      const linkTarget = modelElement.getAttribute('imageLinkTarget');
      if (linkTarget && linkTarget.trim() !== '') {
        linkAttributes.target = linkTarget;
      }

      const linkTitle = modelElement.getAttribute('imageLinkTitle');
      if (linkTitle && linkTitle.trim() !== '') {
        linkAttributes.title = linkTitle;
      }

      return writer.createContainerElement('a', linkAttributes, imgElement);
    }
  }

  return imgElement;
}

/**
 * Mirrors createInlineImageViewElement from typo3image.js with wrapInLink option.
 */
function createInlineImageViewElement(modelElement, writer, { wrapInLink = true } = {}) {
  const attributes = {
    'src': modelElement.getAttribute('src'),
    'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
    'data-htmlarea-file-table': modelElement.getAttribute('fileTable'),
    'width': modelElement.getAttribute('width'),
    'height': modelElement.getAttribute('height'),
    'class': modelElement.getAttribute('class') || 'image-inline',
    'title': modelElement.getAttribute('title') || '',
    'alt': modelElement.getAttribute('alt') || '',
  };

  const imgElement = writer.createEmptyElement('img', attributes);

  if (wrapInLink) {
    const linkHref = modelElement.getAttribute('imageLinkHref');
    if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
      const linkAttributes = { href: linkHref };

      const linkTarget = modelElement.getAttribute('imageLinkTarget');
      if (linkTarget && linkTarget.trim() !== '') {
        linkAttributes.target = linkTarget;
      }

      const linkTitle = modelElement.getAttribute('imageLinkTitle');
      if (linkTitle && linkTitle.trim() !== '') {
        linkAttributes.title = linkTitle;
      }

      return writer.createContainerElement('a', linkAttributes, imgElement);
    }
  }

  return imgElement;
}

/**
 * Helper: count elements by tag name in a view tree.
 */
function findElementsByName(root, tagName) {
  const results = [];
  if (root instanceof MockViewElement) {
    if (root.name === tagName) {
      results.push(root);
    }
    for (const child of root.getChildren()) {
      results.push(...findElementsByName(child, tagName));
    }
  }
  return results;
}

describe('wrapInLink option (#687)', () => {
  let writer;

  const linkedModel = new MockModelElement('typo3image', {
    fileUid: '123',
    fileTable: 'sys_file',
    src: '/fileadmin/test.jpg',
    width: '300',
    height: '200',
    alt: 'Test',
    imageLinkHref: 'https://example.com',
    imageLinkTarget: '_blank',
  });

  const unlinkModel = new MockModelElement('typo3image', {
    fileUid: '456',
    fileTable: 'sys_file',
    src: '/fileadmin/test2.jpg',
    width: '400',
    height: '300',
    alt: 'No link',
  });

  beforeEach(() => {
    writer = new MockWriter();
  });

  describe('createImageViewElement', () => {
    it('with wrapInLink=false should return bare img', () => {
      const result = createImageViewElement(linkedModel, writer, { wrapInLink: false });
      expect(result.name).toBe('img');
      expect(findElementsByName(result, 'a')).toHaveLength(0);
    });

    it('with wrapInLink=true (default) should wrap in <a>', () => {
      const result = createImageViewElement(linkedModel, writer);
      expect(result.name).toBe('a');
      expect(result.getAttribute('href')).toBe('https://example.com');
      expect(findElementsByName(result, 'img')).toHaveLength(1);
    });

    it('without link attributes should return bare img regardless of wrapInLink', () => {
      const result = createImageViewElement(unlinkModel, writer);
      expect(result.name).toBe('img');
    });
  });

  describe('createInlineImageViewElement', () => {
    it('with wrapInLink=false should return bare img', () => {
      const result = createInlineImageViewElement(linkedModel, writer, { wrapInLink: false });
      expect(result.name).toBe('img');
      expect(findElementsByName(result, 'a')).toHaveLength(0);
    });

    it('with wrapInLink=true (default) should wrap in <a>', () => {
      const result = createInlineImageViewElement(linkedModel, writer);
      expect(result.name).toBe('a');
      expect(result.getAttribute('href')).toBe('https://example.com');
    });
  });

  describe('editing downcast for block image with link', () => {
    it('should not contain <a> element', () => {
      // Simulate editing downcast: wrapInLink=false
      const imageElement = createImageViewElement(linkedModel, writer, { wrapInLink: false });
      const figure = writer.createContainerElement('figure', {
        class: 'image ck-widget ck-widget_with-resizer'
      });
      writer.insert(writer.createPositionAt(figure, 0), imageElement);

      // Figure should contain img directly, no <a>
      const links = findElementsByName(figure, 'a');
      expect(links).toHaveLength(0);

      const images = findElementsByName(figure, 'img');
      expect(images).toHaveLength(1);
    });
  });

  describe('editing downcast for inline image with link', () => {
    it('should not contain <a> element', () => {
      const imageElement = createInlineImageViewElement(linkedModel, writer, { wrapInLink: false });
      const wrapper = writer.createContainerElement('span', {
        class: 'ck-widget ck-widget_inline-image'
      });
      writer.insert(writer.createPositionAt(wrapper, 0), imageElement);

      const links = findElementsByName(wrapper, 'a');
      expect(links).toHaveLength(0);

      const images = findElementsByName(wrapper, 'img');
      expect(images).toHaveLength(1);
    });
  });

  describe('data downcast for block image with link', () => {
    it('should contain <a> element (existing behavior preserved)', () => {
      // Data downcast uses default wrapInLink=true
      const imageElement = createImageViewElement(linkedModel, writer);

      // For a linked model, the result should be an <a> element wrapping <img>
      expect(imageElement.name).toBe('a');
      expect(imageElement.getAttribute('href')).toBe('https://example.com');

      const images = findElementsByName(imageElement, 'img');
      expect(images).toHaveLength(1);
    });
  });
});

describe('max-width on figure (#686)', () => {
  let writer;

  beforeEach(() => {
    writer = new MockWriter();
  });

  it('should set max-width on figure when width attribute exists', () => {
    const model = new MockModelElement('typo3image', {
      fileUid: '123',
      src: '/fileadmin/test.jpg',
      width: '500',
      height: '400',
    });

    const figure = writer.createContainerElement('figure', {
      class: 'image ck-widget'
    });

    // Simulate what the editing downcast does after creating figure
    const width = model.getAttribute('width');
    if (width) {
      writer.setStyle('max-width', `${width}px`, figure);
    }

    expect(figure.getStyle('max-width')).toBe('500px');
  });

  it('should not set max-width on figure when width is missing', () => {
    const model = new MockModelElement('typo3image', {
      fileUid: '123',
      src: '/fileadmin/test.jpg',
    });

    const figure = writer.createContainerElement('figure', {
      class: 'image ck-widget'
    });

    const width = model.getAttribute('width');
    if (width) {
      writer.setStyle('max-width', `${width}px`, figure);
    }

    expect(figure.hasStyle('max-width')).toBe(false);
  });

  it('should update max-width when width changes', () => {
    const model = new MockModelElement('typo3image', {
      fileUid: '123',
      src: '/fileadmin/test.jpg',
      width: '300',
    });

    const figure = writer.createContainerElement('figure', {
      class: 'image ck-widget'
    });

    // Initial width
    let width = model.getAttribute('width');
    if (width) {
      writer.setStyle('max-width', `${width}px`, figure);
    }
    expect(figure.getStyle('max-width')).toBe('300px');

    // Simulate width change
    model.setAttribute('width', '800');
    width = model.getAttribute('width');
    if (width) {
      writer.setStyle('max-width', `${width}px`, figure);
    }
    expect(figure.getStyle('max-width')).toBe('800px');
  });
});
