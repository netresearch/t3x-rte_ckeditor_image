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
  openImageEditDialog,
  confirmImageDialog,
  requireCondition,
} from './helpers/typo3-backend';

/**
 * Regression test for https://github.com/netresearch/t3x-rte_ckeditor_image/issues/935
 *
 * Bug: an image's own link is held in the imageLink* model attributes, which
 * know only href, target, title, class and data-link-params. Every other
 * attribute of that <a> was lost on save, for example rel (set by the TYPO3
 * link browser when buttons.link.relAttribute is enabled) or download.
 *
 * Fix: the upcast keeps rel and download of the <a> in the
 * imageLinkAttributes model attribute, and the downcast writes them back.
 * The image dialog keeps them while the link target stays the same, and
 * the inline/block toggle carries them along. Other attributes are not kept.
 */

/** Dedicated CE (seed-content.php), saved by the inline round-trip test. */
const CE_ID = 101;

/** Dedicated CE for the block round-trip test; tests run in parallel. */
const CE_ID_BLOCK = 102;

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

/** Select the first typo3image or typo3imageInline in the document and run the toggle. */
async function toggleFirstImage(page: Page): Promise<void> {
  const frame = getModuleFrame(page);
  await frame.locator('.ck-editor__editable').first().evaluate((el) => {
    const editor = (el as any).ckeditorInstance;
    const model = editor.model;
    let image = null;
    for (const { item } of model.createRangeIn(model.document.getRoot())) {
      if (item.is('element', 'typo3image') || item.is('element', 'typo3imageInline')) {
        image = item;
        break;
      }
    }
    if (!image) {
      throw new Error('No image found in the document');
    }
    model.change((writer: any) => writer.setSelection(image, 'on'));
    editor.execute('toggleImageType');
  });
}

/** Select the first inline image and link it with CKEditor's link command (the toolbar Link button). */
async function linkFirstImageWithLinkButton(page: Page, href: string, attrs: Record<string, string> = {}): Promise<void> {
  const frame = getModuleFrame(page);
  await frame.locator('.ck-editor__editable').first().evaluate((el, { url, linkAttrs }) => {
    const editor = (el as any).ckeditorInstance;
    const model = editor.model;
    let image = null;
    for (const { item } of model.createRangeIn(model.document.getRoot())) {
      if (item.is('element', 'typo3imageInline')) {
        image = item;
        break;
      }
    }
    if (!image) {
      throw new Error('No inline image found in the document');
    }
    model.change((writer: any) => writer.setSelection(image, 'on'));
    // TYPO3's link command (typo3-link.ts) takes the link attributes as { attrs: {...} }
    editor.execute('link', url, { attrs: linkAttrs });
  }, { url: href, linkAttrs: attrs });
}

/** Count the <a> elements in a HTML string */
function countLinks(html: string): number {
  return (html.match(/<a[\s>]/g) || []).length;
}

