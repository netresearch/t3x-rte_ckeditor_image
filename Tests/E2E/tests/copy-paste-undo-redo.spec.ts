import { test, expect, Page } from '@playwright/test';
import { loginToBackend, navigateToContentEdit, waitForCKEditor, getEditorHtml, getModuleFrame, selectImageInEditor, openImageEditDialog, confirmImageDialog, requireCondition, BACKEND_PASSWORD } from './helpers/typo3-backend';

/**
 * E2E tests for copy/paste and undo/redo operations with images in CKEditor.
 *
 * These tests verify that clipboard and history operations preserve
 * image attributes (especially data-htmlarea-file-uid) when working
 * with TYPO3 RTE images in the CKEditor backend.
 *
 * IMPORTANT: CKEditor runs inside an iframe in the TYPO3 backend.
 * Ctrl+C/V does not trigger CKEditor's clipboard pipeline in CI (Playwright
 * synthetic keyboard events inside iframe). Copy/paste tests are skipped.
 * Cut (Ctrl+X) works because CKEditor handles widget deletion natively.
 * Undo/redo of dialog changes works via CKEditor's model change tracking.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/620
 */

/**
 * Focus the CKEditor editable area inside the module iframe.
 *
 * This is required before dispatching keyboard events so that
 * CKEditor receives and intercepts them properly.
 */
async function focusEditable(page: Page): Promise<void> {
  const frame = getModuleFrame(page);
  await frame.locator('.ck-editor__editable').first().click();
  await page.waitForTimeout(300);
}

/**
 * Count images in the CKEditor editable area that have the
 * data-htmlarea-file-uid attribute (TYPO3-managed images).
 */
async function countTypo3Images(page: Page): Promise<number> {
  const frame = getModuleFrame(page);
  return await frame.locator('.ck-editor__editable img[data-htmlarea-file-uid]').count();
}

/**
 * Get all data-htmlarea-file-uid values from images in the editor.
 */
async function getFileUids(page: Page): Promise<string[]> {
  const frame = getModuleFrame(page);
  return await frame.locator('.ck-editor__editable img[data-htmlarea-file-uid]').evaluateAll(
    (imgs: Element[]) => imgs.map(img => img.getAttribute('data-htmlarea-file-uid') || '')
  );
}

/**
 * Get the alt text of the first image in the editor.
 */
async function getFirstImageAlt(page: Page): Promise<string> {
  const frame = getModuleFrame(page);
  const img = frame.locator('.ck-editor__editable img').first();
  return await img.getAttribute('alt') || '';
}

// =============================================================================
// Test Suite: Copy/Paste and Undo/Redo
// =============================================================================

