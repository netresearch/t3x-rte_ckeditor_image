/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Characterization of what the image dialog writes to the image on Save:
 * click behavior (none, enlarge, link), CSS classes, quality and the
 * title/alt override checkboxes.
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
  uid: 1,
  url: '/fileadmin/test.jpg',
  storageDriver: 'Local',
  width: 300,
  height: 200,
  title: 'Default title',
  alt: 'Default alt',
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

function image() {
  const root = editor.model.document.getRoot();
  return Array.from(root.getChildren()).find((node) => node.is('element', 'typo3image'));
}

/**
 * Open the dialog of the block image, let fill() change the dialog, press
 * Save and return the attributes of the new model element.
 */
async function saveDialog(fill) {
  editor.model.change((writer) => writer.setSelection(image(), 'on'));
  editor.ui.componentFactory.create('editTypo3Image').fire('execute');
  await vi.waitFor(() => expect(modalCalls).toHaveLength(1));
  const dialog = modalCalls[0];
  fill(dialog.content);
  dialog.buttons.find((button) => button.btnClass === 'btn-primary').trigger();
  await vi.waitFor(() => expect(modalHidden).toHaveLength(1));
  await new Promise((resolve) => setTimeout(resolve, 0));
  return Object.fromEntries(image().getAttributes());
}

function choose(content, behavior) {
  for (const radio of content.querySelectorAll('input[name="clickBehavior"]')) {
    radio.checked = radio.value === behavior;
  }
}

function setValue(content, selector, value) {
  content.querySelector(selector).value = value;
}

describe('Image dialog save', () => {
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
      typo3image: { routeUrl: '/typo3/rte/wizard/selectimage?token=test' },
    });
  });

  afterEach(async () => {
    await editor.destroy();
    element.remove();
    vi.unstubAllGlobals();
  });

  const BASE = {
    alt: 'My alt',
    altOverride: true,
    fileTable: 'sys_file',
    fileUid: 1,
    height: '200',
    src: '/fileadmin/test.jpg',
    title: 'My title',
    titleOverride: true,
    width: '300',
  };

  beforeEach(() => {
    editor.setData('<figure class="image image-left"><a href="https://old.example/" class="old-link" target="_top">'
      + '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" data-htmlarea-file-table="sys_file" width="300" height="200" alt="My alt" title="My title" data-title-override="true" data-alt-override="true" data-quality="retina"></a></figure>');
  });

  it('"none" drops zoom and link and keeps only the alignment class', async () => {
    expect(await saveDialog((c) => choose(c, 'none'))).toEqual({
      ...BASE,
      class: 'image-left',
      enableZoom: false,
      noScale: false,
      quality: 'retina',
    });
  });

  it('"enlarge" sets zoom, adds the sanitized CSS class and drops the link target', async () => {
    expect(await saveDialog((c) => {
      choose(c, 'enlarge');
      setValue(c, '#input-linkCssClass-enlarge', ' zoom <b>big ');
    })).toEqual({
      ...BASE,
      class: 'image-left zoom bbig',
      enableZoom: true,
      // The link class field is not cleared in enlarge mode
      imageLinkClass: 'old-link',
      noScale: false,
      quality: 'retina',
    });
  });

  it('"link" writes the trimmed link fields and the sanitized link class', async () => {
    expect(await saveDialog((c) => {
      choose(c, 'link');
      setValue(c, '#rteckeditorimage-linkHref', ' t3://page?uid=5 ');
      setValue(c, '#rteckeditorimage-linkTarget', '');
      setValue(c, '#rteckeditorimage-linkTitle', ' New title ');
      setValue(c, '#rteckeditorimage-linkParams', '&L=1');
      setValue(c, '#input-linkCssClass', 'btn "x"');
    })).toEqual({
      ...BASE,
      class: 'image-left',
      enableZoom: false,
      imageLinkClass: 'btn x',
      imageLinkHref: 't3://page?uid=5',
      imageLinkParams: '&L=1',
      imageLinkTitle: 'New title',
      noScale: false,
      quality: 'retina',
    });
  });

  it('"link" without an href drops the empty link fields and keeps no link', async () => {
    expect(await saveDialog((c) => {
      choose(c, 'link');
      setValue(c, '#rteckeditorimage-linkHref', ' ');
      setValue(c, '#rteckeditorimage-linkTarget', '_blank');
      setValue(c, '#rteckeditorimage-linkTitle', '');
      setValue(c, '#rteckeditorimage-linkParams', '');
      setValue(c, '#input-linkCssClass', '');
    })).toEqual({
      ...BASE,
      class: 'image-left',
      enableZoom: false,
      // edit() writes every link field that is not blank, also without an href
      imageLinkTarget: '_blank',
      noScale: false,
      quality: 'retina',
    });
  });

  it('quality "none" sets noScale', async () => {
    expect(await saveDialog((c) => {
      choose(c, 'none');
      setValue(c, '#rteckeditorimage-quality', 'none');
    })).toEqual({
      ...BASE,
      class: 'image-left',
      enableZoom: false,
      noScale: true,
      quality: 'none',
    });
  });

  it('an empty quality removes quality and noScale', async () => {
    expect(await saveDialog((c) => {
      choose(c, 'none');
      setValue(c, '#rteckeditorimage-quality', '');
    })).toEqual({
      ...BASE,
      class: 'image-left',
      enableZoom: false,
      noScale: false,
      quality: '',
    });
  });

  it('unchecked override checkboxes fall back to the file metadata', async () => {
    expect(await saveDialog((c) => {
      choose(c, 'none');
      c.querySelector('#checkbox-title').checked = false;
      c.querySelector('#checkbox-alt').checked = false;
    })).toEqual({
      ...BASE,
      alt: 'Default alt',
      altOverride: false,
      class: 'image-left',
      enableZoom: false,
      noScale: false,
      quality: 'retina',
      title: 'Default title',
      titleOverride: false,
    });
  });
});
