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
 * CE 28 has data-alt-override="false" and data-title-override="false", so the
 * override checkboxes start UNCHECKED and the alt/title inputs start DISABLED
 * (showing FAL metadata as placeholder text).
 *
 * IMPORTANT: The toggle handler is bound to the LABEL element's click event
 * (typo3image.js cboxLabel.on('click', ...)), NOT to the checkbox's change event.
 * Clicking the checkbox directly does NOT trigger the toggle. Always click the
 * label to toggle override state: page.locator('label[for="checkbox-alt"]').click()
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

    // CE 28 has data-alt-override="false" → checkbox unchecked, input disabled
    await expect(altCheckbox).not.toBeChecked();
    await expect(altInput).toBeDisabled();
    console.log('Alt input is correctly disabled when override checkbox is unchecked');
  });

  test('clicking alt override label enables the input', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const altInput = page.locator('#rteckeditorimage-alt');
    const altCheckbox = page.locator('#checkbox-alt');
    const altLabel = page.locator('label[for="checkbox-alt"]');

    await expect(altInput, 'Alt input not found').toBeVisible();
    await expect(altLabel, 'Alt override label not found').toBeVisible();

    // CE 28 starts with override unchecked, input disabled
    await expect(altCheckbox).not.toBeChecked();
    await expect(altInput).toBeDisabled();

    // Click the LABEL (not checkbox) to toggle — the handler is on the label
    await altLabel.click();
    await page.waitForTimeout(300);

    // Now the checkbox should be checked and input enabled
    await expect(altCheckbox).toBeChecked();
    await expect(altInput).toBeEnabled();
    console.log('Alt input is correctly enabled after clicking override label');
  });

  test('custom alt text is applied after enabling override', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const altInput = page.locator('#rteckeditorimage-alt');
    const altLabel = page.locator('label[for="checkbox-alt"]');

    await expect(altInput, 'Alt input not found').toBeVisible();

    // Enable override by clicking the label
    await altLabel.click();
    await page.waitForTimeout(300);
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

    const titleCheckbox = page.locator('#checkbox-title');
    await expect(titleCheckbox, 'Title override checkbox (#checkbox-title) not found in dialog').toBeVisible();
  });

  test('clicking title override label enables the title input', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const titleInput = page.locator('#rteckeditorimage-title');
    const titleCheckbox = page.locator('#checkbox-title');
    const titleLabel = page.locator('label[for="checkbox-title"]');

    await expect(titleInput, 'Title input not found').toBeVisible();
    await expect(titleLabel, 'Title override label not found').toBeVisible();

    // CE 28 starts with title override unchecked, input disabled
    await expect(titleCheckbox).not.toBeChecked();
    await expect(titleInput).toBeDisabled();

    // Click the LABEL to toggle override
    await titleLabel.click();
    await page.waitForTimeout(300);

    // Now the checkbox should be checked and input enabled
    await expect(titleCheckbox).toBeChecked();
    await expect(titleInput).toBeEnabled();
    console.log('Title input is correctly enabled after clicking override label');
  });

  test('override state persists after save and reload', async ({ page }) => {
    await navigateToContentEdit(page, 28);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const altInput = page.locator('#rteckeditorimage-alt');
    const altLabel = page.locator('label[for="checkbox-alt"]');

    await expect(altInput, 'Alt input not found').toBeVisible();

    // Enable override by clicking label
    await altLabel.click();
    await page.waitForTimeout(300);
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

    // Reload and navigate back to content edit
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
    const altLabel = page.locator('label[for="checkbox-alt"]');

    await expect(altInput, 'Alt input not found').toBeVisible();
    await expect(altCheckbox, 'Alt override checkbox not found').toBeVisible();

    // Record initial state (CE 28: unchecked, disabled, empty)
    const initialChecked = await altCheckbox.isChecked();
    const initialValue = await altInput.inputValue();
    console.log(`Initial state — checked: ${initialChecked}, value: "${initialValue}"`);

    // Toggle the override by clicking the label (change state)
    await altLabel.click();
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
