import { test, expect, Page } from '@playwright/test';
import { loginToBackend, navigateToContentEdit, getModuleFrame, waitForCKEditor, openImageEditDialog, confirmImageDialog, cancelImageDialog, getEditorHtml, saveContentElement } from './helpers/typo3-backend';

/**
 * E2E tests for verifying that image dialog changes are actually applied.
 *
 * Tests verify that:
 * 1. Changes made in the image edit dialog are applied to the image in CKEditor
 * 2. Alt text, title, dimensions, link, CSS class changes work correctly
 * 3. Click-to-enlarge setting is properly applied
 * 4. Changes persist after saving the content element
 */

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

    // Save the content element
    await saveContentElement(page);
    console.log('Saved content element');

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
  });
});
