/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Characterization of the ways the image dialog is opened for an existing
 * image: the "Edit image" balloon button and a double click on the image
 * widget, and of the "Toggle inline/block" command.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { ClassicEditor } from '@ckeditor/ckeditor5-editor-classic';
import { Paragraph } from '@ckeditor/ckeditor5-paragraph';
import { Link } from '@ckeditor/ckeditor5-link';
import { GeneralHtmlSupport } from '@ckeditor/ckeditor5-html-support';
import { StyleUtils } from '@ckeditor/ckeditor5-style';
import { WidgetToolbarRepository } from '@ckeditor/ckeditor5-widget';
import { calls as modalCalls, hidden as modalHidden } from '../mocks/typo3-backend-modal.js';
import Typo3Image from '../../../Resources/Public/JavaScript/Plugins/typo3image.js';

const img = (uid, extra = '') => `<img src="/fileadmin/${uid}.jpg" data-htmlarea-file-uid="${uid}" data-htmlarea-file-table="sys_file" width="300" height="200" alt="Alt ${uid}"${extra}>`;

let editor;
let element;
let infoUids;

function imageInfo(uid) {
  return {
    uid,
    url: `/fileadmin/${uid}.jpg`,
    storageDriver: 'Local',
    width: 300,
    height: 200,
    title: '',
    alt: '',
    processed: { width: 300, height: 200, url: `/fileadmin/${uid}.jpg` },
    lang: {
      override: 'Override %s',
      overrideNoDefault: 'Override',
      noDefaultMetadata: 'No default %s',
      imageProperties: 'Image properties',
      cancel: 'Cancel',
      save: 'Save',
    },
  };
}

function findImages(parent = editor.model.document.getRoot(), found = []) {
  for (const child of parent.getChildren()) {
    if (child.is('element', 'typo3image') || child.is('element', 'typo3imageInline')) {
      found.push(child);
    } else if (child.is('element')) {
      findImages(child, found);
    }
  }
  return found;
}

function imageByUid(uid) {
  return findImages().find((image) => image.getAttribute('fileUid') === String(uid));
}

async function dialog() {
  await vi.waitFor(() => expect(modalCalls).toHaveLength(1));
  return modalCalls[0];
}

function radiosDisabled(modal) {
  return ['none', 'enlarge', 'link'].map((name) => modal.content.querySelector(`#clickBehavior-${name}`).disabled);
}

function select(modelElement) {
  editor.model.change((writer) => writer.setSelection(modelElement, 'on'));
}

function dblclick(target) {
  editor.editing.view.document.fire('dblclick:typo3image', { target, domEvent: {} });
}

function stubBrowser() {
  vi.stubGlobal('ResizeObserver', class {
    observe() {}
    unobserve() {}
    disconnect() {}
  });
  vi.stubGlobal('fetch', vi.fn(async (url) => {
    const fileId = new URL(String(url), 'http://localhost').searchParams.get('fileId');
    if (fileId && fileId !== 'translations') {
      infoUids.push(fileId);
      return { ok: true, json: async () => imageInfo(Number(fileId)) };
    }
    return { ok: true, json: async () => ({ lang: {} }) };
  }));
}

async function createEditor() {
  element = document.createElement('div');
  document.body.appendChild(element);
  editor = await ClassicEditor.create(element, {
    licenseKey: 'GPL',
    plugins: [Paragraph, Link, GeneralHtmlSupport, StyleUtils, WidgetToolbarRepository, Typo3Image],
    typo3image: { routeUrl: '/typo3/rte/wizard/selectimage?token=test' },
  });
}

