import { test, expect } from '@playwright/test';
import { loginToBackend, navigateToContentEdit, waitForCKEditor, openImageEditDialog, confirmImageDialog, saveContentElement, requireCondition, BACKEND_PASSWORD } from './helpers/typo3-backend';

/**
 * E2E tests for image dimension controls in the CKEditor image dialog.
 *
 * Tests verify that:
 * 1. Width and height inputs are present and populated with numeric values
 * 2. Changing width auto-adjusts height (aspect ratio lock is on by default)
 * 3. Changing height auto-adjusts width
 * 4. Dimension values persist after confirming the dialog
 * 5. Dimension values persist after saving the content element and reloading
 *
 * The dimension inputs are in the modal (main page), NOT inside the CKEditor
 * iframe. CKEditor's editing view does not store width/height as DOM
 * attributes, so we verify via dialog field values rather than DOM inspection.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/614
 */

test.describe('Image Dialog - Dimensions', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
    await loginToBackend(page);
  });

  test('width input is present and has a numeric value', async ({ page }) => {
    await navigateToContentEdit(page, 26);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const widthInput = page.locator('#rteckeditorimage-width');
    await expect(widthInput, 'Width input not found in image dialog').toBeVisible();

    const widthValue = await widthInput.inputValue();
    console.log(`Width value: "${widthValue}"`);

    expect(widthValue).toBeTruthy();
    expect(Number(widthValue)).toBeGreaterThan(0);
    console.log('SUCCESS: Width input is present with a numeric value');
  });

  test('height input is present and has a numeric value', async ({ page }) => {
    await navigateToContentEdit(page, 26);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const heightInput = page.locator('#rteckeditorimage-height');
    await expect(heightInput, 'Height input not found in image dialog').toBeVisible();

    const heightValue = await heightInput.inputValue();
    console.log(`Height value: "${heightValue}"`);

    expect(heightValue).toBeTruthy();
    expect(Number(heightValue)).toBeGreaterThan(0);
    console.log('SUCCESS: Height input is present with a numeric value');
  });

  test('changing width auto-adjusts height (aspect ratio)', async ({ page }) => {
    await navigateToContentEdit(page, 26);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const widthInput = page.locator('#rteckeditorimage-width');
    const heightInput = page.locator('#rteckeditorimage-height');

    await expect(widthInput).toBeVisible();
    await expect(heightInput).toBeVisible();

    // Read original values to compute expected ratio
    const originalWidth = Number(await widthInput.inputValue());
    const originalHeight = Number(await heightInput.inputValue());
    console.log(`Original dimensions: ${originalWidth}x${originalHeight}`);

    requireCondition(originalWidth > 0 && originalHeight > 0, 'Original dimensions must be positive');

    const aspectRatio = originalHeight / originalWidth;

    // Set a new width value (halve the original)
    const newWidth = Math.round(originalWidth / 2);
    await widthInput.clear();
    await widthInput.fill(String(newWidth));

    // Tab out to trigger the JS event handler that recalculates height
    await widthInput.press('Tab');
    await page.waitForTimeout(300);

    const updatedHeight = Number(await heightInput.inputValue());
    console.log(`After setting width to ${newWidth}: height became ${updatedHeight}`);

    // The height should have been adjusted proportionally
    const expectedHeight = Math.round(newWidth * aspectRatio);
    expect(updatedHeight).toBeGreaterThan(0);
    // Allow 1px rounding tolerance
    expect(Math.abs(updatedHeight - expectedHeight)).toBeLessThanOrEqual(1);
    console.log(`SUCCESS: Height auto-adjusted to ${updatedHeight} (expected ~${expectedHeight})`);
  });

  test('changing height auto-adjusts width (aspect ratio)', async ({ page }) => {
    await navigateToContentEdit(page, 26);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const widthInput = page.locator('#rteckeditorimage-width');
    const heightInput = page.locator('#rteckeditorimage-height');

    await expect(widthInput).toBeVisible();
    await expect(heightInput).toBeVisible();

    // Read original values to compute expected ratio
    const originalWidth = Number(await widthInput.inputValue());
    const originalHeight = Number(await heightInput.inputValue());
    console.log(`Original dimensions: ${originalWidth}x${originalHeight}`);

    requireCondition(originalWidth > 0 && originalHeight > 0, 'Original dimensions must be positive');

    const aspectRatio = originalWidth / originalHeight;

    // Set a new height value (halve the original)
    const newHeight = Math.round(originalHeight / 2);
    await heightInput.clear();
    await heightInput.fill(String(newHeight));

    // Tab out to trigger the JS event handler that recalculates width
    await heightInput.press('Tab');
    await page.waitForTimeout(300);

    const updatedWidth = Number(await widthInput.inputValue());
    console.log(`After setting height to ${newHeight}: width became ${updatedWidth}`);

    // The width should have been adjusted proportionally
    const expectedWidth = Math.round(newHeight * aspectRatio);
    expect(updatedWidth).toBeGreaterThan(0);
    // Allow 1px rounding tolerance
    expect(Math.abs(updatedWidth - expectedWidth)).toBeLessThanOrEqual(1);
    console.log(`SUCCESS: Width auto-adjusted to ${updatedWidth} (expected ~${expectedWidth})`);
  });

  test('dimension values persist after confirm and re-open', async ({ page }) => {
    await navigateToContentEdit(page, 26);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const widthInput = page.locator('#rteckeditorimage-width');
    const heightInput = page.locator('#rteckeditorimage-height');

    await expect(widthInput).toBeVisible();
    await expect(heightInput).toBeVisible();

    // Read original width and compute a new value
    const originalWidth = Number(await widthInput.inputValue());
    requireCondition(originalWidth > 0, 'Original width must be positive');

    const newWidth = Math.round(originalWidth / 2);
    await widthInput.clear();
    await widthInput.fill(String(newWidth));
    await widthInput.press('Tab');
    await page.waitForTimeout(300);

    // Read the auto-adjusted height before confirming
    const adjustedHeight = await heightInput.inputValue();
    console.log(`Set width to ${newWidth}, height auto-adjusted to ${adjustedHeight}`);

    // Confirm the dialog
    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Re-open the dialog on the same image
    await openImageEditDialog(page);

    const persistedWidth = await widthInput.inputValue();
    const persistedHeight = await heightInput.inputValue();
    console.log(`After re-open: width=${persistedWidth}, height=${persistedHeight}`);

    expect(Number(persistedWidth)).toBe(newWidth);
    expect(persistedHeight).toBe(adjustedHeight);
    console.log('SUCCESS: Dimension values persisted after confirm and re-open');
  });

  test('dimension values persist after save and reload', async ({ page }) => {
    await navigateToContentEdit(page, 26);
    await waitForCKEditor(page);
    await openImageEditDialog(page);

    const widthInput = page.locator('#rteckeditorimage-width');
    const heightInput = page.locator('#rteckeditorimage-height');

    await expect(widthInput).toBeVisible();
    await expect(heightInput).toBeVisible();

    // Read original width and compute a new value
    const originalWidth = Number(await widthInput.inputValue());
    requireCondition(originalWidth > 0, 'Original width must be positive');

    const newWidth = Math.round(originalWidth / 2);
    await widthInput.clear();
    await widthInput.fill(String(newWidth));
    await widthInput.press('Tab');
    await page.waitForTimeout(300);

    // Read the auto-adjusted height before confirming
    const adjustedHeight = await heightInput.inputValue();
    console.log(`Set width to ${newWidth}, height auto-adjusted to ${adjustedHeight}`);

    // Confirm the dialog
    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Save the content element
    await saveContentElement(page);
    console.log('Saved content element');

    // Navigate back to the same content element
    await navigateToContentEdit(page, 26);
    await waitForCKEditor(page);

    // Re-open the dialog
    await openImageEditDialog(page);

    const persistedWidth = await widthInput.inputValue();
    const persistedHeight = await heightInput.inputValue();
    console.log(`After save and reload: width=${persistedWidth}, height=${persistedHeight}`);

    expect(Number(persistedWidth)).toBe(newWidth);
    expect(persistedHeight).toBe(adjustedHeight);
    console.log('SUCCESS: Dimension values persisted after save and reload');
  });
});
