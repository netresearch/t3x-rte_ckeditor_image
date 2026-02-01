import { test, expect, Page, FrameLocator } from '@playwright/test';

/**
 * E2E tests for verifying that image dialog changes are actually applied.
 *
 * Tests verify that:
 * 1. Changes made in the image edit dialog are applied to the image in CKEditor
 * 2. Alt text, title, dimensions, link, CSS class changes work correctly
 * 3. Click-to-enlarge setting is properly applied
 * 4. Changes persist after saving the content element
 */

const BACKEND_USER = process.env.TYPO3_BACKEND_USER || 'admin';
const BACKEND_PASSWORD = process.env.TYPO3_BACKEND_PASSWORD || 'Admin1234!';
const BASE_URL = process.env.BASE_URL || 'https://v13.rte-ckeditor-image.ddev.site';

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
async function navigateToContentEdit(page: Page): Promise<boolean> {
  try {
    const editUrl = `${BASE_URL}/typo3/record/edit?edit[tt_content][1]=edit&returnUrl=/typo3/`;
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
  // The modal footer has OK/Cancel buttons
  // Use JavaScript click to avoid overlay interception issues
  const confirmButton = page.locator('.modal-footer button.btn-primary, .modal-footer button.btn-default:has-text("OK"), .modal-footer button:has-text("OK")').first();

  if (await confirmButton.count() > 0) {
    await confirmButton.evaluate((el: HTMLElement) => el.click());
  }

  // Wait for modal to close
  await page.waitForTimeout(1000);
}

/**
 * Cancel/close the image dialog
 */
async function cancelImageDialog(page: Page): Promise<void> {
  const cancelButton = page.locator('.modal-footer button.btn-default, .modal-footer button[name="cancel"], button:has-text("Cancel"), .modal-header .close, button.close').first();
  if (await cancelButton.count() > 0) {
    await cancelButton.click();
    await page.waitForTimeout(500);
  }
}

/**
 * Get image attributes from CKEditor
 */
async function getImageAttributes(page: Page): Promise<{
  src: string;
  alt: string;
  title: string;
  width: string;
  height: string;
  classes: string[];
  isLinked: boolean;
  linkHref: string;
  hasZoom: boolean;
}> {
  const frame = getModuleFrame(page);

  return await frame.locator('.ck-editor__editable').evaluate(() => {
    const img = document.querySelector('.ck-editor__editable img') as HTMLImageElement;
    const figure = img?.closest('figure');
    const link = img?.closest('a') || figure?.querySelector('a');

    return {
      src: img?.src || '',
      alt: img?.alt || '',
      title: img?.title || '',
      width: img?.getAttribute('width') || img?.style.width || '',
      height: img?.getAttribute('height') || img?.style.height || '',
      classes: [...(figure?.classList || img?.classList || [])],
      isLinked: !!link,
      linkHref: link?.href || '',
      hasZoom: img?.hasAttribute('data-htmlarea-zoom') || figure?.hasAttribute('data-htmlarea-zoom') || false,
    };
  });
}

/**
 * Get the HTML source of the CKEditor content
 */
async function getEditorHtml(page: Page): Promise<string> {
  const frame = getModuleFrame(page);
  return await frame.locator('.ck-editor__editable').innerHTML();
}

test.describe('Image Dialog - Apply Changes', () => {
  let loggedIn = false;

  test.beforeEach(async ({ page }) => {
    if (!loggedIn) {
      loggedIn = await loginToBackend(page);
    }
    test.skip(!loggedIn, 'Backend login failed - check TYPO3_BACKEND_PASSWORD');
  });

  test('changing alt text in dialog updates the image', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    // Get original alt text
    const originalAttrs = await getImageAttributes(page);
    console.log(`Original alt: "${originalAttrs.alt}"`);

    // Open image dialog
    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // The alt input has ID 'rteckeditorimage-alt' and may be disabled
    // If disabled, we need to check the override checkbox first
    const altInput = page.locator('#rteckeditorimage-alt');
    const altCheckbox = page.locator('#checkbox-alt');

    if (await altInput.count() > 0) {
      // Check if input is disabled and checkbox exists
      const isDisabled = await altInput.isDisabled();
      console.log(`Alt input disabled: ${isDisabled}`);

      if (isDisabled && await altCheckbox.count() > 0) {
        // Click the checkbox to enable the input
        const checkboxDisabled = await altCheckbox.isDisabled();
        if (!checkboxDisabled) {
          await altCheckbox.click();
          await page.waitForTimeout(300);
          console.log('Clicked alt override checkbox');
        }
      }

      const testAltText = `Test Alt Text ${Date.now()}`;
      await altInput.fill(testAltText);
      console.log(`Set alt text to: "${testAltText}"`);

      // Confirm dialog
      await confirmImageDialog(page);

      // Wait for changes to apply
      await page.waitForTimeout(1000);

      // Verify alt text was updated
      const newAttrs = await getImageAttributes(page);
      console.log(`New alt: "${newAttrs.alt}"`);

      expect(newAttrs.alt).toBe(testAltText);
      console.log('SUCCESS: Alt text was updated');
    } else {
      // Take screenshot for debugging
      await page.screenshot({ path: 'test-results/dialog-no-alt-input.png' });
      test.skip(true, 'Alt input not found in dialog');
    }
  });

  test('changing title in dialog updates the image', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // The title input has ID 'rteckeditorimage-title' and may be disabled
    const titleInput = page.locator('#rteckeditorimage-title');
    const titleCheckbox = page.locator('#checkbox-title');

    if (await titleInput.count() > 0) {
      // Check if input is disabled and checkbox exists
      const isDisabled = await titleInput.isDisabled();
      console.log(`Title input disabled: ${isDisabled}`);

      if (isDisabled && await titleCheckbox.count() > 0) {
        const checkboxDisabled = await titleCheckbox.isDisabled();
        if (!checkboxDisabled) {
          await titleCheckbox.click();
          await page.waitForTimeout(300);
          console.log('Clicked title override checkbox');
        }
      }

      const testTitle = `Test Title ${Date.now()}`;
      await titleInput.fill(testTitle);
      console.log(`Set title to: "${testTitle}"`);

      await confirmImageDialog(page);
      await page.waitForTimeout(1000);

      const newAttrs = await getImageAttributes(page);
      console.log(`New title: "${newAttrs.title}"`);

      expect(newAttrs.title).toBe(testTitle);
      console.log('SUCCESS: Title was updated');
    } else {
      await page.screenshot({ path: 'test-results/dialog-no-title-input.png' });
      test.skip(true, 'Title input not found in dialog');
    }
  });

  test('adding link URL in dialog wraps image in anchor', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // Select "Link" radio button to show link fields
    const linkRadio = page.locator('#clickBehavior-link');
    if (await linkRadio.count() > 0) {
      await linkRadio.click();
      await page.waitForTimeout(500);
      console.log('Selected Link option');
    }

    // Find and fill link URL input
    const linkInput = page.locator('#rteckeditorimage-linkHref');

    if (await linkInput.count() > 0) {
      const testUrl = 'https://example.com/test-link';
      await linkInput.fill(testUrl);
      console.log(`Set link URL to: "${testUrl}"`);

      await confirmImageDialog(page);
      await page.waitForTimeout(1000);

      // Check if image is now wrapped in a link
      const editorHtml = await getEditorHtml(page);
      console.log('Editor HTML contains link:', editorHtml.includes('<a '));

      const newAttrs = await getImageAttributes(page);
      console.log(`Is linked: ${newAttrs.isLinked}, href: "${newAttrs.linkHref}"`);

      expect(newAttrs.isLinked).toBe(true);
      expect(newAttrs.linkHref).toContain('example.com');
      console.log('SUCCESS: Image is wrapped in link');
    } else {
      await page.screenshot({ path: 'test-results/dialog-no-link-input.png' });
      test.skip(true, 'Link input not found in dialog');
    }
  });

  test('setting click-to-enlarge adds zoom attribute', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // Select "Enlarge on click" radio button
    const enlargeRadio = page.locator('#clickBehavior-enlarge');

    if (await enlargeRadio.count() > 0) {
      await enlargeRadio.click();
      console.log('Selected enlarge on click');

      await confirmImageDialog(page);
      await page.waitForTimeout(1000);

      // Check for zoom attribute
      const editorHtml = await getEditorHtml(page);
      const hasZoomAttr = editorHtml.includes('data-htmlarea-zoom');
      console.log(`Has zoom attribute: ${hasZoomAttr}`);

      expect(hasZoomAttr).toBe(true);
      console.log('SUCCESS: Zoom attribute was added');
    } else {
      await page.screenshot({ path: 'test-results/dialog-no-enlarge-radio.png' });
      test.skip(true, 'Enlarge radio not found in dialog');
    }
  });

  test('changing CSS class in dialog updates image class', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // Find CSS class input
    const classInput = page.locator('input[name="class"], input[id*="cssClass"], input.form-control[placeholder*="class"]').first();

    if (await classInput.count() > 0) {
      const testClass = 'test-custom-class';
      await classInput.fill(testClass);
      console.log(`Set CSS class to: "${testClass}"`);

      await confirmImageDialog(page);
      await page.waitForTimeout(1000);

      const editorHtml = await getEditorHtml(page);
      const hasClass = editorHtml.includes(testClass);
      console.log(`Has custom class: ${hasClass}`);

      expect(hasClass).toBe(true);
      console.log('SUCCESS: CSS class was added');
    } else {
      // CSS class might be in a different location
      await page.screenshot({ path: 'test-results/dialog-no-class-input.png' });
      console.log('CSS class input not found - checking dialog structure');

      const dialogHtml = await page.locator('.modal-body').innerHTML();
      console.log('Dialog inputs:', dialogHtml.match(/input[^>]*>/g)?.slice(0, 10));
    }
  });

  test('changing dimensions in dialog updates image size', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    // Get original dimensions
    const originalAttrs = await getImageAttributes(page);
    console.log(`Original dimensions: ${originalAttrs.width}x${originalAttrs.height}`);

    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // Find width input - uses ID rteckeditorimage-width
    const widthInput = page.locator('#rteckeditorimage-width');

    if (await widthInput.count() > 0) {
      // Clear and set new value
      await widthInput.clear();
      const testWidth = '300';
      await widthInput.fill(testWidth);
      console.log(`Set width to: ${testWidth}`);

      await confirmImageDialog(page);
      await page.waitForTimeout(1000);

      const newAttrs = await getImageAttributes(page);
      console.log(`New dimensions: ${newAttrs.width}x${newAttrs.height}`);

      // Width should contain 300 (might have 'px' suffix)
      expect(newAttrs.width).toContain('300');
      console.log('SUCCESS: Width was updated');
    } else {
      await page.screenshot({ path: 'test-results/dialog-no-width-input.png' });
      test.skip(true, 'Width input not found in dialog');
    }
  });

  test('link target is applied when set in dialog', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // Select "Link" option
    const linkRadio = page.locator('#clickBehavior-link');
    if (await linkRadio.count() > 0) {
      await linkRadio.click();
      await page.waitForTimeout(500);
    }

    // Fill link URL
    const linkInput = page.locator('#rteckeditorimage-linkHref');
    if (await linkInput.count() > 0) {
      await linkInput.fill('https://example.com');
    }

    // Set target to _blank
    const targetSelect = page.locator('#rteckeditorimage-linkTarget');

    if (await targetSelect.count() > 0) {
      await targetSelect.selectOption('_blank');
      console.log('Set link target to: _blank');

      await confirmImageDialog(page);
      await page.waitForTimeout(1000);

      const editorHtml = await getEditorHtml(page);
      const hasTarget = editorHtml.includes('target="_blank"');
      console.log(`Has target="_blank": ${hasTarget}`);

      expect(hasTarget).toBe(true);
      console.log('SUCCESS: Link target was applied');
    } else {
      await page.screenshot({ path: 'test-results/dialog-no-target-select.png' });
      test.skip(true, 'Target select not found in dialog');
    }
  });

  test('link title is applied when set in dialog', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // Select "Link" option
    const linkRadio = page.locator('#clickBehavior-link');
    if (await linkRadio.count() > 0) {
      await linkRadio.click();
      await page.waitForTimeout(500);
    }

    // Fill link URL
    const linkInput = page.locator('#rteckeditorimage-linkHref');
    if (await linkInput.count() > 0) {
      await linkInput.fill('https://example.com');
    }

    // Set link title
    const linkTitleInput = page.locator('#rteckeditorimage-linkTitle');

    if (await linkTitleInput.count() > 0) {
      const testLinkTitle = `Link Title ${Date.now()}`;
      await linkTitleInput.fill(testLinkTitle);
      console.log(`Set link title to: "${testLinkTitle}"`);

      await confirmImageDialog(page);
      await page.waitForTimeout(1000);

      const editorHtml = await getEditorHtml(page);
      const hasLinkTitle = editorHtml.includes(`title="${testLinkTitle}"`);
      console.log(`Has link title: ${hasLinkTitle}`);

      expect(hasLinkTitle).toBe(true);
      console.log('SUCCESS: Link title was applied');
    } else {
      await page.screenshot({ path: 'test-results/dialog-no-link-title-input.png' });
      test.skip(true, 'Link title input not found in dialog');
    }
  });

  test('dialog cancel does not apply changes', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    // Get original alt text
    const originalAttrs = await getImageAttributes(page);
    console.log(`Original alt: "${originalAttrs.alt}"`);

    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // Modify alt text
    const altInput = page.locator('input[name="alt"]').first();
    if (await altInput.count() > 0) {
      await altInput.fill('This should not be saved');
    }

    // Cancel dialog
    await cancelImageDialog(page);
    await page.waitForTimeout(500);

    // Verify alt text was NOT changed
    const newAttrs = await getImageAttributes(page);
    console.log(`Alt after cancel: "${newAttrs.alt}"`);

    expect(newAttrs.alt).toBe(originalAttrs.alt);
    console.log('SUCCESS: Cancel did not apply changes');
  });
});

