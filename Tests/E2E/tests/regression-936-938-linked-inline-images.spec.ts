/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test, expect, Page } from '@playwright/test';
import {
  BACKEND_PASSWORD,
  loginToBackend,
  navigateToContentEdit,
  waitForCKEditor,
  getModuleFrame,
  getCKEditorData,
  saveContentElement,
  requireCondition,
} from './helpers/typo3-backend';

/**
 * Regression tests for two bugs of images inside links.
 *
 * https://github.com/netresearch/t3x-rte_ckeditor_image/issues/936
 * Bug: a class-less image inside a link that also wraps text was removed when
 * the content was loaded. No upcast converter took the <img>.
 * The same held for an image with a class such as image-block, and for two
 * images in one link.
 * Fix: the standalone upcast creates a typo3imageInline for an image in a
 * link that holds more than that image, without block style classes. The
 * link plugin keeps the <a> around the text and the image.
 *
 * https://github.com/netresearch/t3x-rte_ckeditor_image/issues/938
 * Bug: the <a> of a linked inline image got class="image-inline". The class
 * converter of typo3imageInline also ran in the data downcast, where the view
 * element of a linked inline image is the <a>.
 * Fix: that converter runs in the editing downcast only. The data downcast
 * puts the class on the <img>.
 * Content saved with that bug keeps class="image-inline" on the <a>. The
 * upcasts of a linked image remove the alignment classes from the link class,
 * so the next save drops it.
 */

/** Dedicated CE (seed-content.php), saved by the #936 round-trip test. */
const CE_ID_TEXT_LINK = 103;

/** Dedicated CE for the #938 round-trip test; tests run in parallel. */
const CE_ID_LINKED_INLINE = 104;

const IMG = 'src="/fileadmin/user_upload/example.jpg" width="50" height="38" data-htmlarea-file-uid="1"';

async function setEditorData(page: Page, html: string): Promise<void> {
  const frame = getModuleFrame(page);
  await frame.locator('.ck-editor__editable').first().evaluate((el, data) => {
    const editor = (el as any).ckeditorInstance;
    if (!editor) {
      throw new Error('CKEditor instance not found on .ck-editor__editable');
    }
    editor.setData(data);
  }, html);
}

/** Count the <a> elements in a HTML string */
function countLinks(html: string): number {
  return (html.match(/<a[\s>]/g) || []).length;
}

