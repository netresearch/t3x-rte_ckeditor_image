import { test, expect } from '@playwright/test';
import { loginToBackend, navigateToContentEdit, waitForCKEditor, openImageEditDialog, confirmImageDialog, getEditorHtml, saveContentElement, requireCondition, BACKEND_PASSWORD } from './helpers/typo3-backend';

/**
 * E2E tests for the image quality selector in the CKEditor image dialog.
 *
 * Tests verify that:
 * 1. The quality dropdown is present in the image edit dialog
 * 2. The dropdown contains all expected quality options
 * 3. Selecting a quality level writes the data-quality attribute to editor HTML
 * 4. Quality selection persists after saving and reloading the content element
 * 5. The default quality for a fresh (non-SVG) image is "retina"
 *
 * The quality selector controls the image processing multiplier used when
 * TYPO3 generates processed image variants. Values: none, standard, retina,
 * ultra, print.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/615
 */

test.describe('Image Dialog - Quality Selector', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
    await loginToBackend(page);
  });

  test('quality dropdown is present in dialog', async ({ page }) => {
    await navigateToContentEdit(page, 27);
    await waitForCKEditor(page);

    await openImageEditDialog(page);

    // The quality dropdown has ID #rteckeditorimage-quality
    const qualitySelect = page.locator('#rteckeditorimage-quality');
    const count = await qualitySelect.count();
    console.log(`Quality dropdown elements found: ${count}`);

    if (count === 0) {
      // Fallback: try to find any select in the modal
      const modalSelects = page.locator('.t3js-modal select');
      const selectCount = await modalSelects.count();
      console.log(`Total selects in modal: ${selectCount}`);
      await page.screenshot({ path: 'test-results/quality-dropdown-missing.png' });
    }

    await expect(qualitySelect, 'Quality dropdown should be present in image dialog').toBeVisible();
  });

  test('quality dropdown has expected options', async ({ page }) => {
    await navigateToContentEdit(page, 27);
    await waitForCKEditor(page);

    await openImageEditDialog(page);

    const qualitySelect = page.locator('#rteckeditorimage-quality');
    await expect(qualitySelect).toBeVisible();

    // Get all options from the dropdown
    const options = qualitySelect.locator('option');
    const optionCount = await options.count();
    console.log(`Quality dropdown option count: ${optionCount}`);

    // Should have exactly 5 options: none, standard, retina, ultra, print
    expect(optionCount).toBe(5);

    // Verify option values
    const expectedValues = ['none', 'standard', 'retina', 'ultra', 'print'];
    for (let i = 0; i < expectedValues.length; i++) {
      const value = await options.nth(i).getAttribute('value');
      console.log(`Option ${i}: value="${value}", text="${await options.nth(i).textContent()}"`);
      expect(value).toBe(expectedValues[i]);
    }

    // Verify option labels contain expected text (labels have marker prefix)
    const expectedLabels = ['No Scaling', 'Standard (1.0x)', 'Retina (2.0x)', 'Ultra (3.0x)', 'Print (6.0x)'];
    for (let i = 0; i < expectedLabels.length; i++) {
      const text = await options.nth(i).textContent();
      expect(text).toContain(expectedLabels[i]);
    }
  });

  test('selecting quality updates data attribute in editor', async ({ page }) => {
    await navigateToContentEdit(page, 27);
    await waitForCKEditor(page);

    await openImageEditDialog(page);

    const qualitySelect = page.locator('#rteckeditorimage-quality');
    await expect(qualitySelect).toBeVisible();

    // Select "ultra" quality (3.0x)
    await qualitySelect.selectOption('ultra');
    const selectedValue = await qualitySelect.inputValue();
    console.log(`Selected quality: "${selectedValue}"`);
    expect(selectedValue).toBe('ultra');

    // Confirm the dialog to apply changes
    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Verify data-quality attribute is set in editor HTML
    const editorHtml = await getEditorHtml(page);
    console.log(`Editor HTML contains data-quality: ${editorHtml.includes('data-quality')}`);

    expect(editorHtml).toContain('data-quality="ultra"');
    console.log('SUCCESS: data-quality="ultra" found in editor HTML');
  });

  test('quality persists after save and reload', async ({ page }) => {
    await navigateToContentEdit(page, 27);
    await waitForCKEditor(page);

    // Open dialog and set quality to "print"
    await openImageEditDialog(page);

    const qualitySelect = page.locator('#rteckeditorimage-quality');
    await expect(qualitySelect).toBeVisible();

    await qualitySelect.selectOption('print');
    console.log('Selected quality: print');

    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Verify it was applied
    const htmlBeforeSave = await getEditorHtml(page);
    expect(htmlBeforeSave).toContain('data-quality="print"');
    console.log('Quality applied before save');

    // Save the content element
    await saveContentElement(page);
    console.log('Content element saved');

    // Reload the page and navigate back to the content element
    await navigateToContentEdit(page, 27);
    await waitForCKEditor(page);

    // Verify data-quality persisted in editor HTML
    const htmlAfterReload = await getEditorHtml(page);
    expect(htmlAfterReload).toContain('data-quality="print"');
    console.log('Quality persisted in editor HTML after reload');

    // Re-open dialog and verify the dropdown still shows "print"
    await openImageEditDialog(page);

    const qualitySelectAfter = page.locator('#rteckeditorimage-quality');
    await expect(qualitySelectAfter).toBeVisible();

    const selectedValue = await qualitySelectAfter.inputValue();
    console.log(`Quality after reload: "${selectedValue}"`);

    expect(selectedValue).toBe('print');
    console.log('SUCCESS: Quality selection persisted after save/reload');
  });

  test('default quality is retina for fresh image', async ({ page }) => {
    await navigateToContentEdit(page, 27);
    await waitForCKEditor(page);

    // Open image dialog on CE 27 (800x600, alt="Quality Test")
    await openImageEditDialog(page);

    const qualitySelect = page.locator('#rteckeditorimage-quality');
    await expect(qualitySelect).toBeVisible();

    // For a non-SVG image without existing data-quality attribute,
    // the default quality is "retina" (see typo3image.js defaultQuality logic)
    const selectedValue = await qualitySelect.inputValue();
    console.log(`Default quality value: "${selectedValue}"`);

    // The default for a fresh raster image is "retina"
    // (Priority: data-quality > data-noscale > SVG > default "retina")
    expect(selectedValue).toBe('retina');
    console.log('SUCCESS: Default quality is "retina" for fresh image');
  });
});
