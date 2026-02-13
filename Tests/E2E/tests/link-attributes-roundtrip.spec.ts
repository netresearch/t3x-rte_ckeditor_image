import { test, expect, Page } from '@playwright/test';
import { loginToBackend, navigateToContentEdit, getModuleFrame, waitForCKEditor, openImageEditDialog, confirmImageDialog, saveContentElement, getEditorHtml, requireCondition, BACKEND_PASSWORD } from './helpers/typo3-backend';

/** Dedicated CE for this spec to prevent cross-file pollution (parallel execution) */
const CE_ID = 32;

/**
 * E2E tests for link attributes round-trip persistence.
 *
 * Tests the workflow: edit → change → save → close → edit again
 * Verifies that these attributes are preserved:
 * - Link CSS class
 * - Link target
 * - Link title
 * - Link additional params
 * - Image alignment classes
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/565
 */

/**
 * Get current values from the image dialog
 */
async function getDialogValues(page: Page): Promise<{
  linkHref: string;
  linkTarget: string;
  linkTitle: string;
  linkClass: string;
  linkParams: string;
  clickBehavior: string;
}> {
  // Wait for dialog to be fully loaded
  await page.waitForTimeout(300);

  const linkHref = await page.locator('#rteckeditorimage-linkHref').inputValue().catch(() => '');
  const linkTarget = await page.locator('#rteckeditorimage-linkTarget').inputValue().catch(() => '');
  const linkTitle = await page.locator('#rteckeditorimage-linkTitle').inputValue().catch(() => '');
  const linkClass = await page.locator('#input-linkCssClass').inputValue().catch(() => '');
  const linkParams = await page.locator('#rteckeditorimage-linkParams').inputValue().catch(() => '');

  // Get which radio is checked
  let clickBehavior = 'none';
  if (await page.locator('#clickBehavior-link').isChecked().catch(() => false)) {
    clickBehavior = 'link';
  } else if (await page.locator('#clickBehavior-enlarge').isChecked().catch(() => false)) {
    clickBehavior = 'enlarge';
  }

  return { linkHref, linkTarget, linkTitle, linkClass, linkParams, clickBehavior };
}

/**
 * Set values in the image dialog
 */
async function setDialogValues(page: Page, values: {
  linkHref?: string;
  linkTarget?: string;
  linkTitle?: string;
  linkClass?: string;
  linkParams?: string;
}): Promise<void> {
  // First, select "Link" radio to show link fields
  const linkRadio = page.locator('#clickBehavior-link');
  if (await linkRadio.count() > 0) {
    await linkRadio.click();
    await page.waitForTimeout(300); // Wait for fields to appear
  }

  if (values.linkHref !== undefined) {
    await page.locator('#rteckeditorimage-linkHref').fill(values.linkHref);
  }
  if (values.linkTarget !== undefined) {
    await page.locator('#rteckeditorimage-linkTarget').fill(values.linkTarget);
  }
  if (values.linkTitle !== undefined) {
    await page.locator('#rteckeditorimage-linkTitle').fill(values.linkTitle);
  }
  if (values.linkClass !== undefined) {
    await page.locator('#input-linkCssClass').fill(values.linkClass);
  }
  if (values.linkParams !== undefined) {
    await page.locator('#rteckeditorimage-linkParams').fill(values.linkParams);
  }
}

