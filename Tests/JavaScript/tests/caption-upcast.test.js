/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Caption upcast must keep the inline content of <figcaption>.
 *
 * The typo3imageCaption schema allows the content of $block, so soft breaks
 * (<br>) and text attributes (<strong>, <em>, links) are valid caption
 * content. These tests load the real Typo3Image plugin into a real CKEditor 5
 * editor and check that such content survives setData() -> getData().
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { ClassicEditor } from '@ckeditor/ckeditor5-editor-classic';
import { Paragraph } from '@ckeditor/ckeditor5-paragraph';
import { Bold, Italic } from '@ckeditor/ckeditor5-basic-styles';
import { ShiftEnter } from '@ckeditor/ckeditor5-enter';
import { Link } from '@ckeditor/ckeditor5-link';
import { GeneralHtmlSupport } from '@ckeditor/ckeditor5-html-support';
import { StyleUtils } from '@ckeditor/ckeditor5-style';
import { WidgetToolbarRepository } from '@ckeditor/ckeditor5-widget';
import Typo3Image from '../../../Resources/Public/JavaScript/Plugins/typo3image.js';

const IMG = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" data-htmlarea-file-table="sys_file" width="300" height="200" alt="Alt">';
const CAPTION = 'Line1<br>Line2 <strong>bold</strong>';

let editor;
let element;

async function createEditor() {
  // jsdom has no ResizeObserver (used by the ClassicEditor toolbar) and no
  // backend route for the plugin's translation request.
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
    plugins: [Paragraph, Bold, Italic, ShiftEnter, Link, GeneralHtmlSupport, StyleUtils, WidgetToolbarRepository, Typo3Image],
    typo3image: { routeUrl: '/typo3/rte/wizard/selectimage?token=test' },
  });
  return editor;
}

function getCaption() {
  const root = editor.model.document.getRoot();
  const image = Array.from(root.getChildren()).find((node) => node.is('element', 'typo3image'));
  expect(image, 'typo3image model element').toBeDefined();
  const captions = Array.from(image.getChildren()).filter((node) => node.is('element', 'typo3imageCaption'));
  expect(captions, 'exactly one typo3imageCaption').toHaveLength(1);
  return captions[0];
}

function figcaptionsOf(html) {
  return html.match(/<figcaption>.*?<\/figcaption>/g) || [];
}

describe('Caption upcast keeps inline markup', () => {
  beforeEach(async () => {
    await createEditor();
  });

  afterEach(async () => {
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  it('keeps <br> and <strong> of a figure caption in the model', () => {
    editor.setData(`<figure class="image">${IMG}<figcaption>${CAPTION}</figcaption></figure>`);

    const children = Array.from(getCaption().getChildren());
    expect(children.map((node) => node.name ?? node.data)).toEqual(['Line1', 'softBreak', 'Line2 ', 'bold']);
    expect(children[3].getAttribute('bold')).toBe(true);
  });

  it('keeps <br> and <strong> of a figure caption in the saved data', () => {
    editor.setData(`<figure class="image">${IMG}<figcaption>${CAPTION}</figcaption></figure>`);

    expect(figcaptionsOf(editor.getData())).toEqual([`<figcaption>${CAPTION}</figcaption>`]);
  });

  it('keeps <br> and <strong> of a caption in a figure with a linked image', () => {
    editor.setData(`<figure class="image"><a href="https://example.com">${IMG}</a><figcaption>${CAPTION}</figcaption></figure>`);

    expect(figcaptionsOf(editor.getData())).toEqual([`<figcaption>${CAPTION}</figcaption>`]);
  });

  it('keeps <br> and <strong> of a caption in a figure wrapped in a link', () => {
    editor.setData(`<a href="https://example.com"><figure class="image">${IMG}<figcaption>${CAPTION}</figcaption></figure></a>`);

    // The outer link is also applied to the caption text (the figcaption comes
    // out as <figcaption><a href="...">...</a></figcaption>). That happens with
    // or without this caption fix and is not what this test checks.
    const figcaptions = figcaptionsOf(editor.getData());
    expect(figcaptions).toHaveLength(1);
    expect(figcaptions[0]).toContain(CAPTION);
  });

  it('keeps <em> and <a> of a figure caption in the saved data', () => {
    const link = '<a href="https://example.com/author">Author</a>';
    editor.setData(`<figure class="image">${IMG}<figcaption><em>Photo</em> by ${link}</figcaption></figure>`);

    // The Italic plugin writes <em> back as <i>.
    expect(figcaptionsOf(editor.getData())).toEqual([`<figcaption><i>Photo</i> by ${link}</figcaption>`]);
  });

  it('keeps a plain-text caption unchanged', () => {
    editor.setData(`<figure class="image">${IMG}<figcaption>Plain caption</figcaption></figure>`);

    expect(figcaptionsOf(editor.getData())).toEqual(['<figcaption>Plain caption</figcaption>']);
    expect(Array.from(getCaption().getChildren()).map((node) => node.data)).toEqual(['Plain caption']);
  });
});