test.describe('Regression #935: attributes of an image\'s own link', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);
  });

  test('inline image link keeps rel and download after save and reload', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a href="https://example.com/935" rel="nofollow noopener" download="file.jpg"><img class="image-inline" alt="Inline Rel" ${IMG}></a> text after.</p>`
    );

    // Soft: on a broken build, the test still reaches the save and shows the stored result.
    const before = await getCKEditorData(page);
    expect.soft(before, `editor data: ${before}`).toMatch(/<a [^>]*rel="nofollow noopener"[^>]*><img[^>]*alt="Inline Rel"/);

    await saveContentElement(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    const after = await getCKEditorData(page);
    expect(after, `stored data after reload: ${after}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/935"[^>]*><img[^>]*alt="Inline Rel"/);
    expect(after, `stored data after reload: ${after}`).toMatch(/<a [^>]*rel="nofollow noopener"[^>]*><img[^>]*alt="Inline Rel"/);
    expect(after, `stored data after reload: ${after}`).toMatch(/<a [^>]*download="file\.jpg"[^>]*><img[^>]*alt="Inline Rel"/);
    expect(countLinks(after), `stored data after reload: ${after}`).toBe(1);
  });

  test('block image link keeps rel after save and reload', async ({ page }) => {
    await navigateToContentEdit(page, CE_ID_BLOCK);
    await waitForCKEditor(page);
    await setEditorData(
      page,
      `<p>Paragraph above.</p><figure class="image image-block"><a href="https://example.com/935-block" rel="nofollow"><img alt="Block Rel" ${IMG}></a></figure><p>Paragraph below.</p>`
    );

    await saveContentElement(page);
    await navigateToContentEdit(page, CE_ID_BLOCK);
    await waitForCKEditor(page);

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/935-block"[^>]*rel="nofollow"[^>]*><img[^>]*alt="Block Rel"|<a [^>]*rel="nofollow"[^>]*href="https:\/\/example\.com\/935-block"[^>]*><img[^>]*alt="Block Rel"/);
    expect(countLinks(data), `editor data: ${data}`).toBe(1);
  });

  test('other attributes on the link are not kept', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a href="https://example.com/935-unsafe" rel="nofollow" onclick="alert(1)" style="color:red" ping="https://tracker.example/"><img class="image-inline" alt="Unsafe Attrs" ${IMG}></a> text after.</p>`
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*rel="nofollow"[^>]*><img[^>]*alt="Unsafe Attrs"/);
    expect(data, `editor data: ${data}`).not.toContain('onclick');
    expect(data, `editor data: ${data}`).not.toContain('color:red');
    expect(data, `editor data: ${data}`).not.toContain('ping=');
  });

  test('saving the image dialog keeps rel on the link', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a href="https://example.com/935-dialog" rel="nofollow"><img class="image-inline" alt="Dialog Rel" ${IMG}></a> text after.</p>`
    );

    await openImageEditDialog(page, 0);
    await confirmImageDialog(page);

    const data = await getCKEditorData(page);
    // Only the dialog save writes data-quality, so the image was rebuilt by edit()
    expect(data, `editor data: ${data}`).toMatch(/<img[^>]*data-quality="[^"]+"[^>]*alt="Dialog Rel"|<img[^>]*alt="Dialog Rel"[^>]*data-quality="[^"]+"/);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/935-dialog"[^>]*><img[^>]*alt="Dialog Rel"/);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*rel="nofollow"[^>]*><img[^>]*alt="Dialog Rel"/);
    expect(countLinks(data), `editor data: ${data}`).toBe(1);
  });

  test('saving the image dialog keeps rel when the stored href has spaces around it', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a href=" https://example.com/935-padded " rel="nofollow"><img class="image-inline" alt="Padded Href" ${IMG}></a> text after.</p>`
    );

    await openImageEditDialog(page, 0);
    await confirmImageDialog(page);

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<img[^>]*data-quality="[^"]+"[^>]*alt="Padded Href"|<img[^>]*alt="Padded Href"[^>]*data-quality="[^"]+"/);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*rel="nofollow"[^>]*><img[^>]*alt="Padded Href"/);
  });

  test('changing the link target in the image dialog drops rel and download', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a href="https://example.com/935-old" rel="nofollow" download="old.jpg"><img class="image-inline" alt="Change Href" ${IMG}></a> text after.</p>`
    );

    await openImageEditDialog(page, 0);
    await page.locator('#rteckeditorimage-linkHref').fill('https://example.com/935-new');
    await confirmImageDialog(page);

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/935-new"[^>]*><img[^>]*alt="Change Href"/);
    expect(data, `editor data: ${data}`).not.toContain('nofollow');
    expect(data, `editor data: ${data}`).not.toContain('old.jpg');
  });

  test('toggling inline to block and back keeps rel on the link', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <a href="https://example.com/935-toggle" rel="nofollow"><img class="image-inline" alt="Toggle Rel" ${IMG}></a> text after.</p>`
    );

    await toggleFirstImage(page);
    const block = await getCKEditorData(page);
    expect(block, `editor data after inline→block: ${block}`).toMatch(/<figure[^>]*>\s*<a [^>]*rel="nofollow"[^>]*><img[^>]*alt="Toggle Rel"/);

    await toggleFirstImage(page);
    const inline = await getCKEditorData(page);
    expect(inline, `editor data after block→inline: ${inline}`).toMatch(/<a [^>]*rel="nofollow"[^>]*><img[^>]*class="image-inline"[^>]*alt="Toggle Rel"/);
    expect(countLinks(inline), `editor data after block→inline: ${inline}`).toBe(1);
  });

  test('link set with the Link button on an inline image survives the image dialog', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <img class="image-inline" alt="Link Button Dialog" ${IMG}> text after.</p>`
    );
    await linkFirstImageWithLinkButton(page, 'https://example.com/935-button-dialog');
    const linked = await getCKEditorData(page);
    expect.soft(linked, `editor data after Link button: ${linked}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/935-button-dialog"[^>]*><img[^>]*alt="Link Button Dialog"/);
    expect.soft(linked, `editor data after Link button: ${linked}`).not.toContain('data-quality=');

    await openImageEditDialog(page, 0);
    await confirmImageDialog(page);

    const data = await getCKEditorData(page);
    // Only the dialog save writes data-quality, so the image was rebuilt by edit()
    expect(data, `editor data: ${data}`).toMatch(/<img[^>]*data-quality="[^"]+"[^>]*alt="Link Button Dialog"|<img[^>]*alt="Link Button Dialog"[^>]*data-quality="[^"]+"/);
    expect(data, `editor data: ${data}`).toMatch(/<a [^>]*href="https:\/\/example\.com\/935-button-dialog"[^>]*><img[^>]*alt="Link Button Dialog"/);
    expect(countLinks(data), `editor data: ${data}`).toBe(1);
  });

  test('link set with the Link button on an inline image survives the toggle to block', async ({ page }) => {
    await setEditorData(
      page,
      `<p>Text before <img class="image-inline" alt="Link Button Toggle" ${IMG}> text after.</p>`
    );
    // linkRel and linkClass are the keys of TYPO3's link command (typo3-link.ts)
    await linkFirstImageWithLinkButton(page, 'https://example.com/935-button-toggle', { linkRel: 'nofollow', linkClass: 'button-link' });

    await toggleFirstImage(page);

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(/<figure[^>]*>\s*<a [^>]*href="https:\/\/example\.com\/935-button-toggle"[^>]*><img[^>]*alt="Link Button Toggle"/);
    expect(data, `editor data: ${data}`).not.toContain('image-inline');
    expect(data, `editor data: ${data}`).toMatch(/<figure[^>]*>\s*<a [^>]*rel="nofollow"[^>]*><img[^>]*alt="Link Button Toggle"/);
    expect(data, `editor data: ${data}`).toMatch(/<figure[^>]*>\s*<a [^>]*class="button-link"[^>]*><img[^>]*alt="Link Button Toggle"/);
    expect(countLinks(data), `editor data: ${data}`).toBe(1);
  });
});
