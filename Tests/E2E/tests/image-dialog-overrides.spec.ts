import { test, expect } from '@playwright/test';
import {
  loginToBackend,
  navigateToContentEdit,
  waitForCKEditor,
  openImageEditDialog,
  confirmImageDialog,
  cancelImageDialog,
  getEditorHtml,
  saveContentElement,
  requireCondition,
  BACKEND_PASSWORD,
} from './helpers/typo3-backend';

/**
 * E2E tests for alt/title override checkboxes in the CKEditor image dialog.
 *
 * Tests verify that:
 * 1. Override checkboxes exist for alt and title fields
 * 2. Inputs are disabled when override is unchecked (showing FAL metadata)
 * 3. Clicking the override checkbox enables the input for custom values
 * 4. Custom values are applied to the editor HTML after confirming
 * 5. Override state persists after saving and reloading
 * 6. Cancelling the dialog reverts override state changes
 *
 * Dialog field IDs:
 * - Alt input: #rteckeditorimage-alt
 * - Alt override checkbox: #checkbox-alt
 * - Title input: #rteckeditorimage-title
 * - Title override checkbox: #checkbox-title
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/616
 */

test.describe('Image Dialog - Override Checkboxes', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
    await loginToBackend(page);
  });

  test('alt override checkbox exists in image dialog', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // The override checkbox for alt text should be present in the dialog
    const altCheckbox = page.locator('#checkbox-alt');
    await expect(altCheckbox, 'Alt override checkbox (#checkbox-alt) not found in dialog').toBeVisible();
  });

  test('alt input is disabled when override is unchecked', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const altInput = page.locator('#rteckeditorimage-alt');
    const altCheckbox = page.locator('#checkbox-alt');

    await expect(altInput, 'Alt input not found').toBeVisible();
    await expect(altCheckbox, 'Alt override checkbox not found').toBeVisible();

    // When the checkbox is unchecked, the input should show FAL metadata and be disabled
    const isChecked = await altCheckbox.isChecked();
    if (!isChecked) {
      await expect(altInput).toBeDisabled();
      console.log('Alt input is correctly disabled when override checkbox is unchecked');
    } else {
      // If already checked (no FAL metadata or previously overridden), uncheck first
      await altCheckbox.click();
      await page.waitForTimeout(300);

      // After unchecking, input should be disabled (unless checkbox itself is disabled)
      const checkboxDisabled = await altCheckbox.isDisabled();
      if (!checkboxDisabled) {
        await expect(altInput).toBeDisabled();
        console.log('Alt input is correctly disabled after unchecking override');
      }
    }
  });

  test('clicking alt override checkbox enables the input', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const altInput = page.locator('#rteckeditorimage-alt');
    const altCheckbox = page.locator('#checkbox-alt');

    await expect(altInput, 'Alt input not found').toBeVisible();
    await expect(altCheckbox, 'Alt override checkbox not found').toBeVisible();

    // If checkbox is disabled (no FAL metadata), skip the test
    const checkboxDisabled = await altCheckbox.isDisabled();
    if (checkboxDisabled) {
      console.log('Alt override checkbox is disabled (no FAL metadata) — skipping');
      return;
    }

    // Ensure override is unchecked first
    const isChecked = await altCheckbox.isChecked();
    if (isChecked) {
      await altCheckbox.click();
      await page.waitForTimeout(300);
    }

    // Input should be disabled when override is unchecked
    await expect(altInput).toBeDisabled();

    // Click the override checkbox to enable the input
    await altCheckbox.click();
    await page.waitForTimeout(300);

    // Now the input should be enabled
    await expect(altInput).toBeEnabled();
    console.log('Alt input is correctly enabled after clicking override checkbox');
  });

  test('custom alt text is applied after enabling override', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const altInput = page.locator('#rteckeditorimage-alt');
    const altCheckbox = page.locator('#checkbox-alt');

    await expect(altInput, 'Alt input not found').toBeVisible();
    await expect(altCheckbox, 'Alt override checkbox not found').toBeVisible();

    // Enable override if not already enabled
    const isDisabled = await altInput.isDisabled();
    if (isDisabled) {
      const checkboxDisabled = await altCheckbox.isDisabled();
      requireCondition(!checkboxDisabled, 'Alt override checkbox is disabled — cannot test override');
      await altCheckbox.click();
      await page.waitForTimeout(300);
    }

    await expect(altInput).toBeEnabled();

    // Type a unique custom alt text
    const customAlt = `Override Alt ${Date.now()}`;
    await altInput.fill(customAlt);
    console.log(`Set custom alt text: "${customAlt}"`);

    // Confirm the dialog
    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Verify the custom alt text is present in the editor HTML
    const editorHtml = await getEditorHtml(page);
    expect(editorHtml).toContain(`alt="${customAlt}"`);
    console.log('Custom alt text was correctly applied to editor HTML');
  });

  test('title override checkbox exists in image dialog', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    // The override checkbox for title should be present in the dialog
    const titleCheckbox = page.locator('#checkbox-title');
    await expect(titleCheckbox, 'Title override checkbox (#checkbox-title) not found in dialog').toBeVisible();
  });

  test('clicking title override checkbox enables the title input', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const titleInput = page.locator('#rteckeditorimage-title');
    const titleCheckbox = page.locator('#checkbox-title');

    await expect(titleInput, 'Title input not found').toBeVisible();
    await expect(titleCheckbox, 'Title override checkbox not found').toBeVisible();

    // If checkbox is disabled (no FAL metadata for title), skip the test
    const checkboxDisabled = await titleCheckbox.isDisabled();
    if (checkboxDisabled) {
      console.log('Title override checkbox is disabled (no FAL metadata) — skipping');
      return;
    }

    // Ensure override is unchecked first
    const isChecked = await titleCheckbox.isChecked();
    if (isChecked) {
      await titleCheckbox.click();
      await page.waitForTimeout(300);
    }

    // Input should be disabled when override is unchecked
    await expect(titleInput).toBeDisabled();

    // Click the override checkbox to enable the input
    await titleCheckbox.click();
    await page.waitForTimeout(300);

    // Now the input should be enabled
    await expect(titleInput).toBeEnabled();
    console.log('Title input is correctly enabled after clicking override checkbox');
  });

  test('override state persists after save and reload', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const altInput = page.locator('#rteckeditorimage-alt');
    const altCheckbox = page.locator('#checkbox-alt');

    await expect(altInput, 'Alt input not found').toBeVisible();
    await expect(altCheckbox, 'Alt override checkbox not found').toBeVisible();

    // Enable override if not already enabled
    const isDisabled = await altInput.isDisabled();
    if (isDisabled) {
      const checkboxDisabled = await altCheckbox.isDisabled();
      requireCondition(!checkboxDisabled, 'Alt override checkbox is disabled — cannot test persistence');
      await altCheckbox.click();
      await page.waitForTimeout(300);
    }

    await expect(altInput).toBeEnabled();

    // Set a unique value to verify persistence
    const persistAlt = `Persist Override ${Date.now()}`;
    await altInput.fill(persistAlt);
    console.log(`Set alt for persistence test: "${persistAlt}"`);

    // Confirm the dialog
    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Save the content element
    await saveContentElement(page);
    console.log('Saved content element');

    // Reload the page and navigate back to content edit
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);

    // Re-open the image dialog
    await openImageEditDialog(page);

    // Verify the override checkbox is still checked
    const altCheckboxAfter = page.locator('#checkbox-alt');
    const altInputAfter = page.locator('#rteckeditorimage-alt');

    await expect(altCheckboxAfter, 'Alt override checkbox not found after reload').toBeVisible();
    await expect(altInputAfter, 'Alt input not found after reload').toBeVisible();

    // The input should be enabled (override still active)
    await expect(altInputAfter).toBeEnabled();

    // The custom value should have been preserved
    const valueAfterReload = await altInputAfter.inputValue();
    console.log(`Alt value after reload: "${valueAfterReload}"`);

    expect(valueAfterReload).toBe(persistAlt);
    console.log('Override state and value persisted after save/reload');
  });

  test('cancel does not change override state', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);

    // First, open dialog and record the initial override state
    await openImageEditDialog(page);

    const altInput = page.locator('#rteckeditorimage-alt');
    const altCheckbox = page.locator('#checkbox-alt');

    await expect(altInput, 'Alt input not found').toBeVisible();
    await expect(altCheckbox, 'Alt override checkbox not found').toBeVisible();

    // If the checkbox is disabled, skip this test
    const checkboxDisabled = await altCheckbox.isDisabled();
    if (checkboxDisabled) {
      console.log('Alt override checkbox is disabled (no FAL metadata) — skipping');
      await cancelImageDialog(page);
      return;
    }

    // Record initial state
    const initialChecked = await altCheckbox.isChecked();
    const initialValue = await altInput.inputValue();
    console.log(`Initial state — checked: ${initialChecked}, value: "${initialValue}"`);

    // Toggle the override checkbox (change state)
    await altCheckbox.click();
    await page.waitForTimeout(300);

    // If we just enabled the override, type a different value
    if (!initialChecked) {
      await altInput.fill('This should be reverted');
    }

    // Cancel the dialog — changes should NOT be applied
    await cancelImageDialog(page);
    await page.waitForTimeout(500);

    // Re-open the dialog
    await openImageEditDialog(page);

    const altCheckboxAgain = page.locator('#checkbox-alt');
    const altInputAgain = page.locator('#rteckeditorimage-alt');

    await expect(altCheckboxAgain, 'Alt override checkbox not found after re-open').toBeVisible();
    await expect(altInputAgain, 'Alt input not found after re-open').toBeVisible();

    // The override state should match the original (before our toggle)
    const currentChecked = await altCheckboxAgain.isChecked();
    const currentValue = await altInputAgain.inputValue();
    console.log(`After cancel — checked: ${currentChecked}, value: "${currentValue}"`);

    expect(currentChecked).toBe(initialChecked);
    expect(currentValue).toBe(initialValue);
    console.log('Cancel correctly reverted override state changes');
  });
});