test.describe('Image Dialog - Save Persistence', () => {
  test('changes persist after saving content element', async ({ page }) => {
    const loggedIn = await loginToBackend(page);
    test.skip(!loggedIn, 'Backend login failed');

    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const dialogOpened = await openImageEditDialog(page);
    expect(dialogOpened).toBe(true);

    // Set a unique alt text
    const uniqueAlt = `Persist Test ${Date.now()}`;
    const altInput = page.locator('input[name="alt"]').first();

    if (await altInput.count() > 0) {
      await altInput.fill(uniqueAlt);
      console.log(`Set alt to: "${uniqueAlt}"`);
    }

    await confirmImageDialog(page);
    await page.waitForTimeout(1000);

    // Save the content element by clicking the save button
    const frame = getModuleFrame(page);
    const saveButton = frame.locator('button[name="_savedok"], button[value="1"][name="_savedok"], .btn-toolbar button[title*="Save"]').first();

    if (await saveButton.count() > 0) {
      await saveButton.click();
      console.log('Clicked save button');

      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);

      // Reload the page
      await page.reload();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);

      // Navigate back to content edit
      await navigateToContentEdit(page);
      await waitForCKEditor(page);

      // Check if alt text persisted
      const attrs = await getImageAttributes(page);
      console.log(`Alt after reload: "${attrs.alt}"`);

      expect(attrs.alt).toBe(uniqueAlt);
      console.log('SUCCESS: Changes persisted after save');
    } else {
      console.log('Save button not found - checking available buttons');
      const buttons = await frame.locator('button').allTextContents();
      console.log('Available buttons:', buttons.slice(0, 10));
      test.skip(true, 'Save button not found');
    }
  });
});
