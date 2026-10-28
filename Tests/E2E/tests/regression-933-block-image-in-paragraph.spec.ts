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
 * Regression test for https://github.com/netresearch/t3x-rte_ckeditor_image/issues/933
 *
 * Bug: the typo3image schema allowed the block image in '$block', so in a
 * paragraph. With the cursor at the end of "Some text", insertObject() put
 * the image into the paragraph and the editor emitted
 * <p>Some text<figure>…</figure></p>. TYPO3's RteHtmlParser moves the figure
 * out of the paragraph on save and drops the paragraph text.
 *
 * Fix: typo3image is no longer allowed in '$block'. insertObject() and the
 * upcast split the paragraph. The inline→block toggle uses insertObject() too,
 * because its raw writer.insert() bypassed the schema. A legacy class-less
 * <img> between text renders inline in the frontend, so the upcast keeps it
 * in its paragraph as typo3imageInline instead of splitting the paragraph.
 *
 * The tests drive the editor through its API (editor.model / editor.setData),
 * so they do not depend on the file browser. The insert test uses the same
 * model.insertObject() call as the image dialog in typo3image.js.
 */

/** Dedicated CE (seed-content.php), saved by the insert test. */
const CE_ID = 100;

/** A <figure> or block <img> that sits inside a <p> */
const BLOCK_IMAGE_IN_PARAGRAPH = /<p>(?:(?!<\/p>).)*<(?:figure|img(?![^>]*image-inline))/s;

async function insertBlockImageAtEndOfFirstParagraph(page: Page): Promise<void> {
  const frame = getModuleFrame(page);
  await frame.locator('.ck-editor__editable').first().evaluate((el) => {
    const editor = (el as any).ckeditorInstance;
    if (!editor) {
      throw new Error('CKEditor instance not found on .ck-editor__editable');
    }
    const model = editor.model;
    model.change((writer: any) => {
      const paragraph = model.document.getRoot().getChild(0);
      writer.setSelection(paragraph, 'end');
      const image = writer.createElement('typo3image', {
        fileUid: '1',
        fileTable: 'sys_file',
        src: '/fileadmin/user_upload/example.jpg',
        width: '200',
        height: '150',
        alt: 'Issue 933',
        class: 'image-block',
      });
      model.insertObject(image);
    });
  });
}

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

