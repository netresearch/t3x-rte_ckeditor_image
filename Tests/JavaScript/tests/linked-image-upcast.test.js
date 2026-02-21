/**
 * Unit tests for linked image upcast converter.
 *
 * Tests the fix for issue #565: duplicate links when images are wrapped in <a> tags.
 * The upcast converter must properly consume the parent <a> element to prevent
 * CKEditor's General HTML Support from also processing it.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/565
 */

import { describe, it, expect, beforeEach } from 'vitest';
import {
  MockViewElement,
  MockConversionApi,
  createLinkedImageView,
  createDoubleWrappedLinkedImageView,
  createStandaloneImageView,
} from '../mocks/ckeditor-mocks.js';

/**
 * Dedicated upcast converter for corrupted double-link structure: <a><a><img></a></a>
 * Mirrors the production converter at typo3image.js lines ~2028-2116.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/667
 */
function doubleLinkedImageUpcastConverter(viewOuterLink, conversionApi) {
  const { writer, consumable } = conversionApi;

  let innerLink = null;
  let imgElement = null;

  for (const child of viewOuterLink.getChildren()) {
    if (child.is('element', 'a')) {
      innerLink = child;
      for (const innerChild of child.getChildren()) {
        if (innerChild.is('element', 'img') && innerChild.getAttribute('data-htmlarea-file-uid')) {
          imgElement = innerChild;
          break;
        }
      }
    }
  }

  if (!innerLink || !imgElement) {
    return null;
  }

  if (!consumable.test(viewOuterLink, { name: true }) ||
      !consumable.test(innerLink, { name: true }) ||
      !consumable.test(imgElement, { name: true })) {
    return null;
  }

  consumable.consume(viewOuterLink, { name: true });
  consumable.consume(innerLink, { name: true });
  consumable.consume(imgElement, { name: true });

  // Use inner link attributes (they have more complete info like data-link-params)
  const linkHref = innerLink.getAttribute('href') || viewOuterLink.getAttribute('href') || '';
  const linkTarget = innerLink.getAttribute('target') || viewOuterLink.getAttribute('target') || '';
  const linkTitle = innerLink.getAttribute('title') || viewOuterLink.getAttribute('title') || '';

  const imageAttributes = {
    fileUid: imgElement.getAttribute('data-htmlarea-file-uid'),
    fileTable: imgElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
    src: imgElement.getAttribute('src'),
    width: imgElement.getAttribute('width') || '',
    height: imgElement.getAttribute('height') || '',
    class: imgElement.getAttribute('class') || '',
    alt: imgElement.getAttribute('alt') || ''
  };

  if (linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/') {
    imageAttributes.imageLinkHref = linkHref;
    if (linkTarget && linkTarget.trim() !== '') {
      imageAttributes.imageLinkTarget = linkTarget;
    }
    if (linkTitle && linkTitle.trim() !== '') {
      imageAttributes.imageLinkTitle = linkTitle;
    }
  }

  const imgClass = (imageAttributes.class || '').toString();
  const isInline = imgClass.split(/\s+/).includes('image-inline');
  return writer.createElement(isInline ? 'typo3imageInline' : 'typo3image', imageAttributes);
}

/**
 * Extract the converter logic from typo3image.js for testing.
 * This mirrors the linked image upcast converter added in the fix.
 *
 * NOTE: This test implementation mirrors the production code but is maintained
 * separately. Consider importing from the production module when ES module
 * exports are available.
 */
