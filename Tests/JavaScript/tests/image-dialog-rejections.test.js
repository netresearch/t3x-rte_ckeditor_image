/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * The image dialog promises must not end as unhandled rejections.
 *
 * Cancelling the dialog rejects without a reason: that is a normal outcome
 * and must stay silent. A failed image info request while saving is an error:
 * it is reported and the dialog stays open so that the editor can retry.
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

const IMG = '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" data-htmlarea-file-table="sys_file" width="300" height="200" alt="Alt">';

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
let infoRequests;
let failInfoFrom;

async function createEditor() {
  vi.stubGlobal('ResizeObserver', class {
    observe() {}
    unobserve() {}
    disconnect() {}
  });
  infoRequests = 0;
  failInfoFrom = Infinity;
  vi.stubGlobal('fetch', vi.fn(async (url) => {
    if (!String(url).includes('action=info') || String(url).includes('fileId=translations')) {
      return { ok: true, json: async () => ({ lang: {} }) };
    }
    infoRequests += 1;
    if (infoRequests >= failInfoFrom) {
      return { ok: false, status: 500, json: async () => ({}) };
    }
    return { ok: true, json: async () => structuredClone(IMAGE_INFO) };
  }));
  element = document.createElement('div');
  document.body.appendChild(element);
  editor = await ClassicEditor.create(element, {
    licenseKey: 'GPL',
    plugins: [Paragraph, Link, GeneralHtmlSupport, StyleUtils, WidgetToolbarRepository, Typo3Image],
    typo3image: { routeUrl: '/typo3/rte/wizard/selectimage?token=test' },
  });
}

async function openDialog() {
  const root = editor.model.document.getRoot();
  const image = Array.from(root.getChildren()).find((node) => node.is('element', 'typo3image'));
  expect(image, 'typo3image model element').toBeDefined();
  editor.model.change((writer) => writer.setSelection(image, 'on'));

  editor.ui.componentFactory.create('editTypo3Image').fire('execute');
  await vi.waitFor(() => expect(modalCalls).toHaveLength(1));

  return modalCalls[0];
}

const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('Image dialog rejections', () => {
  let consoleError;

  beforeEach(async () => {
    modalCalls.length = 0;
    modalHidden.length = 0;
    await createEditor();
    editor.setData(`<figure class="image">${IMG}</figure>`);
    consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(async () => {
    consoleError.mockRestore();
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  it('closes silently and keeps the image when the dialog is cancelled', async () => {
    const before = editor.getData();
    const dialog = await openDialog();

    dialog.buttons.find((button) => button.btnClass === 'btn-default').trigger();
    await settle();

    expect(modalHidden).toHaveLength(1);
    expect(consoleError).not.toHaveBeenCalled();
    expect(editor.getData()).toBe(before);
  });

  it('reports a failed image info request on save and keeps the dialog open', async () => {
    const before = editor.getData();
    const dialog = await openDialog();
    failInfoFrom = infoRequests + 1;

    dialog.buttons.find((button) => button.btnClass === 'btn-primary').trigger();
    await vi.waitFor(() => expect(consoleError).toHaveBeenCalled());

    expect(consoleError.mock.calls[0][0]).toBe('Failed to fetch image info:');
    expect(consoleError.mock.calls[0][1]).toBeInstanceOf(Error);
    expect(modalHidden).toHaveLength(0);
    expect(editor.getData()).toBe(before);
  });

  it('reports a failed image info request when the dialog is opened', async () => {
    failInfoFrom = 1;
    const root = editor.model.document.getRoot();
    const image = Array.from(root.getChildren()).find((node) => node.is('element', 'typo3image'));
    editor.model.change((writer) => writer.setSelection(image, 'on'));

    editor.ui.componentFactory.create('editTypo3Image').fire('execute');
    await vi.waitFor(() => expect(consoleError).toHaveBeenCalled());

    expect(consoleError.mock.calls[0][0]).toBe('Failed to edit image:');
    expect(modalCalls).toHaveLength(0);
  });
});
