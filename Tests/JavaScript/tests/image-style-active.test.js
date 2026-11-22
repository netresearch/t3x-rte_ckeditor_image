/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * The style dropdown shows an <img> style as active when the selected block
 * image carries all classes of the style.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { ClassicEditor } from '@ckeditor/ckeditor5-editor-classic';
import { Paragraph } from '@ckeditor/ckeditor5-paragraph';
import { Link } from '@ckeditor/ckeditor5-link';
import { GeneralHtmlSupport } from '@ckeditor/ckeditor5-html-support';
import { StyleUtils } from '@ckeditor/ckeditor5-style';
import { WidgetToolbarRepository } from '@ckeditor/ckeditor5-widget';
import Typo3Image from '../../../Resources/Public/JavaScript/Plugins/typo3image.js';

let editor;
let element;

function selectImage() {
  const image = Array.from(editor.model.document.getRoot().getChildren()).find((node) => node.is('element', 'typo3image'));
  editor.model.change((writer) => writer.setSelection(image, 'on'));
  return image;
}

describe('isStyleActiveForBlock for typo3image', () => {
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
    editor.setData('<figure class="image image-left framed"><img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" data-htmlarea-file-table="sys_file" width="300" height="200"></figure>');
  });

  afterEach(async () => {
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  const isActive = (definition, block) => editor.plugins.get('StyleUtils').isStyleActiveForBlock(definition, block);

  it('is active when the image has all classes of an img style', () => {
    const image = selectImage();

    expect(isActive({ element: 'img', classes: ['framed', 'image-left'] }, image)).toBe(true);
  });

  it('is not active when a class of the style is missing', () => {
    const image = selectImage();

    expect(isActive({ element: 'img', classes: ['framed', 'rounded'] }, image)).toBe(false);
  });

  it('leaves styles of other elements alone', () => {
    const image = selectImage();

    expect(isActive({ element: 'p', classes: ['framed'] }, image)).toBe(false);
  });
});
