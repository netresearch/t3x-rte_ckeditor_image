import { test, expect, Page, FrameLocator } from '@playwright/test';

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

const BACKEND_USER = process.env.TYPO3_BACKEND_USER || 'admin';
const BACKEND_PASSWORD = process.env.TYPO3_BACKEND_PASSWORD || '';
const BASE_URL = process.env.BASE_URL || 'https://v13.rte-ckeditor-image.ddev.site';

/**
 * Check if backend credentials are configured.
 */
function hasBackendCredentials(): boolean {
  return !!process.env.TYPO3_BACKEND_PASSWORD && process.env.TYPO3_BACKEND_PASSWORD.length > 0;
}

/**
 * Login to TYPO3 backend
 */
async function loginToBackend(page: Page): Promise<boolean> {
  try {
    await page.goto(`${BASE_URL}/typo3/`, { timeout: 30000 });

    const loginForm = page.locator('form[name="loginform"], #typo3-login-form, input[name="username"], #t3-username');
    const isLoginPage = await loginForm.count() > 0;

    if (!isLoginPage) {
      return true; // Already logged in
    }

    const usernameInput = page.locator('input[name="username"], #t3-username').first();
    const passwordInput = page.locator('input[name="p_field"], input[name="password"], #t3-password').first();

    await usernameInput.fill(BACKEND_USER);
    await passwordInput.fill(BACKEND_PASSWORD);
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle', { timeout: 30000 });

    const backendIndicators = page.locator('.modulemenu, .typo3-module-menu, [data-modulemenu], .scaffold');
    return await backendIndicators.count() > 0;
  } catch (error) {
    console.log('Backend login failed:', error);
    return false;
  }
}

/**
 * Navigate to content element edit form
 */
async function navigateToContentEdit(page: Page, contentId: number = 1): Promise<boolean> {
  try {
    const editUrl = `${BASE_URL}/typo3/record/edit?edit[tt_content][${contentId}]=edit&returnUrl=/typo3/`;
    await page.goto(editUrl, { timeout: 30000 });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const moduleFrame = page.frameLocator('iframe').first();
    await moduleFrame.locator('.ck-editor__editable, .ck-content').first().waitFor({ timeout: 20000 });
    return true;
  } catch (error) {
    console.log('Failed to navigate to content edit:', error);
    return false;
  }
}

/**
 * Get the module frame locator
 */
function getModuleFrame(page: Page): FrameLocator {
  return page.frameLocator('iframe').first();
}

/**
 * Wait for CKEditor to be ready
 */
async function waitForCKEditor(page: Page): Promise<void> {
  const frame = getModuleFrame(page);
  await frame.locator('.ck-editor__editable').first().waitFor({ timeout: 15000 });
  await page.waitForTimeout(1000);
}

/**
 * Double-click image to open edit dialog
 */
async function openImageEditDialog(page: Page): Promise<boolean> {
  const frame = getModuleFrame(page);
  const image = frame.locator('.ck-editor__editable img');
  if (await image.count() > 0) {
    await image.first().dblclick();
    await page.waitForSelector('.modal-dialog, .t3js-modal', { timeout: 10000 });
    await page.waitForTimeout(500); // Wait for dialog to fully render
    return true;
  }
  return false;
}

/**
 * Close the image dialog by clicking the confirm/save button
 */
async function confirmImageDialog(page: Page): Promise<void> {
  const confirmButton = page.locator('.modal-footer button.btn-primary, .modal-footer button.btn-default:has-text("OK"), .modal-footer button:has-text("OK")').first();

  if (await confirmButton.count() > 0) {
    await confirmButton.evaluate((el: HTMLElement) => el.click());
  }

  // Wait for modal to close
  await page.waitForTimeout(1000);
}

/**
 * Save the content element
 */
async function saveContentElement(page: Page): Promise<void> {
  const frame = getModuleFrame(page);

  // Look for save button in the docheader
  const saveButton = frame.locator('button[name="_savedok"], button[value="1"][name="_savedok"], .t3js-editform-submitButton').first();

  if (await saveButton.count() > 0) {
    await saveButton.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);
  }
}

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

/**
 * Get the HTML from CKEditor to inspect link attributes
 */
async function getEditorHtml(page: Page): Promise<string> {
  const frame = getModuleFrame(page);
  return await frame.locator('.ck-editor__editable').innerHTML();
}

test.describe('Link Attributes Round-Trip Persistence', () => {
  test.beforeEach(async ({ page }) => {
    const loggedIn = await loginToBackend(page);
    test.skip(!loggedIn, 'Backend login failed - check credentials');
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
    const editLoaded = await navigateToContentEdit(page);
    test.skip(!editLoaded, 'Could not load content edit form');
    await waitForCKEditor(page);

    // Step 2: Open image dialog
    const dialogOpened = await openImageEditDialog(page);
    test.skip(!dialogOpened, 'Could not open image dialog - no image found');

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
    const reloadedEdit = await navigateToContentEdit(page);
    test.skip(!reloadedEdit, 'Could not reload content edit form');
    await waitForCKEditor(page);

    // Step 9: Check HTML in editor after reload
    const htmlAfterReload = await getEditorHtml(page);
    console.log('HTML after reload:', htmlAfterReload.substring(0, 500));

    // Step 10: Open image dialog again
    const dialogReopened = await openImageEditDialog(page);
    test.skip(!dialogReopened, 'Could not reopen image dialog');

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
    const editLoaded = await navigateToContentEdit(page);
    test.skip(!editLoaded, 'Could not load content edit form');
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
    const dialogOpened = await openImageEditDialog(page);
    test.skip(!dialogOpened, 'Could not open image dialog');

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
    await navigateToContentEdit(page);
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

    const editLoaded = await navigateToContentEdit(page);
    test.skip(!editLoaded, 'Could not load content edit form');
    await waitForCKEditor(page);

    // Set up a link with all attributes
    const dialogOpened = await openImageEditDialog(page);
    test.skip(!dialogOpened, 'Could not open image dialog');

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
    await navigateToContentEdit(page);
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