function linkedImageUpcastConverter(viewElement, conversionApi) {
  const { writer, consumable } = conversionApi;

  // Find img child with data-htmlarea-file-uid
  let imgElement = null;
  for (const child of viewElement.getChildren()) {
    if (child.is('element', 'img') && child.getAttribute('data-htmlarea-file-uid')) {
      imgElement = child;
      break;
    }
  }

  // If no TYPO3 image found, let other converters handle this <a>
  if (!imgElement) {
    return null;
  }

  // Extract link attributes from <a> element
  const linkHref = viewElement.getAttribute('href') || '';
  const linkTarget = viewElement.getAttribute('target') || '';
  const linkTitle = viewElement.getAttribute('title') || '';

  // Determine if this is a real link (non-empty, non-placeholder href)
  const hasValidLink = linkHref && linkHref.trim() !== '' && linkHref.trim() !== '/';

  // Test if both elements can be consumed before committing to conversion
  if (
    !consumable.test(viewElement, { name: true }) ||
    !consumable.test(imgElement, { name: true })
  ) {
    return null;
  }

  // Consume the <a> element and its children to prevent GHS from processing
  // Note: Only consume 'name' - consuming all attributes causes iteration error
  consumable.consume(viewElement, { name: true });
  consumable.consume(imgElement, { name: true });

  // Build image attributes from the img element
  const imageAttributes = {
    fileUid: imgElement.getAttribute('data-htmlarea-file-uid'),
    fileTable: imgElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
    src: imgElement.getAttribute('src'),
    width: imgElement.getAttribute('width') || '',
    height: imgElement.getAttribute('height') || '',
    alt: imgElement.getAttribute('alt') || ''
  };

  // Only add link attributes if href is valid (not empty/placeholder)
  if (hasValidLink) {
    imageAttributes.imageLinkHref = linkHref;
    if (linkTarget && linkTarget.trim() !== '') {
      imageAttributes.imageLinkTarget = linkTarget;
    }
    if (linkTitle && linkTitle.trim() !== '') {
      imageAttributes.imageLinkTitle = linkTitle;
    }
  }

  // Check if this should be an inline image based on class
  const imgClass = imgElement.getAttribute('class') || '';
  const classList = imgClass.split(/\s+/);
  const isInlineImage = classList.includes('image-inline');

  return writer.createElement(isInlineImage ? 'typo3imageInline' : 'typo3image', imageAttributes);
}

/**
 * Standalone image upcast converter (should skip linked images)
 */
function standaloneImageUpcastConverter(viewElement, conversionApi) {
  const { writer } = conversionApi;

  // Skip if parent is <a> - handled by linked image converter
  if (viewElement.parent && viewElement.parent.name === 'a') {
    return null;
  }

  const imageAttributes = {
    fileUid: viewElement.getAttribute('data-htmlarea-file-uid'),
    fileTable: viewElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
    src: viewElement.getAttribute('src'),
    width: viewElement.getAttribute('width') || '',
    height: viewElement.getAttribute('height') || '',
    alt: viewElement.getAttribute('alt') || '',
  };

  return writer.createElement('typo3image', imageAttributes);
}

