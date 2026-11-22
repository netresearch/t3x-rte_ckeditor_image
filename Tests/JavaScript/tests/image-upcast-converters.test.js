/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Characterization of the image upcast converters of the real Typo3Image
 * plugin: which model element and which attributes each supported HTML
 * structure produces.
 *
 * The HTML parser cannot produce nested <a> elements, so structures with an
 * <a> inside an <a> are built as view trees with the UpcastWriter and
 * converted with editor.data.toModel().
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { ClassicEditor } from '@ckeditor/ckeditor5-editor-classic';
import { Paragraph } from '@ckeditor/ckeditor5-paragraph';
import { Link } from '@ckeditor/ckeditor5-link';
import { GeneralHtmlSupport } from '@ckeditor/ckeditor5-html-support';
import { StyleUtils } from '@ckeditor/ckeditor5-style';
import { WidgetToolbarRepository } from '@ckeditor/ckeditor5-widget';
import { UpcastWriter } from '@ckeditor/ckeditor5-engine';
import Typo3Image from '../../../Resources/Public/JavaScript/Plugins/typo3image.js';

const IMG_ATTRS = {
  src: '/fileadmin/test.jpg',
  'data-htmlarea-file-uid': '7',
  'data-htmlarea-file-table': 'sys_file',
  width: '300',
  height: '200',
  alt: 'Alt',
  title: 'Title',
  'data-alt-override': 'true',
  'data-title-override': 'true',
  'data-htmlarea-zoom': 'true',
  'data-noscale': 'true',
  'data-quality': '2',
};

const IMG = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="7" data-htmlarea-file-table="sys_file" width="300" height="200" alt="Alt" title="Title" data-alt-override="true" data-title-override="true" data-htmlarea-zoom="true" data-noscale="true" data-quality="2"';

const IMAGE_BASE = {
  fileUid: '7',
  fileTable: 'sys_file',
  width: '300',
  height: '200',
  alt: 'Alt',
  altOverride: 'true',
  title: 'Title',
  titleOverride: 'true',
  enableZoom: 'true',
  noScale: 'true',
  quality: '2',
  src: '/fileadmin/test.jpg',
};

let editor;
let element;

function findImage(parent) {
  for (const child of parent.getChildren()) {
    if (child.is('element', 'typo3image') || child.is('element', 'typo3imageInline')) {
      return child;
    }
    if (child.is('element')) {
      const found = findImage(child);
      if (found) {
        return found;
      }
    }
  }
  return null;
}

function attributesOf(image) {
  return Object.fromEntries(image.getAttributes());
}

function imageFromData(html) {
  editor.setData(html);
  return findImage(editor.model.document.getRoot());
}

function imageFromView(build) {
  const writer = new UpcastWriter(editor.data.viewDocument);
  const node = build(writer);
  // A node that already has a parent is converted in place, so that the
  // converter still sees that parent
  const viewItem = node.parent ? node : writer.createDocumentFragment(node);
  let image = null;
  editor.model.change(() => {
    image = findImage(editor.data.toModel(viewItem));
  });
  return image;
}

