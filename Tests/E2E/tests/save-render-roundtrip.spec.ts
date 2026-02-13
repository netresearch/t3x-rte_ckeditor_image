import { test, expect } from '@playwright/test';
import {
  BACKEND_PASSWORD,
  loginToBackend,
  navigateToContentEdit,
  waitForCKEditor,
  getEditorHtml,
  saveContentElement,
  openImageEditDialog,
  confirmImageDialog,
  gotoFrontendPage,
  requireCondition,
} from './helpers/typo3-backend';

/**
 * Save-Render Roundtrip Tests.
 *
 * Tests the critical path: save in CKEditor backend, verify rendered output
 * on the frontend. Catches "works in editor, broken on frontend" bugs.
 *
 * Test content: CE 1 has a basic image with data-htmlarea-zoom="true".
 *
 * E2E tests use Apache + PHP-FPM (not PHP built-in server) to support
 * TYPO3's URL rewriting and FAL image processing pipeline.
 */
test.describe('Save-Render Roundtrip', () => {
  test.beforeEach(() => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
  });

  test('save unchanged content element — images still render on frontend', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, 1);
    await waitForCKEditor(page);

    const editorHtml = await getEditorHtml(page);
    expect(editorHtml).toContain('<img');
    expect(editorHtml).toContain('data-htmlarea-file-uid');

    await saveContentElement(page);
    await page.waitForTimeout(2000);

    // Clear backend session before frontend navigation
    await page.context().clearCookies();
    await gotoFrontendPage(page);

    const images = page.locator('img[alt="Example"]');
    expect(await images.count(), 'Expected images on frontend after save').toBeGreaterThan(0);
    await expect(images.first()).toBeVisible();

    const src = await images.first().getAttribute('src');
    expect(src).toBeTruthy();
    expect(src).toMatch(/(fileadmin|_processed_)/);
  });

  test('editor HTML preserves key attributes after save-reload', async ({ page }) => {
    // Step 1: Login and open CE 1
    await loginToBackend(page);
    await navigateToContentEdit(page, 1);
    await waitForCKEditor(page);

    // Step 2: Capture key attributes from editor HTML
    const htmlBefore = await getEditorHtml(page);
    const hasFileUid = htmlBefore.includes('data-htmlarea-file-uid');
    const hasAlt = htmlBefore.includes('alt=');

    expect(hasFileUid, 'Editor should have data-htmlarea-file-uid attribute').toBe(true);
    expect(hasAlt, 'Editor should have alt attribute on images').toBe(true);

    // Step 3: Save
    await saveContentElement(page);
    await page.waitForTimeout(2000);

    // Step 4: Re-open the same CE
    await navigateToContentEdit(page, 1);
    await waitForCKEditor(page);

    // Step 5: Verify key attributes are preserved
    const htmlAfter = await getEditorHtml(page);
    expect(htmlAfter).toContain('data-htmlarea-file-uid');
    expect(htmlAfter).toContain('alt=');

    // The image src should still be present
    expect(htmlAfter).toContain('<img');
    expect(htmlAfter).toContain('fileadmin/user_upload/example.jpg');
  });

  test('modify alt text in dialog — change persists after save-reload', async ({ page }) => {
    // Backend-only roundtrip: confirm dialog → save → reload CE → verify editor HTML.
    // Avoids frontend navigation (PHP built-in server FAL issue).
    await loginToBackend(page);
    await navigateToContentEdit(page, 1);
    await waitForCKEditor(page);

    // Open image dialog
    await openImageEditDialog(page);

    // Change alt text (may need to enable override checkbox first)
    const altInput = page.locator('#rteckeditorimage-alt, input[name="alt"]').first();
    const isDisabled = await altInput.isDisabled().catch(() => false);
    if (isDisabled) {
      const altCheckbox = page.locator('#checkbox-alt');
      if (await altCheckbox.count() > 0) {
        // Use vanilla JS to toggle override checkbox (jQuery not on window in TYPO3 v13+)
        await page.evaluate(() => {
          const cb = document.querySelector('#checkbox-alt') as HTMLInputElement;
          const input = document.querySelector('#rteckeditorimage-alt') as HTMLInputElement;
          if (cb) {
            cb.checked = true;
            cb.dispatchEvent(new Event('change', { bubbles: true }));
          }
          if (input) {
            input.disabled = false;
          }
        });
        await expect(altInput).toBeEnabled();
      }
    }
    await altInput.clear();
    await altInput.fill('Roundtrip Alt Test');

    // Confirm dialog and save
    await confirmImageDialog(page);
    await saveContentElement(page);
    // TYPO3 backend needs time to persist and re-render the edit form
    // before navigateToContentEdit() will see the updated content
    await page.waitForTimeout(2000);

    // Re-open the same CE and verify the alt text persisted
    await navigateToContentEdit(page, 1);
    await waitForCKEditor(page);

    const editorHtml = await getEditorHtml(page);
    expect(editorHtml, 'Modified alt text should persist after save-reload').toContain('Roundtrip Alt Test');
  });

  test('enable zoom in dialog — data-htmlarea-zoom persists after save-reload', async ({ page }) => {
    // Uses CE 35 (dedicated zoom-roundtrip CE with surrounding text) instead
    // of CE 14 which renders as CKEditor block widget (dblclick can't open dialog).
    await loginToBackend(page);
    await navigateToContentEdit(page, 35);
    await waitForCKEditor(page);

    // Open image dialog
    await openImageEditDialog(page);

    // Toggle click-to-enlarge radio button
    const enlargeRadio = page.locator('#clickBehavior-enlarge');
    await enlargeRadio.click();

    // Confirm and save
    await confirmImageDialog(page);
    await saveContentElement(page);
    await page.waitForTimeout(2000);

    // Re-open the same CE and verify zoom attribute persisted
    await navigateToContentEdit(page, 35);
    await waitForCKEditor(page);

    const editorHtml = await getEditorHtml(page);
    expect(editorHtml, 'data-htmlarea-zoom should persist after save-reload').toContain('data-htmlarea-zoom');
  });
});
