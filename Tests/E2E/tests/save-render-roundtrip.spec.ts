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
 * NOTE: Tests that require opening the image dialog and confirming are marked
 * as fixme due to a known CI issue where confirmImageDialog() clicks the
 * button but the modal does not close (PHP built-in server timing issue).
 */
test.describe('Save-Render Roundtrip', () => {
  test.beforeEach(async () => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
  });

  test('save unchanged content element — images still render on frontend', async ({ page }) => {
    // Step 1: Login and open CE 1 in backend editor
    await loginToBackend(page);
    await navigateToContentEdit(page, 1);
    await waitForCKEditor(page);

    // Step 2: Verify editor has image content before saving
    const editorHtml = await getEditorHtml(page);
    expect(editorHtml).toContain('<img');
    expect(editorHtml).toContain('data-htmlarea-file-uid');

    // Step 3: Save without changes
    await saveContentElement(page);
    // Wait for the iframe form submission to complete (save is inside iframe,
    // page.waitForLoadState only watches the main page scaffold)
    await page.waitForTimeout(2000);

    // Step 4: Navigate to frontend and verify images render
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // CE 1 has an image with alt="Example" and data-htmlarea-zoom="true"
    // After rendering, it should be inside a popup link wrapper
    const images = page.locator('img[alt="Example"]');
    const imageCount = await images.count();
    if (imageCount === 0) {
      // Diagnostic: log the page content to help debug rendering issues
      const bodyHtml = await page.locator('body').innerHTML();
      console.log('Frontend body content (first 500 chars):', bodyHtml.substring(0, 500));
    }
    expect(imageCount, 'Expected images on frontend after save').toBeGreaterThan(0);
    await expect(images.first()).toBeVisible();

    // Verify image has a valid src (not broken)
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

  test.fixme('modify alt text in dialog — change reflected on frontend', async ({ page }) => {
    // FIXME: Requires confirmImageDialog() which has a known modal-close issue in CI.
    // When the modal close issue is resolved, this test verifies the full
    // edit → save → render roundtrip for attribute changes.
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
        await altCheckbox.click();
      }
    }
    await altInput.clear();
    await altInput.fill('Roundtrip Alt Test');

    // Confirm dialog and save
    await confirmImageDialog(page);
    await saveContentElement(page);

    // Verify on frontend
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const img = page.locator('img[alt="Roundtrip Alt Test"]');
    expect(await img.count(), 'Modified alt text should appear on frontend').toBeGreaterThan(0);
  });

  test.fixme('enable zoom in dialog — popup wrapper appears on frontend', async ({ page }) => {
    // FIXME: Requires confirmImageDialog() which has a known modal-close issue in CI.
    // When fixed, this test verifies that toggling click-to-enlarge in the
    // backend produces data-popup="true" links on the frontend.
    await loginToBackend(page);
    // Use CE 14 (Standalone template — no zoom initially)
    await navigateToContentEdit(page, 14);
    await waitForCKEditor(page);

    // Open image dialog
    await openImageEditDialog(page);

    // Toggle click-to-enlarge radio button
    const enlargeRadio = page.locator('#clickBehavior-enlarge');
    await enlargeRadio.click();

    // Confirm and save
    await confirmImageDialog(page);
    await saveContentElement(page);

    // Verify popup wrapper on frontend
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    const popupLink = page.locator('a[data-popup="true"] img[alt="Template Standalone"]');
    expect(
      await popupLink.count(),
      'Image should be wrapped in popup link after enabling zoom'
    ).toBeGreaterThan(0);
  });
});