test.describe('Link Attributes Round-Trip Persistence', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
    await loginToBackend(page);
  });

  test('link attributes persist after save and reload', async ({ page }) => {
    // Test values - use unique values to ensure we're testing round-trip
    const testValues = {
      linkHref: 'https://example.com/test-roundtrip',
      linkTarget: '_blank',
      linkTitle: 'Test Link Title',
      linkClass: 'my-custom-link-class',
      linkParams: '&foo=bar&test=123'
    };

    // Step 1: Navigate to content edit
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    // Step 2: Open image dialog
    await openImageEditDialog(page);

    // Step 3: Set all link values
    console.log('Setting link values:', testValues);
    await setDialogValues(page, testValues);

    // Step 4: Confirm dialog
    await confirmImageDialog(page);

    // Step 5: Check HTML in editor after dialog close
    const htmlAfterDialog = await getEditorHtml(page);
    console.log('HTML after dialog close:', htmlAfterDialog.substring(0, 500));

    // Step 6: Save the content element
    await saveContentElement(page);

    // Step 7: Reload the page to simulate "close and edit again"
    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Step 8: Navigate back to content edit
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    // Step 9: Check HTML in editor after reload
    const htmlAfterReload = await getEditorHtml(page);
    console.log('HTML after reload:', htmlAfterReload.substring(0, 500));

    // Step 10: Open image dialog again
    await openImageEditDialog(page);

    // Step 11: Get values from dialog
    const retrievedValues = await getDialogValues(page);
    console.log('Retrieved values after reload:', retrievedValues);

    // Step 12: Take screenshot for debugging
    await page.screenshot({ path: 'test-results/link-roundtrip-dialog.png', fullPage: true });

    // Step 13: Assertions - verify all values persisted
    expect(retrievedValues.clickBehavior).toBe('link');
    expect(retrievedValues.linkHref).toBe(testValues.linkHref);
    expect(retrievedValues.linkTarget).toBe(testValues.linkTarget);
    expect(retrievedValues.linkTitle).toBe(testValues.linkTitle);
    expect(retrievedValues.linkClass).toBe(testValues.linkClass);
    expect(retrievedValues.linkParams).toBe(testValues.linkParams);
  });

  test('image alignment persists after save and reload', async ({ page }) => {
    // Step 1: Navigate to content edit
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    // Step 2: Get initial HTML
    const initialHtml = await getEditorHtml(page);
    console.log('Initial HTML:', initialHtml.substring(0, 500));

    // Check for alignment classes
    const hasImageLeft = initialHtml.includes('image-left');
    const hasImageRight = initialHtml.includes('image-right');
    const hasImageCenter = initialHtml.includes('image-center');
    console.log('Initial alignment:', { hasImageLeft, hasImageRight, hasImageCenter });

    // Step 3: Open image dialog and set a link (this should NOT remove alignment)
    await openImageEditDialog(page);

    await setDialogValues(page, {
      linkHref: 'https://example.com/alignment-test',
      linkClass: 'test-link'
    });

    await confirmImageDialog(page);

    // Step 4: Check HTML after dialog - alignment should be preserved
    const htmlAfterDialog = await getEditorHtml(page);
    console.log('HTML after dialog:', htmlAfterDialog.substring(0, 500));

    // Step 5: Save content element
    await saveContentElement(page);

    // Step 6: Reload
    await page.reload();
    await page.waitForLoadState('networkidle');
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    // Step 7: Check HTML after reload
    const htmlAfterReload = await getEditorHtml(page);
    console.log('HTML after reload:', htmlAfterReload.substring(0, 500));

    // Step 8: Verify alignment is still present
    const alignmentPreserved =
      (hasImageLeft && htmlAfterReload.includes('image-left')) ||
      (hasImageRight && htmlAfterReload.includes('image-right')) ||
      (hasImageCenter && htmlAfterReload.includes('image-center')) ||
      (!hasImageLeft && !hasImageRight && !hasImageCenter); // No alignment initially

    await page.screenshot({ path: 'test-results/alignment-roundtrip.png', fullPage: true });

    expect(alignmentPreserved).toBe(true);
  });

  test('debug: inspect raw HTML structure of linked image', async ({ page }) => {
    // This test helps debug by showing the exact HTML structure

    // Capture console messages from the page and any frames
    page.on('console', msg => {
      if (msg.text().includes('[typo3image DEBUG]') || msg.text().includes('typo3image')) {
        console.log('BROWSER:', msg.text());
      }
    });

    // Also capture from frames
    page.on('frameattached', frame => {
      frame.on('console', (msg: any) => {
        if (msg.text && msg.text().includes('[typo3image DEBUG]')) {
          console.log('FRAME:', msg.text());
        }
      });
    });

    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    // Set up a link with all attributes
    await openImageEditDialog(page);

    await setDialogValues(page, {
      linkHref: 't3://page?uid=1',
      linkTarget: 'custom_frame',
      linkTitle: 'Debug Title',
      linkClass: 'debug-class another-class',
      linkParams: '&L=1&type=123'
    });

    await confirmImageDialog(page);

    // Get HTML before save (from editing view)
    const htmlBeforeSave = await getEditorHtml(page);
    console.log('\n=== HTML BEFORE SAVE (Editing View) ===');
    console.log(htmlBeforeSave);

    // CRITICAL: Get CKEditor's actual data output (what gets saved to DB)
    const frame = getModuleFrame(page);
    const dataBeforeSave = await frame.locator('.ck-editor__editable').evaluate((el: any) => {
      // Access CKEditor instance from the editable element
      const editor = el.ckeditorInstance;
      if (editor) {
        return editor.getData();
      }
      return 'NO CKEDITOR INSTANCE FOUND';
    });
    console.log('\n=== CKEDITOR getData() BEFORE SAVE ===');
    console.log(dataBeforeSave);

    // Save
    await saveContentElement(page);

    // Reload and get HTML
    await page.reload();
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    const htmlAfterReload = await getEditorHtml(page);
    console.log('\n=== HTML AFTER RELOAD (Editing View) ===');
    console.log(htmlAfterReload);

    // Get CKEditor's data after reload
    const dataAfterReload = await frame.locator('.ck-editor__editable').evaluate((el: any) => {
      const editor = el.ckeditorInstance;
      if (editor) {
        return editor.getData();
      }
      return 'NO CKEDITOR INSTANCE FOUND';
    });
    console.log('\n=== CKEDITOR getData() AFTER RELOAD ===');
    console.log(dataAfterReload);

    // Open dialog and get values
    await openImageEditDialog(page);
    const values = await getDialogValues(page);
    console.log('\n=== DIALOG VALUES AFTER RELOAD ===');
    console.log(JSON.stringify(values, null, 2));

    // Take screenshot
    await page.screenshot({ path: 'test-results/debug-html-structure.png', fullPage: true });

    // This test always passes - it's for debugging
    expect(true).toBe(true);
  });
});
