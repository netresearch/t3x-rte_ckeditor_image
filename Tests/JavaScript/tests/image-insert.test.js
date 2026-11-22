/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Inserting a new image through the "Insert image" button: the file browser
 * modal, the image dialog and the class a new image gets (#655).
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

const IMAGE_INFO = {
  uid: 5,
  url: '/fileadmin/new.jpg',
  storageDriver: 'Local',
  width: 300,
  height: 200,
  title: '',
  alt: '',
  processed: { width: 300, height: 200, url: '/fileadmin/new.jpg' },
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
let fileBrowser;

/**
 * Stand-in for the file browser modal: an iframe whose document holds file
 * list entries, as the TYPO3 element browser renders them.
 */
function openFileBrowser() {
  const modal = document.createElement('div');
  const iframe = document.createElement('iframe');
  modal.appendChild(iframe);
  document.body.appendChild(modal);
  const hiddenCalls = [];
  modal.hideModal = () => hiddenCalls.push(true);
  modalCalls[0].callback(modal);
  iframe.dispatchEvent(new Event('load'));
  return { iframe, modal, hiddenCalls };
}

function addFileListEntry(doc, type, uid) {
  const entry = doc.createElement('div');
  entry.dataset.filelistElement = '';
  entry.dataset.filelistType = type;
  entry.dataset.filelistUid = uid;
  doc.body.appendChild(entry);
  return entry;
}

describe('Insert image button', () => {
  beforeEach(async () => {
    modalCalls.length = 0;
    modalHidden.length = 0;
    vi.stubGlobal('ResizeObserver', class {
      observe() {}
      unobserve() {}
      disconnect() {}
    });
    vi.stubGlobal('fetch', vi.fn(async (url) => ({
      ok: true,
      json: async () => (String(url).includes('fileId=translations') ? { lang: {} } : structuredClone(IMAGE_INFO)),
    })));
    element = document.createElement('div');
    document.body.appendChild(element);
    editor = await ClassicEditor.create(element, {
      licenseKey: 'GPL',
      plugins: [Paragraph, Link, GeneralHtmlSupport, StyleUtils, WidgetToolbarRepository, Typo3Image],
      typo3image: { routeUrl: '/typo3/rte/wizard/selectimage?token=test', allowedExtensions: 'jpg,png' },
    });
    editor.setData('<p>Text</p>');
    editor.model.change((writer) => writer.setSelection(editor.model.document.getRoot().getChild(0), 'end'));
    editor.ui.componentFactory.create('insertimage').fire('execute');
    await vi.waitFor(() => expect(modalCalls).toHaveLength(1));
    fileBrowser = openFileBrowser();
  });

  afterEach(async () => {
    fileBrowser.modal.remove();
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  it('opens the file browser as an iframe modal', () => {
    expect(modalCalls[0].type).toBe('iframe');
    expect(modalCalls[0].content).toContain('/typo3/rte/wizard/selectimage?token=test&bparams=');
  });

  it('ignores a click on a folder entry', async () => {
    const doc = fileBrowser.iframe.contentDocument;
    addFileListEntry(doc, 'folder', '9').click();
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(fileBrowser.hiddenCalls).toHaveLength(0);
    expect(modalCalls).toHaveLength(1);
  });

  it('gives a new block image the class image-block', async () => {
    const doc = fileBrowser.iframe.contentDocument;
    addFileListEntry(doc, 'file', '5').click();

    expect(fileBrowser.hiddenCalls).toHaveLength(1);
    await vi.waitFor(() => expect(modalCalls).toHaveLength(2));
    const dialog = modalCalls[1];
    dialog.buttons.find((button) => button.btnClass === 'btn-primary').trigger();
    await vi.waitFor(() => expect(modalHidden).toHaveLength(1));
    await new Promise((resolve) => setTimeout(resolve, 0));

    const image = Array.from(editor.model.document.getRoot().getChildren()).find((node) => node.is('element', 'typo3image'));
    expect(image, 'inserted typo3image').toBeDefined();
    expect(image.getAttribute('class')).toBe('image-block');
    expect(image.getAttribute('fileUid')).toBe(5);
  });
});