test.describe('Regression #933: block image inside a text paragraph', () => {
  test.beforeEach(() => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
  });

  test('image inserted at the end of a paragraph keeps the paragraph text after save', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    await insertBlockImageAtEndOfFirstParagraph(page);

    // Soft: on a broken build, the test still reaches the save and shows the stored result.
    const before = await getCKEditorData(page);
    expect.soft(before, `editor data: ${before}`).toContain('<p>Some text</p>');
    expect.soft(before, `editor data: ${before}`).not.toMatch(BLOCK_IMAGE_IN_PARAGRAPH);
    expect.soft(before).toContain('alt="Issue 933"');

    await saveContentElement(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    const after = await getCKEditorData(page);
    expect(after, `stored data after reload: ${after}`).toContain('<p>Some text</p>');
    expect(after).toContain('alt="Issue 933"');
  });

  test('legacy class-less image between text stays in its paragraph as inline image', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    await setEditorData(
      page,
      '<p>Text before <img src="/fileadmin/user_upload/example.jpg" alt="Legacy Classless" width="200" height="150" data-htmlarea-file-uid="1"> text after.</p>'
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(
      /<p>Text before(?: |&nbsp;)<img[^>]*class="image-inline"[^>]*alt="Legacy Classless"[^>]*>(?: |&nbsp;)text after\.<\/p>/
    );
  });

  test('legacy linked class-less image between text stays in its paragraph as inline image', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    await setEditorData(
      page,
      '<p>Text before <a href="https://example.com/933"><img src="/fileadmin/user_upload/example.jpg" alt="Legacy Linked" width="200" height="150" data-htmlarea-file-uid="1"></a> text after.</p>'
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(
      /<p>Text before(?: |&nbsp;)<a [^>]*href="https:\/\/example\.com\/933"[^>]*><img[^>]*class="image-inline"[^>]*alt="Legacy Linked"[^>]*><\/a>(?: |&nbsp;)text after\.<\/p>/
    );
  });

  test('legacy class-less image in an inline wrapper between text stays in its paragraph', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    await setEditorData(
      page,
      '<p>Text before <strong><img src="/fileadmin/user_upload/example.jpg" alt="Legacy Wrapped" width="200" height="150" data-htmlarea-file-uid="1"></strong> text after.</p>'
    );

    const data = await getCKEditorData(page);
    // One paragraph that holds both text parts and the image as inline image
    expect(data, `editor data: ${data}`).toMatch(
      /^<p>Text before(?: |&nbsp;)(?:<strong>)?<img[^>]*class="image-inline"[^>]*alt="Legacy Wrapped"[^>]*>(?:<\/strong>)?(?: |&nbsp;)text after\.<\/p>$/
    );
  });

  test('legacy class-less image alone in an inline wrapper stays a block image', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    await setEditorData(
      page,
      '<p>Paragraph above.</p><p><strong><img src="/fileadmin/user_upload/example.jpg" alt="Wrapped Alone" width="200" height="150" data-htmlarea-file-uid="1"></strong></p><p>Paragraph below.</p>'
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toContain('<p>Paragraph above.</p>');
    expect(data, `editor data: ${data}`).toContain('<p>Paragraph below.</p>');
    expect(data, `editor data: ${data}`).not.toMatch(/<img[^>]*class="image-inline"[^>]*alt="Wrapped Alone"/);
  });

  test('legacy aligned image between text splits the paragraph and keeps both text parts', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    await setEditorData(
      page,
      '<p>Text before <img class="image-left" src="/fileadmin/user_upload/example.jpg" alt="Legacy Left" width="200" height="150" data-htmlarea-file-uid="1"> text after.</p>'
    );

    const data = await getCKEditorData(page);
    // CKEditor keeps the space at the split point as &nbsp;
    expect(data, `editor data: ${data}`).toMatch(/<p>Text before(?:&nbsp;| )?<\/p>/);
    expect(data, `editor data: ${data}`).toMatch(/<p>(?:&nbsp;| )?text after\.<\/p>/);
    expect(data).toContain('alt="Legacy Left"');
    expect(data, `editor data: ${data}`).not.toMatch(BLOCK_IMAGE_IN_PARAGRAPH);
  });

  test('inline image stays inside its paragraph', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    await setEditorData(
      page,
      '<p>Text before <img class="image-inline" src="/fileadmin/user_upload/example.jpg" alt="Inline Stays" width="50" height="38" data-htmlarea-file-uid="1"> text after.</p>'
    );

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toMatch(
      /<p>Text before(?: |&nbsp;)<img[^>]*alt="Inline Stays"[^>]*>(?: |&nbsp;)text after\.<\/p>/
    );
  });

  test('toggling an inline image to block splits the paragraph and keeps the text', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    await setEditorData(
      page,
      '<p>Text before <img class="image-inline" src="/fileadmin/user_upload/example.jpg" alt="Toggle Me" width="50" height="38" data-htmlarea-file-uid="1"> text after.</p>'
    );

    const frame = getModuleFrame(page);
    await frame.locator('.ck-editor__editable').first().evaluate((el) => {
      const editor = (el as any).ckeditorInstance;
      const model = editor.model;
      const paragraph = model.document.getRoot().getChild(0);
      let inlineImage = null;
      for (const child of paragraph.getChildren()) {
        if (child.is('element', 'typo3imageInline')) {
          inlineImage = child;
        }
      }
      if (!inlineImage) {
        throw new Error('typo3imageInline not found in the first paragraph');
      }
      model.change((writer: any) => writer.setSelection(inlineImage, 'on'));
      editor.execute('toggleImageType');
    });

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toContain('Text before');
    expect(data, `editor data: ${data}`).toContain('text after.');
    expect(data).toContain('alt="Toggle Me"');
    expect(data, `editor data: ${data}`).not.toContain('image-inline');
    expect(data, `editor data: ${data}`).not.toMatch(BLOCK_IMAGE_IN_PARAGRAPH);
  });

  test('toggling a block image at the root to inline puts it into a paragraph', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    await setEditorData(
      page,
      '<p>Paragraph above.</p>'
      + '<figure class="image image-block"><img src="/fileadmin/user_upload/example.jpg" alt="Block To Inline" width="200" height="150" data-htmlarea-file-uid="1"></figure>'
      + '<p>Paragraph below.</p>'
    );

    const frame = getModuleFrame(page);
    await frame.locator('.ck-editor__editable').first().evaluate((el) => {
      const editor = (el as any).ckeditorInstance;
      const model = editor.model;
      let blockImage = null;
      for (const child of model.document.getRoot().getChildren()) {
        if (child.is('element', 'typo3image')) {
          blockImage = child;
        }
      }
      if (!blockImage) {
        throw new Error('typo3image not found at the root');
      }
      model.change((writer: any) => writer.setSelection(blockImage, 'on'));
      editor.execute('toggleImageType');
    });

    const data = await getCKEditorData(page);
    expect(data, `editor data: ${data}`).toContain('<p>Paragraph above.</p>');
    expect(data, `editor data: ${data}`).toContain('<p>Paragraph below.</p>');
    // An inline image is not allowed at the root, so the toggle wraps it in a paragraph
    expect(data, `editor data: ${data}`).toMatch(/<p><img[^>]*class="image-inline"[^>]*alt="Block To Inline"[^>]*><\/p>/);
  });
});