describe('Image upcast converters', () => {
  beforeEach(async () => {
    vi.stubGlobal('ResizeObserver', class {
      observe() {}
      unobserve() {}
      disconnect() {}
    });
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, json: async () => ({ lang: {} }) })));
    element = document.createElement('div');
    document.body.appendChild(element);
    editor = await ClassicEditor.create(element, {
      licenseKey: 'GPL',
      plugins: [Paragraph, Link, GeneralHtmlSupport, StyleUtils, WidgetToolbarRepository, Typo3Image],
      typo3image: { routeUrl: '/typo3/rte/wizard/selectimage?token=test' },
    });
  });

  afterEach(async () => {
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  describe('double-wrapped link <a><a><img></a></a>', () => {
    it('takes the link from the inner <a> and the missing extras from the outer one', () => {
      const image = imageFromView((w) => w.createElement('p', {}, [
        w.createElement('a', { href: 'https://outer.example/', class: 'outer', rel: 'nofollow' }, [
          w.createElement('a', {
            href: 'https://inner.example/',
            target: '_blank',
            title: 'Link title',
            class: 'image-left inner',
            'data-link-params': '&L=1',
            download: 'file.jpg',
          }, [w.createElement('img', { ...IMG_ATTRS, class: 'image-inline' })]),
        ]),
      ]));

      expect(image.name).toBe('typo3imageInline');
      expect(attributesOf(image)).toEqual({
        ...IMAGE_BASE,
        class: 'image-inline',
        imageLinkHref: 'https://inner.example/',
        imageLinkTarget: '_blank',
        imageLinkTitle: 'Link title',
        imageLinkClass: 'inner',
        imageLinkParams: '&L=1',
        imageLinkAttributes: { download: 'file.jpg', rel: 'nofollow' },
        // Set by the link plugin's upcast of the outer <a>; the post-fixer
        // removes it once the image is in the document
        linkHref: 'https://outer.example/',
      });
    });

    it('falls back to the outer <a> attributes and creates a block image', () => {
      const image = imageFromView((w) => w.createElement('a', {
        href: 'https://outer.example/', target: '_self', title: 'Outer', class: 'outer image-right', 'data-link-params': '&x=2',
      }, [
        w.createElement('a', {}, [w.createElement('img', { ...IMG_ATTRS, class: 'image-left' })]),
      ]));

      expect(image.name).toBe('typo3image');
      expect(attributesOf(image)).toEqual({
        ...IMAGE_BASE,
        class: 'image-left',
        imageLinkHref: 'https://outer.example/',
        imageLinkTarget: '_self',
        imageLinkTitle: 'Outer',
        imageLinkClass: 'outer',
        imageLinkParams: '&x=2',
      });
    });

    it('drops a placeholder href of "/" with all link attributes', () => {
      const image = imageFromView((w) => w.createElement('a', { href: '/' }, [
        w.createElement('a', { target: '_blank' }, [w.createElement('img', { ...IMG_ATTRS })]),
      ]));

      expect(attributesOf(image)).toEqual({ ...IMAGE_BASE, class: '' });
    });
  });

  describe('link around a figure <a><figure><img></figure></a>', () => {
    it('moves alignment classes of the link to the figure class', () => {
      const image = imageFromData(`<a href="https://example.com/" target="_blank" title="T" class="image-right lnk" data-link-params="&a=1" rel="noopener"><figure class="image image-left">${IMG}><figcaption>Cap</figcaption></figure></a>`);

      expect(image.name).toBe('typo3image');
      expect(attributesOf(image)).toEqual({
        ...IMAGE_BASE,
        class: 'image image-right',
        imageLinkHref: 'https://example.com/',
        imageLinkTarget: '_blank',
        imageLinkTitle: 'T',
        imageLinkClass: 'lnk',
        imageLinkParams: '&a=1',
        imageLinkAttributes: { rel: 'noopener' },
      });
      expect(Array.from(image.getChildren()).map((child) => child.name)).toEqual(['typo3imageCaption']);
    });

    it('prefers the outer href and the inner data-link-params of a figure with an inner <a>', () => {
      const image = imageFromView((w) => w.createElement('a', { href: 'https://outer.example/' }, [
        w.createElement('figure', { class: 'image' }, [
          w.createElement('a', { href: 'https://inner.example/', 'data-link-params': '&in=1', class: 'inner', download: 'x' }, [
            w.createElement('img', { ...IMG_ATTRS }),
          ]),
        ]),
      ]));

      expect(attributesOf(image)).toEqual({
        ...IMAGE_BASE,
        class: 'image',
        imageLinkHref: 'https://outer.example/',
        imageLinkClass: 'inner',
        imageLinkParams: '&in=1',
        imageLinkAttributes: { download: 'x' },
      });
    });

    it('leaves an <a> around a figure without a FAL image to other converters', () => {
      editor.setData('<a href="https://example.com/"><figure class="image"><img src="/x.jpg"></figure></a>');

      expect(findImage(editor.model.document.getRoot())).toBeNull();
    });
  });

  describe('figure with an image <figure><a><img></a></figure>', () => {
    it('reads the link of the inner <a> without its alignment classes', () => {
      const image = imageFromData(`<figure class="image image-center"><a href="https://example.com/" target="_top" title="T" class="image-left lnk" data-link-params="&b=2" download="d">${IMG}></a><figcaption>Cap</figcaption></figure>`);

      expect(image.name).toBe('typo3image');
      expect(attributesOf(image)).toEqual({
        ...IMAGE_BASE,
        class: 'image image-center',
        imageLinkHref: 'https://example.com/',
        imageLinkTarget: '_top',
        imageLinkTitle: 'T',
        imageLinkClass: 'lnk',
        imageLinkParams: '&b=2',
        imageLinkAttributes: { download: 'd' },
      });
    });

    it('creates an inline image without caption for a figure with image-inline', () => {
      const image = imageFromData(`<figure class="image image-inline">${IMG}><figcaption>Cap</figcaption></figure>`);

      expect(image.name).toBe('typo3imageInline');
      expect(attributesOf(image)).toEqual({ ...IMAGE_BASE, class: 'image image-inline' });
      expect(image.childCount).toBe(0);
    });
  });

  describe('linked image <a><img></a>', () => {
    it('reads all link attributes of an <a> that holds only the image', () => {
      const image = imageFromData(`<p><a href="https://example.com/" target="_blank" title="T" class="image-inline lnk" data-link-params="&c=3" rel="nofollow">${IMG} class="image-inline"></a></p>`);

      expect(image.name).toBe('typo3imageInline');
      expect(attributesOf(image)).toEqual({
        ...IMAGE_BASE,
        class: 'image-inline',
        imageLinkHref: 'https://example.com/',
        imageLinkTarget: '_blank',
        imageLinkTitle: 'T',
        imageLinkClass: 'lnk',
        imageLinkParams: '&c=3',
        imageLinkAttributes: { rel: 'nofollow' },
      });
    });

    it('keeps the image but no link for a placeholder href', () => {
      const image = imageFromData(`<p><a href="/">${IMG} class="image-inline"></a></p>`);

      // linkHref comes from the link plugin's upcast of the <a>
      expect(attributesOf(image)).toEqual({ ...IMAGE_BASE, class: 'image-inline', linkHref: '/' });
    });
  });

  describe('inline image <img class="image-inline">', () => {
    it('reads the link of a parent <a> that holds only the image', () => {
      const image = imageFromView((w) => {
        const img = w.createElement('img', { ...IMG_ATTRS, class: 'image-inline' });
        w.createElement('a', {
          href: 'https://example.com/', target: '_blank', title: 'T', class: 'image-left lnk', 'data-link-params': '&d=4', rel: 'nofollow',
        }, [img]);
        return img;
      });

      expect(attributesOf(image)).toEqual({
        ...IMAGE_BASE,
        class: 'image-inline',
        imageLinkHref: 'https://example.com/',
        imageLinkTarget: '_blank',
        imageLinkTitle: 'T',
        imageLinkClass: 'lnk',
        imageLinkParams: '&d=4',
        imageLinkAttributes: { rel: 'nofollow' },
      });
    });

    it('ignores the link of a parent <a> that also holds text', () => {
      const image = imageFromView((w) => {
        const img = w.createElement('img', { ...IMG_ATTRS, class: 'image-inline' });
        w.createElement('a', { href: 'https://example.com/' }, [w.createText('text '), img]);
        return img;
      });

      expect(attributesOf(image)).toEqual({ ...IMAGE_BASE, class: 'image-inline' });
    });
  });
});