/** The class attributes of all <a> elements in a HTML string */
function linkClasses(html: string): string[] {
  return [...html.matchAll(/<a\s[^>]*>/g)].map((m) => (m[0].match(/\sclass="([^"]*)"/) || [])[1] ?? '');
}

/** The <a> with the text and the image of the #936 input */
const TEXT_LINK_WITH_IMAGE = /<a [^>]*href="https:\/\/example\.com\/936"[^>]*>[^<]*Read[^<]*<img[^>]*alt="In Text Link"[^>]*>[^<]*more<\/a>/;

test.describe('Regression #936 and #938: images inside links', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID_TEXT_LINK);
    await waitForCKEditor(page);
  });

  test('#936: class-less image in a link with text stays in the link', async ({ page }) => {
    await setEditorData(
      page,
      `<p><a href="https://example.com/936">Read <img alt="In Text Link" ${IMG}> more</a></p>`
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(TEXT_LINK_WITH_IMAGE);
    expect(data, `editor data: ${data}`).toMatch(/<img[^>]*class="image-inline"[^>]*alt="In Text Link"|<img[^>]*alt="In Text Link"[^>]*class="image-inline"/);
    expect(countLinks(data), `editor data: ${data}`).toBe(1);
  });

  test('#936: class-less image in a link with text survives save and reload', async ({ page }) => {
    await setEditorData(
      page,
      `<p><a href="https://example.com/936">Read <img alt="In Text Link" ${IMG}> more</a></p>`
    );

    await saveContentElement(page);
    await navigateToContentEdit(page, CE_ID_TEXT_LINK);
    await waitForCKEditor(page);

    const data = await getCKEditorData(page);
    expect(data, `stored data after reload: ${data}`).toMatch(TEXT_LINK_WITH_IMAGE);
    expect(countLinks(data), `stored data after reload: ${data}`).toBe(1);
  });

  test('#936: class-less image in a link with text inside <strong> stays in the link', async ({ page }) => {
    await setEditorData(
      page,
      `<p><strong><a href="https://example.com/936">Read <img alt="In Text Link" ${IMG}> more</a></strong></p>`
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<img[^>]*alt="In Text Link"/);
    // CKEditor moves the <strong> inside the <a>
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/936"[^>]*>(<strong>)?Read <img[^>]*alt="In Text Link"[^>]*> more(<\/strong>)?<\/a>/);
    expect(countLinks(data), `editor data: ${data}`).toBe(1);
  });

  test('#936: image with a block style class in a link with text becomes an inline image in the link', async ({ page }) => {
    await setEditorData(
      page,
      `<p><a href="https://example.com/936">Read <img class="my-frame image-block" alt="In Text Link" ${IMG}> more</a></p>`
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(TEXT_LINK_WITH_IMAGE);
    expect(data, `editor data: ${data}`).toMatch(/<img[^>]*class="my-frame image-inline"[^>]*alt="In Text Link"/);
    expect(countLinks(data), `editor data: ${data}`).toBe(1);

    // The model holds the class too; the data downcast would add image-inline on its own.
    // The block style class is removed, as the inline/block toggle does.
    const modelClass = await getModuleFrame(page).locator('.ck-editor__editable').first().evaluate((el) => {
      const model = (el as any).ckeditorInstance.model;
      for (const { item } of model.createRangeIn(model.document.getRoot())) {
        if (item.is('element', 'typo3imageInline')) {
          return item.getAttribute('class');
        }
      }
      return null;
    });
    expect(modelClass).toBe('my-frame image-inline');
  });

  test('#936: two images in one link both stay in the link', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a href="https://example.com/936"><img alt="First" ${IMG}><img alt="Second" ${IMG}></a> text after.</p>`
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/936"[^>]*><img[^>]*alt="First"[^>]*><img[^>]*alt="Second"[^>]*><\/a>/);
    expect(countLinks(data), `editor data: ${data}`).toBe(1);
  });

  test('#938: the <a> of a linked inline image carries no image-inline class', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a href="https://example.com/938"><img class="image-inline" alt="Linked Inline" ${IMG}></a> text after.</p>`
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/938"[^>]*><img[^>]*class="image-inline"[^>]*alt="Linked Inline"/);
    expect(linkClasses(data), `editor data: ${data}`).toEqual(['']);
  });

  test('#938: the class of the image link stays as set, without image-inline', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a class="button-link" href="https://example.com/938"><img class="image-inline" alt="Linked Inline" ${IMG}></a> text after.</p>`
    );

    const data = await getCKEditorData(page);
    expect(linkClasses(data), `editor data: ${data}`).toEqual(['button-link']);
  });

  test('#938: stored content with image-inline on the <a> loses that class on load', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a class="image-inline" href="https://example.com/938"><img class="image-inline" alt="Linked Inline" ${IMG}></a> text after.</p>`
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/938"[^>]*><img[^>]*class="image-inline"[^>]*alt="Linked Inline"/);
    expect(linkClasses(data), `editor data: ${data}`).toEqual(['']);
  });

  test('#938: stored content keeps the own class of the <a> and loses image-inline on load', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a class="button-link image-inline" href="https://example.com/938"><img class="image-inline" alt="Linked Inline" ${IMG}></a> text after.</p>`
    );

    const data = await getCKEditorData(page);
    expect(linkClasses(data), `editor data: ${data}`).toEqual(['button-link']);
  });

  test('#938: the <a> of a linked image in a figure loses alignment classes on load', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before.</p><figure class="image"><a class="button-link image-block" href="https://example.com/938"><img alt="Linked Block" ${IMG}></a></figure><p>Text after.</p>`
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/938"[^>]*><img[^>]*alt="Linked Block"/);
    expect(linkClasses(data), `editor data: ${data}`).toEqual(['button-link']);
  });

  test('#938: the <a> of a linked inline image carries no image-inline class after save and reload', async ({ page }) => {
    await navigateToContentEdit(page, CE_ID_LINKED_INLINE);
    await waitForCKEditor(page);
    await setEditorData(
      page,
      `<p>Text before <a href="https://example.com/938"><img class="image-inline" alt="Linked Inline" ${IMG}></a> text after.</p>`
    );

    await saveContentElement(page);
    await navigateToContentEdit(page, CE_ID_LINKED_INLINE);
    await waitForCKEditor(page);

    const data = await getCKEditorData(page);
    expect(data, `stored data after reload: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/938"[^>]*><img[^>]*alt="Linked Inline"/);
    expect(linkClasses(data), `stored data after reload: ${data}`).toEqual(['']);
  });
});