describe('Linked Image Upcast Converter (#565)', () => {
  let conversionApi;

  beforeEach(() => {
    conversionApi = new MockConversionApi();
  });

  describe('linkedImageUpcastConverter', () => {
    it('should convert <a><img data-htmlarea-file-uid="..."/></a> to typo3image with link attributes', () => {
      const { anchor } = createLinkedImageView(
        { href: 'https://example.com', target: '_blank', title: 'Link Title' },
        {
          'data-htmlarea-file-uid': '123',
          'src': '/fileadmin/test.jpg',
          'width': '300',
          'height': '200',
          'alt': 'Test Image'
        }
      );

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      expect(result).not.toBeNull();
      expect(result.name).toBe('typo3image');
      expect(result.getAttribute('fileUid')).toBe('123');
      expect(result.getAttribute('imageLinkHref')).toBe('https://example.com');
      expect(result.getAttribute('imageLinkTarget')).toBe('_blank');
      expect(result.getAttribute('imageLinkTitle')).toBe('Link Title');
    });

    it('should consume both <a> and <img> elements to prevent duplicate processing', () => {
      const { anchor, img } = createLinkedImageView(
        { href: 'https://example.com' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      linkedImageUpcastConverter(anchor, conversionApi);

      // Both elements should be consumed
      expect(conversionApi.consumable.isConsumed(anchor)).toBe(true);
      expect(conversionApi.consumable.isConsumed(img)).toBe(true);
    });

    it('should return null without consuming when elements are already consumed', () => {
      // Note: We only need the anchor for this test, img is implicitly created
      // but not used in the assertion (we're testing pre-consumption behavior)
      const { anchor } = createLinkedImageView(
        { href: 'https://example.com' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      // Pre-consume the anchor to simulate another converter handling it first
      conversionApi.consumable.consume(anchor, { name: true });

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      // Should return null because test() fails for pre-consumed element
      expect(result).toBeNull();
    });

    it('should return null for <a> without TYPO3 image (no data-htmlarea-file-uid)', () => {
      const img = new MockViewElement('img', { src: '/external.jpg' });
      const anchor = new MockViewElement('a', { href: 'https://example.com' }, [img]);

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      expect(result).toBeNull();
      // Elements should NOT be consumed - let other converters handle them
      expect(conversionApi.consumable.isConsumed(anchor)).toBe(false);
    });

    it('should convert image with empty href but without link attributes', () => {
      const { anchor, img } = createLinkedImageView(
        { href: '' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      // Should still convert the image (not null)
      expect(result).not.toBeNull();
      expect(result.name).toBe('typo3image');
      expect(result.getAttribute('fileUid')).toBe('123');
      // But should NOT have link attributes
      expect(result.getAttribute('imageLinkHref')).toBeUndefined();
      expect(result.getAttribute('imageLinkTarget')).toBeUndefined();
      expect(result.getAttribute('imageLinkTitle')).toBeUndefined();
      // Elements should be consumed to prevent GHS duplicate
      expect(conversionApi.consumable.isConsumed(anchor)).toBe(true);
      expect(conversionApi.consumable.isConsumed(img)).toBe(true);
    });

    it('should convert image with href="/" but without link attributes', () => {
      const { anchor, img } = createLinkedImageView(
        { href: '/' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      // Should still convert the image (not null)
      expect(result).not.toBeNull();
      expect(result.name).toBe('typo3image');
      // But should NOT have link attributes (/ is a placeholder)
      expect(result.getAttribute('imageLinkHref')).toBeUndefined();
      // Elements should be consumed
      expect(conversionApi.consumable.isConsumed(anchor)).toBe(true);
      expect(conversionApi.consumable.isConsumed(img)).toBe(true);
    });

    it('should handle link with only href (no target/title)', () => {
      const { anchor } = createLinkedImageView(
        { href: 'https://example.com' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      expect(result.getAttribute('imageLinkHref')).toBe('https://example.com');
      expect(result.getAttribute('imageLinkTarget')).toBeUndefined();
      expect(result.getAttribute('imageLinkTitle')).toBeUndefined();
    });

    it('should preserve all image attributes', () => {
      const { anchor } = createLinkedImageView(
        { href: 'https://example.com' },
        {
          'data-htmlarea-file-uid': '456',
          'data-htmlarea-file-table': 'sys_file_reference',
          'src': '/fileadmin/images/photo.jpg',
          'width': '800',
          'height': '600',
          'alt': 'Photo description'
        }
      );

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      expect(result.getAttribute('fileUid')).toBe('456');
      expect(result.getAttribute('fileTable')).toBe('sys_file_reference');
      expect(result.getAttribute('src')).toBe('/fileadmin/images/photo.jpg');
      expect(result.getAttribute('width')).toBe('800');
      expect(result.getAttribute('height')).toBe('600');
      expect(result.getAttribute('alt')).toBe('Photo description');
    });
  });

  describe('standaloneImageUpcastConverter', () => {
    it('should convert standalone <img> to typo3image', () => {
      const img = createStandaloneImageView({
        'data-htmlarea-file-uid': '789',
        'src': '/fileadmin/standalone.jpg',
        'width': '400',
        'height': '300',
        'alt': 'Standalone'
      });

      const result = standaloneImageUpcastConverter(img, conversionApi);

      expect(result).not.toBeNull();
      expect(result.name).toBe('typo3image');
      expect(result.getAttribute('fileUid')).toBe('789');
      expect(result.getAttribute('imageLinkHref')).toBeUndefined();
    });

    it('should return null for <img> inside <a> (handled by linkedImageUpcastConverter)', () => {
      const { img } = createLinkedImageView(
        { href: 'https://example.com' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      const result = standaloneImageUpcastConverter(img, conversionApi);

      // Should return null - parent is <a>, so linkedImageUpcastConverter handles it
      expect(result).toBeNull();
    });
  });

  describe('Linked Inline Images (#580)', () => {
    it('should convert linked inline image to typo3imageInline', () => {
      const { anchor } = createLinkedImageView(
        { href: 'https://example.com', target: '_blank' },
        {
          'data-htmlarea-file-uid': '123',
          'src': '/fileadmin/test.jpg',
          'class': 'image-inline',
          'width': '100',
          'height': '80',
          'alt': 'Inline image'
        }
      );

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      expect(result).not.toBeNull();
      expect(result.name).toBe('typo3imageInline');
      expect(result.getAttribute('fileUid')).toBe('123');
      expect(result.getAttribute('imageLinkHref')).toBe('https://example.com');
      expect(result.getAttribute('imageLinkTarget')).toBe('_blank');
    });

    it('should convert linked block image to typo3image (not inline)', () => {
      const { anchor } = createLinkedImageView(
        { href: 'https://example.com' },
        {
          'data-htmlarea-file-uid': '456',
          'src': '/fileadmin/block.jpg',
          'class': 'image-block',
          'width': '800',
          'height': '600'
        }
      );

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      expect(result).not.toBeNull();
      expect(result.name).toBe('typo3image');
      expect(result.getAttribute('fileUid')).toBe('456');
    });

    it('should handle image-inline alongside other classes', () => {
      const { anchor } = createLinkedImageView(
        { href: 'https://example.com' },
        {
          'data-htmlarea-file-uid': '789',
          'src': '/fileadmin/multi.jpg',
          'class': 'custom-class image-inline another-class'
        }
      );

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      expect(result.name).toBe('typo3imageInline');
    });

    it('should NOT match image-inline-block as inline (exact class match)', () => {
      const { anchor } = createLinkedImageView(
        { href: 'https://example.com' },
        {
          'data-htmlarea-file-uid': '999',
          'src': '/fileadmin/notinline.jpg',
          'class': 'image-inline-block'
        }
      );

      const result = linkedImageUpcastConverter(anchor, conversionApi);

      // Should be typo3image, not typo3imageInline
      expect(result.name).toBe('typo3image');
    });
  });

  describe('Integration: No duplicate links', () => {
    it('should process linked image exactly once (no duplicates)', () => {
      const { anchor, img } = createLinkedImageView(
        { href: 'https://example.com' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      // First: linked image converter processes the <a>
      const linkResult = linkedImageUpcastConverter(anchor, conversionApi);

      // Second: standalone converter should skip the <img> (parent is <a>)
      const standaloneResult = standaloneImageUpcastConverter(img, conversionApi);

      // Only one model element should be created
      expect(linkResult).not.toBeNull();
      expect(standaloneResult).toBeNull();

      // The model element should have link attributes (not duplicated)
      expect(linkResult.getAttribute('imageLinkHref')).toBe('https://example.com');
    });
  });

  describe('Double-wrapped links — dedicated converter (#667)', () => {
    it('should convert <a><a><img data-htmlarea-file-uid="..."/></a></a> to model element', () => {
      const { outerAnchor } = createDoubleWrappedLinkedImageView(
        { href: 't3://page?uid=1#1', target: '_blank', class: 'image image-inline' },
        { href: 't3://page?uid=1#1', target: '_blank', class: 'image image-inline' },
        {
          'data-htmlarea-file-uid': '2',
          'src': '/fileadmin/test.jpg',
          'class': 'image-inline',
          'width': '300',
          'height': '200',
          'alt': 'Test'
        }
      );

      const result = doubleLinkedImageUpcastConverter(outerAnchor, conversionApi);

      expect(result).not.toBeNull();
      expect(result.name).toBe('typo3imageInline');
      expect(result.getAttribute('fileUid')).toBe('2');
      expect(result.getAttribute('imageLinkHref')).toBe('t3://page?uid=1#1');
      expect(result.getAttribute('imageLinkTarget')).toBe('_blank');
    });

    it('should consume all three elements: outer <a>, inner <a>, and <img>', () => {
      const { outerAnchor, innerAnchor, img } = createDoubleWrappedLinkedImageView(
        { href: 'https://example.com' },
        { href: 'https://example.com' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      doubleLinkedImageUpcastConverter(outerAnchor, conversionApi);

      // All three elements must be consumed to prevent GHS from re-processing
      expect(conversionApi.consumable.isConsumed(outerAnchor)).toBe(true);
      expect(conversionApi.consumable.isConsumed(innerAnchor)).toBe(true);
      expect(conversionApi.consumable.isConsumed(img)).toBe(true);
    });

    it('should prefer inner <a> link attributes (more complete info)', () => {
      const { outerAnchor } = createDoubleWrappedLinkedImageView(
        { href: 'https://outer.com', target: '_blank', title: 'Outer Title' },
        { href: 'https://inner.com', target: '_self', title: 'Inner Title' },
        { 'data-htmlarea-file-uid': '456', 'src': '/test.jpg' }
      );

      const result = doubleLinkedImageUpcastConverter(outerAnchor, conversionApi);

      // Inner link attributes take precedence (they have more complete info)
      expect(result.getAttribute('imageLinkHref')).toBe('https://inner.com');
      expect(result.getAttribute('imageLinkTarget')).toBe('_self');
      expect(result.getAttribute('imageLinkTitle')).toBe('Inner Title');
    });

    it('should fall back to outer <a> attributes when inner has none', () => {
      const { outerAnchor } = createDoubleWrappedLinkedImageView(
        { href: 'https://outer.com', target: '_blank' },
        {},
        { 'data-htmlarea-file-uid': '789', 'src': '/test.jpg' }
      );

      const result = doubleLinkedImageUpcastConverter(outerAnchor, conversionApi);

      expect(result.getAttribute('imageLinkHref')).toBe('https://outer.com');
      expect(result.getAttribute('imageLinkTarget')).toBe('_blank');
    });

    it('should return null for nested <a> without TYPO3 image', () => {
      const innerImg = new MockViewElement('img', { src: '/external.jpg' }); // No file-uid
      const innerAnchor = new MockViewElement('a', { href: '/page' }, [innerImg]);
      const outerAnchor = new MockViewElement('a', { href: '/page' }, [innerAnchor]);

      const result = doubleLinkedImageUpcastConverter(outerAnchor, conversionApi);

      expect(result).toBeNull();
      expect(conversionApi.consumable.isConsumed(outerAnchor)).toBe(false);
    });

    it('should return null when outer <a> is already consumed', () => {
      const { outerAnchor } = createDoubleWrappedLinkedImageView(
        { href: 'https://example.com' },
        { href: 'https://example.com' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      // Pre-consume outer to simulate another converter
      conversionApi.consumable.consume(outerAnchor, { name: true });

      const result = doubleLinkedImageUpcastConverter(outerAnchor, conversionApi);

      expect(result).toBeNull();
    });

    it('should delegate double-wrapped links from generic converter to dedicated one', () => {
      const { outerAnchor, innerAnchor, img } = createDoubleWrappedLinkedImageView(
        { href: 'https://example.com' },
        { href: 'https://example.com' },
        { 'data-htmlarea-file-uid': '123', 'src': '/test.jpg' }
      );

      // Generic converter should return null (nested <a> is "other content")
      const genericResult = linkedImageUpcastConverter(outerAnchor, conversionApi);
      expect(genericResult).toBeNull();

      // Dedicated converter handles it
      const dedicatedResult = doubleLinkedImageUpcastConverter(outerAnchor, conversionApi);
      expect(dedicatedResult).not.toBeNull();
      expect(dedicatedResult.getAttribute('fileUid')).toBe('123');

      // All elements consumed
      expect(conversionApi.consumable.isConsumed(outerAnchor)).toBe(true);
      expect(conversionApi.consumable.isConsumed(innerAnchor)).toBe(true);
      expect(conversionApi.consumable.isConsumed(img)).toBe(true);
    });
  });
});
