/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Saving the image dialog must not flatten a formatted caption.
 *
 * The dialog shows the caption as plain text in a textarea. When that text is
 * not changed, the caption content of the model (soft breaks, bold, links) is
 * kept. When it is changed, the caption is replaced by the new plain text.
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
import { calls as modalCalls, hidden as modalHidden } from '../mocks/typo3-backend-modal.js';
import Typo3Image from '../../../Resources/Public/JavaScript/Plugins/typo3image.js';

const IMG = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" data-htmlarea-file-table="sys_file" width="300" height="200" alt="Alt">';
const CAPTION = 'Line1<br>Line2 <strong>bold</strong> by <a href="https://example.com/author">Author</a>';

const IMAGE_INFO = {
  uid: 1,
  url: '/fileadmin/test.jpg',
  storageDriver: 'Local',
  width: 300,
  height: 200,
  title: '',
  alt: '',
  processed: { width: 300, height: 200, url: '/fileadmin/test.jpg' },
  lang: {
    override: 'Override %s',
    overrideNoDefault: 'Override',
    noDefaultMetadata: 'No default %s',
    imageProperties: 'Image properties',
    cancel: 'Cancel',
    save: 'Save',
  },
};

let editor;
let element;

async function createEditor() {
  vi.stubGlobal('ResizeObserver', class {
    observe() {}
    unobserve() {}
    disconnect() {}
  });
  vi.stubGlobal('fetch', vi.fn(async (url) => ({
    ok: true,
    json: async () => (String(url).includes('action=info') ? structuredClone(IMAGE_INFO) : { lang: {} }),
  })));
  element = document.createElement('div');
  document.body.appendChild(element);
  editor = await ClassicEditor.create(element, {
    licenseKey: 'GPL',
    plugins: [Paragraph, Bold, Italic, ShiftEnter, Link, GeneralHtmlSupport, StyleUtils, WidgetToolbarRepository, Typo3Image],
    typo3image: { routeUrl: '/typo3/rte/wizard/selectimage?token=test' },
  });
}

function figcaptionsOf(html) {
  return html.match(/<figcaption>.*?<\/figcaption>/g) || [];
}

/**
 * Open the image dialog through the "Edit image" button, optionally type a
 * new caption into its textarea, press Save and wait until the dialog closed
 * and the model was updated.
 */
async function editCaptionInDialog(newCaption) {
  const root = editor.model.document.getRoot();
  const image = Array.from(root.getChildren()).find((node) => node.is('element', 'typo3image'));
  expect(image, 'typo3image model element').toBeDefined();
  editor.model.change((writer) => writer.setSelection(image, 'on'));

  editor.ui.componentFactory.create('editTypo3Image').fire('execute');
  await vi.waitFor(() => expect(modalCalls).toHaveLength(1));

  const dialog = modalCalls[0];
  const textarea = dialog.content.querySelector('#rteckeditorimage-caption');
  expect(textarea, 'caption textarea').not.toBeNull();
  if (newCaption !== undefined) {
    textarea.value = newCaption;
  }

  const save = dialog.buttons.find((button) => button.btnClass === 'btn-primary');
  save.trigger();
  await vi.waitFor(() => expect(modalHidden).toHaveLength(1));
  // The model change runs in the promise callback after hideModal()
  await new Promise((resolve) => setTimeout(resolve, 0));

  return textarea;
}

describe('Image dialog keeps caption markup', () => {
  beforeEach(async () => {
    modalCalls.length = 0;
    modalHidden.length = 0;
    await createEditor();
    editor.setData(`<figure class="image">${IMG}<figcaption>${CAPTION}</figcaption></figure>`);
  });

  afterEach(async () => {
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  it('shows the caption text in the dialog', async () => {
    const textarea = await editCaptionInDialog();

    expect(textarea.value).toBe('Line1Line2 bold by Author');
  });

  it('keeps <br>, <strong> and <a> when the caption is not changed in the dialog', async () => {
    await editCaptionInDialog();

    expect(figcaptionsOf(editor.getData())).toEqual([`<figcaption>${CAPTION}</figcaption>`]);
  });

  it('keeps the caption when only surrounding whitespace is added in the dialog', async () => {
    await editCaptionInDialog('  Line1Line2 bold by Author  ');

    expect(figcaptionsOf(editor.getData())).toEqual([`<figcaption>${CAPTION}</figcaption>`]);
  });

  it('replaces the caption with the plain text when it is changed in the dialog', async () => {
    await editCaptionInDialog('New caption');

    expect(figcaptionsOf(editor.getData())).toEqual(['<figcaption>New caption</figcaption>']);
  });

  it('removes the caption when it is cleared in the dialog', async () => {
    await editCaptionInDialog('');

    expect(figcaptionsOf(editor.getData())).toEqual([]);
  });
});