test.describe('Copy/Paste and Undo/Redo (#620)', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
    await loginToBackend(page);
  });

  test.skip('select and copy image preserves attributes on paste', async ({ page }) => {
    // SKIP: Ctrl+C/V does not trigger CKEditor's clipboard pipeline inside
    // the TYPO3 backend iframe — paste count stays at 1 (no image duplicated).
    await navigateToContentEdit(page);
    await waitForCKEditor(page);

    // Verify we have at least one TYPO3 image to work with
    const initialCount = await countTypo3Images(page);
    requireCondition(initialCount > 0, 'CE 1 must contain at least one image with data-htmlarea-file-uid');

    const initialUids = await getFileUids(page);
    console.log(`Initial image count: ${initialCount}, UIDs: ${JSON.stringify(initialUids)}`);

    // Select the image by clicking on it
    await selectImageInEditor(page);
    await page.waitForTimeout(500);

    // Copy the selected image
    await page.keyboard.press('Control+c');
    await page.waitForTimeout(500);

    // Move cursor to end of editor content to paste in a new position
    await page.keyboard.press('End');
    await page.waitForTimeout(300);

    // Paste the image
    await page.keyboard.press('Control+v');
    await page.waitForTimeout(1000);

    // Verify: should now have one more image than before
    const afterPasteCount = await countTypo3Images(page);
    console.log(`After paste image count: ${afterPasteCount}`);
    expect(afterPasteCount).toBe(initialCount + 1);

    // Verify: all images (original + pasted) should have data-htmlarea-file-uid
    const afterPasteUids = await getFileUids(page);
    console.log(`After paste UIDs: ${JSON.stringify(afterPasteUids)}`);

    for (const uid of afterPasteUids) {
      expect(uid, 'Every image should retain data-htmlarea-file-uid after paste').toBeTruthy();
    }

    // The pasted image should have the same UID as the source
    expect(afterPasteUids).toContain(initialUids[0]);
    expect(afterPasteUids.filter(uid => uid === initialUids[0]).length).toBe(2);
  });

  test.skip('undo removes pasted image', async ({ page }) => {
    // SKIP: Depends on copy/paste working (see above).
    await navigateToContentEdit(page);
    await waitForCKEditor(page);

    const initialCount = await countTypo3Images(page);
    requireCondition(initialCount > 0, 'CE 1 must contain at least one image with data-htmlarea-file-uid');

    // Select and copy the image
    await selectImageInEditor(page);
    await page.waitForTimeout(500);
    await page.keyboard.press('Control+c');
    await page.waitForTimeout(500);

    // Move cursor and paste
    await page.keyboard.press('End');
    await page.waitForTimeout(300);
    await page.keyboard.press('Control+v');
    await page.waitForTimeout(1000);

    // Verify paste happened
    const afterPasteCount = await countTypo3Images(page);
    console.log(`After paste: ${afterPasteCount} images (expected ${initialCount + 1})`);
    expect(afterPasteCount).toBe(initialCount + 1);

    // Focus the editable area to ensure undo targets CKEditor
    await focusEditable(page);
    await page.waitForTimeout(300);

    // Undo the paste
    await page.keyboard.press('Control+z');
    await page.waitForTimeout(1000);

    // Verify: should be back to original count
    const afterUndoCount = await countTypo3Images(page);
    console.log(`After undo: ${afterUndoCount} images (expected ${initialCount})`);
    expect(afterUndoCount).toBe(initialCount);
  });

  test.skip('redo restores pasted image after undo', async ({ page }) => {
    // SKIP: Depends on copy/paste working (see above).
    await navigateToContentEdit(page);
    await waitForCKEditor(page);

    const initialCount = await countTypo3Images(page);
    requireCondition(initialCount > 0, 'CE 1 must contain at least one image with data-htmlarea-file-uid');

    // Select, copy, move, paste
    await selectImageInEditor(page);
    await page.waitForTimeout(500);
    await page.keyboard.press('Control+c');
    await page.waitForTimeout(500);
    await page.keyboard.press('End');
    await page.waitForTimeout(300);
    await page.keyboard.press('Control+v');
    await page.waitForTimeout(1000);

    // Verify paste
    const afterPasteCount = await countTypo3Images(page);
    expect(afterPasteCount).toBe(initialCount + 1);

    // Undo
    await focusEditable(page);
    await page.waitForTimeout(300);
    await page.keyboard.press('Control+z');
    await page.waitForTimeout(1000);

    // Verify undo
    const afterUndoCount = await countTypo3Images(page);
    expect(afterUndoCount).toBe(initialCount);

    // Redo — try Ctrl+Y first (most common CKEditor redo shortcut)
    await focusEditable(page);
    await page.waitForTimeout(300);
    await page.keyboard.press('Control+y');
    await page.waitForTimeout(1000);

    // Verify: should have the pasted image back
    const afterRedoCount = await countTypo3Images(page);
    console.log(`After redo: ${afterRedoCount} images (expected ${initialCount + 1})`);
    expect(afterRedoCount).toBe(initialCount + 1);

    // Verify the restored image retains its data-htmlarea-file-uid
    const afterRedoUids = await getFileUids(page);
    for (const uid of afterRedoUids) {
      expect(uid, 'Every image should retain data-htmlarea-file-uid after redo').toBeTruthy();
    }
  });

  test('undo reverts alt text change made via dialog', async ({ page }) => {

    await navigateToContentEdit(page);
    await waitForCKEditor(page);

    // Get original alt text
    const originalAlt = await getFirstImageAlt(page);
    console.log(`Original alt text: "${originalAlt}"`);

    // Open image dialog and change alt text
    await openImageEditDialog(page);

    const altInput = page.locator('#rteckeditorimage-alt');
    const altCheckbox = page.locator('#checkbox-alt');

    requireCondition(await altInput.count() > 0, 'Alt input not found in image dialog');

    // Enable override checkbox if alt input is disabled
    const isDisabled = await altInput.isDisabled();
    if (isDisabled && await altCheckbox.count() > 0) {
      const checkboxDisabled = await altCheckbox.isDisabled();
      if (!checkboxDisabled) {
        await altCheckbox.click();
        await page.waitForTimeout(300);
      }
    }

    const modifiedAlt = `Modified Alt ${Date.now()}`;
    await altInput.fill(modifiedAlt);
    console.log(`Changed alt text to: "${modifiedAlt}"`);

    // Confirm dialog to apply changes
    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Verify alt was changed
    const afterChangeAlt = await getFirstImageAlt(page);
    console.log(`After dialog confirm alt: "${afterChangeAlt}"`);
    expect(afterChangeAlt).toBe(modifiedAlt);

    // Try to undo the dialog change
    await focusEditable(page);
    await page.waitForTimeout(300);
    await page.keyboard.press('Control+z');
    await page.waitForTimeout(1000);

    // Check if undo reverted the alt text
    const afterUndoAlt = await getFirstImageAlt(page);
    console.log(`After undo alt: "${afterUndoAlt}"`);
    expect(afterUndoAlt).toBe(originalAlt);
  });

  test('cut and paste image moves it to new position', async ({ page }) => {

    await navigateToContentEdit(page);
    await waitForCKEditor(page);

    const initialCount = await countTypo3Images(page);
    requireCondition(initialCount > 0, 'CE 1 must contain at least one image with data-htmlarea-file-uid');

    const initialHtml = await getEditorHtml(page);
    console.log(`Initial editor HTML length: ${initialHtml.length}`);

    // Select the image
    await selectImageInEditor(page);
    await page.waitForTimeout(500);

    // Cut the image
    await page.keyboard.press('Control+x');
    await page.waitForTimeout(1000);

    // Verify image was removed (cut)
    const afterCutCount = await countTypo3Images(page);
    console.log(`After cut: ${afterCutCount} images (expected ${initialCount - 1})`);
    expect(afterCutCount).toBe(initialCount - 1);

    // Focus and move cursor to a different position
    await focusEditable(page);
    await page.waitForTimeout(300);
    await page.keyboard.press('End');
    await page.waitForTimeout(300);

    // Paste the image at the new position
    await page.keyboard.press('Control+v');
    await page.waitForTimeout(1000);

    // Verify: should be back to original count (image moved, not duplicated)
    const afterPasteCount = await countTypo3Images(page);
    console.log(`After paste: ${afterPasteCount} images (expected ${initialCount})`);
    expect(afterPasteCount).toBe(initialCount);

    // Verify the pasted image retains data-htmlarea-file-uid
    const afterPasteUids = await getFileUids(page);
    console.log(`After cut+paste UIDs: ${JSON.stringify(afterPasteUids)}`);
    for (const uid of afterPasteUids) {
      expect(uid, 'Image should retain data-htmlarea-file-uid after cut+paste').toBeTruthy();
    }
  });
});
