/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Characterization of the post-fixer that removes the link plugin attributes
 * (linkHref, linkTarget, htmlA, ...) from an inline image that has its own
 * link in the imageLink* attributes. Without it the image gets a second,
 * outer <a>.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { ClassicEditor } from '@ckeditor/ckeditor5-editor-classic';
import { Paragraph } from '@ckeditor/ckeditor5-paragraph';
import { Link } from '@ckeditor/ckeditor5-link';
import { GeneralHtmlSupport } from '@ckeditor/ckeditor5-html-support';
import { StyleUtils } from '@ckeditor/ckeditor5-style';
import { WidgetToolbarRepository } from '@ckeditor/ckeditor5-widget';
import Typo3Image from '../../../Resources/Public/JavaScript/Plugins/typo3image.js';

const INLINE_IMG = '<img class="image-inline" src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" data-htmlarea-file-table="sys_file" width="30" height="20" alt="Alt">';

let editor;
let element;

function inlineImage() {
  const paragraph = editor.model.document.getRoot().getChild(0);
  return Array.from(paragraph.getChildren()).find((node) => node.is('element', 'typo3imageInline'));
}

function linkPluginKeys(item) {
  return Array.from(item.getAttributeKeys()).filter((key) => key.startsWith('link') || key === 'htmlA');
}

describe('Inline image link post-fixer', () => {
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
      htmlSupport: { allow: [{ name: 'a', attributes: true, classes: true }] },
    });
  });

  afterEach(async () => {
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  it('keeps one <a> around a linked inline image in text', () => {
    editor.setData(`<p>Before <a href="https://example.com/" target="_blank" title="T">${INLINE_IMG}</a> after</p>`);

    const image = inlineImage();
    expect(image.getAttribute('imageLinkHref')).toBe('https://example.com/');
    expect(linkPluginKeys(image)).toEqual([]);
    expect(editor.getData().match(/<a /g)).toHaveLength(1);
  });

  it('removes every link plugin attribute set later on an image with its own link', () => {
    editor.setData(`<p>Before <a href="https://example.com/">${INLINE_IMG}</a> after</p>`);
    const image = inlineImage();

    editor.model.change((writer) => {
      writer.setAttribute('linkHref', 'https://other.example/', image);
      writer.setAttribute('linkTarget', '_blank', image);
      writer.setAttribute('linkTitle', 'Other', image);
    });

    expect(linkPluginKeys(inlineImage())).toEqual([]);
    expect(inlineImage().getAttribute('imageLinkHref')).toBe('https://example.com/');
  });

  it('keeps the link plugin attributes of an inline image without its own link', () => {
    editor.setData(`<p>Before ${INLINE_IMG} after</p>`);
    const image = inlineImage();

    editor.model.change((writer) => {
      writer.setAttribute('linkHref', 'https://other.example/', image);
    });

    expect(inlineImage().hasAttribute('imageLinkHref')).toBe(false);
    expect(inlineImage().getAttribute('linkHref')).toBe('https://other.example/');
  });
});