describe('Opening the image dialog', () => {
  beforeEach(async () => {
    modalCalls.length = 0;
    modalHidden.length = 0;
    infoUids = [];
    stubBrowser();
    await createEditor();
    editor.setData([
      `<figure class="image">${img(1)}<figcaption>Caption <strong>one</strong></figcaption></figure>`,
      `<p>Own link <a href="https://own.example/">${img(2, ' class="image-inline"')}</a> text</p>`,
      `<p><a href="https://text.example/">Linked text ${img(3, ' class="image-inline"')} more</a></p>`,
      `<p>Plain ${img(4, ' class="image-inline"')} text</p>`,
    ].join(''));
  });

  afterEach(async () => {
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  describe('Edit image button', () => {
    it('opens the dialog of a block image with its caption text', async () => {
      select(imageByUid(1));
      editor.ui.componentFactory.create('editTypo3Image').fire('execute');

      const modal = await dialog();
      expect(infoUids[0]).toBe('1');
      expect(modal.content.querySelector('#rteckeditorimage-caption').value).toBe('Caption one');
      expect(radiosDisabled(modal)).toEqual([false, false, false]);
    });

    it('keeps the click behavior of an inline image with its own link', async () => {
      select(imageByUid(2));
      editor.ui.componentFactory.create('editTypo3Image').fire('execute');

      const modal = await dialog();
      expect(infoUids[0]).toBe('2');
      expect(radiosDisabled(modal)).toEqual([false, false, false]);
      expect(modal.content.querySelector('#clickBehavior-link').checked).toBe(true);
    });

    it('disables the click behavior of an inline image inside a text link', async () => {
      select(imageByUid(3));
      editor.ui.componentFactory.create('editTypo3Image').fire('execute');

      const modal = await dialog();
      expect(infoUids[0]).toBe('3');
      expect(radiosDisabled(modal)).toEqual([true, true, true]);
    });

    it('does nothing without a selected image', async () => {
      editor.model.change((writer) => writer.setSelection(editor.model.document.getRoot().getChild(3), 0));
      editor.ui.componentFactory.create('editTypo3Image').fire('execute');
      await new Promise((resolve) => setTimeout(resolve, 0));

      expect(infoUids).toEqual([]);
      expect(modalCalls).toHaveLength(0);
    });
  });

  describe('Double click', () => {
    const widgetOf = (uid) => editor.editing.mapper.toViewElement(imageByUid(uid));
    const childNamed = (parent, name) => Array.from(parent.getChildren()).find((child) => child.name === name);

    it('opens the dialog when the widget itself is the target', async () => {
      dblclick(widgetOf(1));

      await dialog();
      expect(infoUids[0]).toBe('1');
      expect(editor.model.document.selection.getSelectedElement()).toBe(imageByUid(1));
    });

    it('opens the dialog when the <img> in a block widget is the target', async () => {
      dblclick(childNamed(widgetOf(1), 'img'));

      await dialog();
      expect(infoUids[0]).toBe('1');
    });

    it('opens the dialog when text in the caption is the target', async () => {
      const figcaption = childNamed(widgetOf(1), 'figcaption');
      const text = Array.from(figcaption.getChildren())[0];

      dblclick(text);

      await dialog();
      expect(infoUids[0]).toBe('1');
    });

    it('opens the dialog when the figcaption itself is the target', async () => {
      dblclick(childNamed(widgetOf(1), 'figcaption'));

      await dialog();
      expect(infoUids[0]).toBe('1');
    });

    it('opens the dialog when the <img> in an inline widget is the target', async () => {
      dblclick(childNamed(widgetOf(4), 'img'));

      const modal = await dialog();
      expect(infoUids[0]).toBe('4');
      expect(radiosDisabled(modal)).toEqual([false, false, false]);
    });

    it('disables the click behavior of an inline image inside a text link', async () => {
      dblclick(childNamed(widgetOf(3), 'img'));

      const modal = await dialog();
      expect(infoUids[0]).toBe('3');
      expect(radiosDisabled(modal)).toEqual([true, true, true]);
    });

    it('opens the dialog when an <img> inside an <a> inside the widget is the target', async () => {
      // Stand-in nodes: the editing view does not nest the <img> in an <a>
      const widget = widgetOf(4);
      const link = { name: 'a', parent: widget, hasClass: () => false, is: () => false };
      const image = { name: 'img', parent: link, hasClass: () => false };

      dblclick(image);

      await dialog();
      expect(infoUids[0]).toBe('4');
    });

    it('opens the dialog when another element inside the widget is the target', async () => {
      // Stand-in node for an element the widget adds, such as a resize handle
      const inner = { name: 'div', parent: widgetOf(1), hasClass: () => false };

      dblclick(inner);

      await dialog();
      expect(infoUids[0]).toBe('1');
    });

    it('does nothing for a target outside an image widget', async () => {
      const paragraph = editor.editing.mapper.toViewElement(editor.model.document.getRoot().getChild(3));

      dblclick(paragraph);
      await new Promise((resolve) => setTimeout(resolve, 0));

      expect(infoUids).toEqual([]);
      expect(modalCalls).toHaveLength(0);
    });
  });
});

describe('Toggle inline/block command', () => {
  beforeEach(async () => {
    infoUids = [];
    stubBrowser();
    await createEditor();
  });

  afterEach(async () => {
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  function toggle(uid) {
    select(imageByUid(uid));
    editor.execute('toggleImageType');
    return imageByUid(uid);
  }

  it('turns a block image into an inline image in a paragraph', () => {
    editor.setData(`<figure class="image image-left"><a href="https://example.com/" class="image-left lnk">${img(1)}</a></figure>`);

    const image = toggle(1);

    expect(image.name).toBe('typo3imageInline');
    expect(image.parent.name).toBe('paragraph');
    expect(image.getAttribute('class')).toBe('image-inline');
    expect(image.getAttribute('imageLinkHref')).toBe('https://example.com/');
    expect(image.getAttribute('imageLinkClass')).toBe('lnk');
    expect(editor.model.document.selection.getSelectedElement()).toBe(image);
  });

  it('turns an inline image into a block image and splits the paragraph', () => {
    editor.setData(`<p>Before ${img(1, ' class="image-inline extra"')} after</p>`);

    const image = toggle(1);

    expect(image.name).toBe('typo3image');
    expect(image.getAttribute('class')).toBe('extra image-block');
    expect(Array.from(editor.model.document.getRoot().getChildren()).map((node) => node.name))
      .toEqual(['paragraph', 'typo3image', 'paragraph']);
    expect(editor.model.document.selection.getSelectedElement()).toBe(image);
  });

  it('keeps an alignment class of an inline image and drops an alignment-only link class', () => {
    editor.setData(`<p>Before <a href="https://example.com/">${img(1, ' class="image-inline image-right"')}</a> after</p>`);
    editor.model.change((writer) => writer.setAttribute('imageLinkClass', 'image-inline', imageByUid(1)));

    const image = toggle(1);

    expect(image.getAttribute('class')).toBe('image-right');
    expect(image.hasAttribute('imageLinkClass')).toBe(false);
    expect(image.getAttribute('imageLinkHref')).toBe('https://example.com/');
  });

  it('keeps the attributes of the image', () => {
    editor.setData(`<p>Before ${img(1, ' class="image-inline" title="T" data-htmlarea-zoom="true" data-quality="2"')} after</p>`);

    const image = toggle(1);

    expect(image.getAttribute('fileUid')).toBe('1');
    expect(image.getAttribute('src')).toBe('/fileadmin/1.jpg');
    expect(image.getAttribute('title')).toBe('T');
    expect(image.getAttribute('enableZoom')).toBe('true');
    expect(image.getAttribute('quality')).toBe('2');
    expect(image.hasAttribute('imageLinkHref')).toBe(false);
  });

  it('does nothing without a selected element', () => {
    editor.setData(`<p>Before ${img(1, ' class="image-inline"')} after</p>`);
    const before = editor.getData();
    editor.model.change((writer) => writer.setSelection(editor.model.document.getRoot().getChild(0), 0));

    editor.commands.get('toggleImageType').execute();

    expect(editor.getData()).toBe(before);
  });
});
