import { test, expect } from '@playwright/test';
import { loginToBackend, navigateToContentEdit, waitForCKEditor, openImageEditDialog, confirmImageDialog, getEditorHtml, getCKEditorData, requireCondition, BACKEND_PASSWORD } from './helpers/typo3-backend';

/**
 * E2E tests for the click behavior radio buttons in the CKEditor image dialog.
 *
 * Tests verify that:
 * 1. All three radio buttons (None/Enlarge/Link) exist in the dialog
 * 2. Default selection matches the image's current state
 * 3. Selecting Link shows link fields, selecting None/Enlarge hides them
 * 4. Changes are applied to the editor HTML on confirm
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/617
 */

/** Content element IDs used in tests */
const CE_WITH_ZOOM = 30;      // Isolated CE with data-htmlarea-zoom="true" (Enlarge selected)
const CE_STANDALONE = 29;     // Isolated standalone CE, no zoom, no link (None selected)

/** Radio button selectors */
const RADIO_NONE = '#clickBehavior-none';
const RADIO_ENLARGE = '#clickBehavior-enlarge';
const RADIO_LINK = '#clickBehavior-link';

/** Link field selectors (visible when "Link" is selected) */
const LINK_HREF = '#rteckeditorimage-linkHref';
const LINK_TARGET = '#rteckeditorimage-linkTarget';
const LINK_TITLE = '#rteckeditorimage-linkTitle';
const LINK_CLASS = '#input-linkCssClass';

test.describe('Image Dialog - Click Behavior', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
    await loginToBackend(page);
  });

  test('radio buttons exist in dialog', async ({ page }) => {
    await navigateToContentEdit(page, CE_WITH_ZOOM);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // All three radio buttons must be present
    await expect(page.locator(RADIO_NONE)).toBeAttached();
    await expect(page.locator(RADIO_ENLARGE)).toBeAttached();
    await expect(page.locator(RADIO_LINK)).toBeAttached();

    console.log('All three click behavior radio buttons found in dialog');
  });

  test('CE with zoom has Enlarge selected by default', async ({ page }) => {
    await navigateToContentEdit(page, CE_WITH_ZOOM);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // CE 30 has data-htmlarea-zoom="true", so Enlarge should be checked
    await expect(page.locator(RADIO_ENLARGE)).toBeChecked();
    await expect(page.locator(RADIO_NONE)).not.toBeChecked();
    await expect(page.locator(RADIO_LINK)).not.toBeChecked();

    console.log('CE 30: Enlarge radio is correctly selected by default');
  });

  test('standalone CE has None selected by default', async ({ page }) => {
    await navigateToContentEdit(page, CE_STANDALONE);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // CE 29 is a standalone image — None should be checked
    await expect(page.locator(RADIO_NONE)).toBeChecked();
    await expect(page.locator(RADIO_ENLARGE)).not.toBeChecked();
    await expect(page.locator(RADIO_LINK)).not.toBeChecked();

    console.log('CE 29: None radio is correctly selected by default');
  });

  test('selecting Link shows link fields', async ({ page }) => {
    await navigateToContentEdit(page, CE_STANDALONE);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // Click the Link radio button
    await page.locator(RADIO_LINK).click();
    await page.waitForTimeout(500);

    // All link fields should now be visible
    await expect(page.locator(LINK_HREF)).toBeVisible();
    await expect(page.locator(LINK_TARGET)).toBeVisible();
    await expect(page.locator(LINK_TITLE)).toBeVisible();
    await expect(page.locator(LINK_CLASS)).toBeVisible();

    console.log('Link fields are visible after selecting Link radio');
  });

  test('selecting None hides all link fields', async ({ page }) => {
    await navigateToContentEdit(page, CE_STANDALONE);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // First select Link to make fields visible
    await page.locator(RADIO_LINK).click();
    await page.waitForTimeout(500);

    // Verify fields are visible
    await expect(page.locator(LINK_HREF)).toBeVisible();

    // Now select None
    await page.locator(RADIO_NONE).click();
    await page.waitForTimeout(500);

    // All link fields should be hidden
    await expect(page.locator(LINK_HREF)).not.toBeVisible();
    await expect(page.locator(LINK_TARGET)).not.toBeVisible();
    await expect(page.locator(LINK_TITLE)).not.toBeVisible();
    await expect(page.locator(LINK_CLASS)).not.toBeVisible();

    console.log('Link fields are hidden after selecting None radio');
  });

  test('selecting Enlarge hides link fields', async ({ page }) => {
    await navigateToContentEdit(page, CE_STANDALONE);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // First select Link to make fields visible
    await page.locator(RADIO_LINK).click();
    await page.waitForTimeout(500);

    // Verify fields are visible
    await expect(page.locator(LINK_HREF)).toBeVisible();

    // Now select Enlarge
    await page.locator(RADIO_ENLARGE).click();
    await page.waitForTimeout(500);

    // Link URL field should be hidden
    await expect(page.locator(LINK_HREF)).not.toBeVisible();

    console.log('Link fields are hidden after selecting Enlarge radio');
  });

  test('setting Link URL applies anchor to editor HTML', async ({ page }) => {
    await navigateToContentEdit(page, CE_STANDALONE);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // Select Link radio
    await page.locator(RADIO_LINK).click();
    await page.waitForTimeout(500);

    // Enter a link URL
    const testUrl = 'https://example.com/click-behavior-test';
    await page.locator(LINK_HREF).fill(testUrl);

    // Confirm the dialog
    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Use data output since editing view has no <a> wrapper (#687)
    const editorData = await getCKEditorData(page);
    expect(editorData).toContain('<a ');
    expect(editorData).toContain('example.com/click-behavior-test');

    console.log('Link URL was applied to editor HTML');
  });

  test('setting Enlarge applies zoom attribute to editor HTML', async ({ page }) => {
    await navigateToContentEdit(page, CE_STANDALONE);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // Select Enlarge radio
    await page.locator(RADIO_ENLARGE).click();
    await page.waitForTimeout(500);

    // Confirm the dialog
    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Verify the editor HTML contains the zoom attribute
    const editorHtml = await getEditorHtml(page);
    expect(editorHtml).toContain('data-htmlarea-zoom');

    console.log('Zoom attribute was applied to editor HTML');
  });

  test('switching from Enlarge to None removes zoom attribute', async ({ page }) => {
    await navigateToContentEdit(page, CE_WITH_ZOOM);
    await waitForCKEditor(page);

    // Verify CE 30 currently has zoom attribute
    const initialHtml = await getEditorHtml(page);
    expect(initialHtml).toContain('data-htmlarea-zoom');
    console.log('CE 30 initially has data-htmlarea-zoom attribute');

    // Open dialog
    await openImageEditDialog(page);

    // Verify Enlarge is currently selected
    await expect(page.locator(RADIO_ENLARGE)).toBeChecked();

    // Switch to None
    await page.locator(RADIO_NONE).click();
    await page.waitForTimeout(500);

    // Confirm the dialog
    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Verify zoom attribute is removed from editor HTML
    const updatedHtml = await getEditorHtml(page);
    expect(updatedHtml).not.toContain('data-htmlarea-zoom');

    console.log('Zoom attribute was removed after switching to None');
  });
});
