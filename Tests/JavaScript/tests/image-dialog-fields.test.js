/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Characterization of the interactive fields of the image dialog: the
 * width/height constraints, the title/alt override checkboxes, the
 * preselected quality and the link browser button.
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

let editor;
let element;
let imageUrl;
let linkBrowserResponse;

function imageInfo() {
  return {
    uid: 1,
    url: imageUrl,
    storageDriver: 'Local',
    width: 300,
    height: 200,
    title: 'Default title',
    alt: 'Default alt',
    processed: { width: 300, height: 200, url: imageUrl },
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

async function openDialog(imgAttributes) {
  editor.setData(`<figure class="image"><img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" data-htmlarea-file-table="sys_file" width="300" height="200" ${imgAttributes}></figure>`);
  const image = Array.from(editor.model.document.getRoot().getChildren()).find((node) => node.is('element', 'typo3image'));
  editor.model.change((writer) => writer.setSelection(image, 'on'));
  editor.ui.componentFactory.create('editTypo3Image').fire('execute');
  await vi.waitFor(() => expect(modalCalls).toHaveLength(1));
  return modalCalls[0].content;
}

function fire(el, type, init = {}) {
  el.dispatchEvent(Object.assign(new Event(type, { cancelable: true }), init));
}

describe('Image dialog fields', () => {
  beforeEach(async () => {
    modalCalls.length = 0;
    modalHidden.length = 0;
    imageUrl = '/fileadmin/test.jpg';
    linkBrowserResponse = { error: 'No access to the link browser' };
    vi.stubGlobal('ResizeObserver', class {
      observe() {}
      unobserve() {}
      disconnect() {}
    });
    vi.stubGlobal('fetch', vi.fn(async (url) => {
      const params = new URL(String(url), 'http://localhost').searchParams;
      if (params.get('action') === 'linkBrowser') {
        return { ok: true, json: async () => linkBrowserResponse };
      }
      if (params.get('fileId') === 'translations') {
        return { ok: true, json: async () => ({ lang: {} }) };
      }
      return { ok: true, json: async () => imageInfo() };
    }));
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
    vi.restoreAllMocks();
  });

  describe('width and height', () => {
    it('keeps the aspect ratio while typing', async () => {
      const content = await openDialog('');
      const width = content.querySelector('#rteckeditorimage-width');
      const height = content.querySelector('#rteckeditorimage-height');

      width.value = '150';
      fire(width, 'input');

      expect(width.value).toBe('150');
      expect(height.value).toBe('100');
    });

    it('allows an empty value while typing', async () => {
      const content = await openDialog('');
      const width = content.querySelector('#rteckeditorimage-width');
      const height = content.querySelector('#rteckeditorimage-height');

      width.value = '';
      fire(width, 'input');

      expect(width.value).toBe('');
      expect(height.value).toBe('200');
    });

    it('limits a changed value to the image size', async () => {
      const content = await openDialog('');
      const width = content.querySelector('#rteckeditorimage-width');
      const height = content.querySelector('#rteckeditorimage-height');

      width.value = '9999';
      fire(width, 'change');

      expect(width.value).toBe('300');
      expect(height.value).toBe('200');
    });

    it('steps the value with the mouse wheel', async () => {
      const content = await openDialog('');
      const height = content.querySelector('#rteckeditorimage-height');
      const width = content.querySelector('#rteckeditorimage-width');
      height.value = '100';

      fire(height, 'wheel', { deltaY: -1 });
      expect(height.value).toBe('101');
      expect(width.value).toBe('152');

      fire(height, 'wheel', { deltaY: 1 });
      expect(height.value).toBe('100');
    });
  });

  describe('override checkboxes', () => {
    it('starts without override when data-title-override is "false"', async () => {
      const content = await openDialog('title="Own" data-title-override="false"');
      const title = content.querySelector('#rteckeditorimage-title');

      expect(content.querySelector('#checkbox-title').checked).toBe(false);
      expect(title.disabled).toBe(true);
      expect(title.value).toBe('');
    });

    it('clears the field when the override is turned off and restores it when turned on', async () => {
      const content = await openDialog('alt="Own alt" data-alt-override="true"');
      const alt = content.querySelector('#rteckeditorimage-alt');
      const checkbox = content.querySelector('#checkbox-alt');
      const label = content.querySelector('label[for="checkbox-alt"]');

      checkbox.checked = false;
      fire(label, 'click');
      expect(alt.disabled).toBe(true);
      expect(alt.value).toBe('');

      checkbox.checked = true;
      fire(label, 'click');
      expect(alt.disabled).toBe(false);
      expect(alt.value).toBe('Own alt');
    });
  });

  describe('quality', () => {
    it('preselects "none" for an image with data-noscale', async () => {
      const content = await openDialog('data-noscale="true"');

      expect(content.querySelector('#rteckeditorimage-quality').value).toBe('none');
    });

    it('preselects "print" and disables the dropdown for an SVG image', async () => {
      imageUrl = '/fileadmin/logo.svg';
      const content = await openDialog('');
      const quality = content.querySelector('#rteckeditorimage-quality');

      expect(quality.value).toBe('print');
      expect(quality.disabled).toBe(true);
    });

    it('preselects "retina" by default', async () => {
      const content = await openDialog('');

      expect(content.querySelector('#rteckeditorimage-quality').value).toBe('retina');
    });
  });

  describe('link browser button', () => {
    it('reports a link browser error and keeps the link fields', async () => {
      const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
      vi.spyOn(console, 'error').mockImplementation(() => {});
      const content = await openDialog('');
      const href = content.querySelector('#rteckeditorimage-linkHref');
      href.value = 'https://kept.example/';

      content.querySelector('#linkFields button').click();
      await vi.waitFor(() => expect(warn).toHaveBeenCalled());

      expect(warn).toHaveBeenCalledWith('Link browser returned no link:', 'No access to the link browser');
      expect(href.value).toBe('https://kept.example/');
    });
  });
});
