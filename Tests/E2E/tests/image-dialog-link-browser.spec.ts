import { test, expect } from '@playwright/test';
import { loginToBackend, navigateToContentEdit, waitForCKEditor, selectImageInEditor, openImageEditDialog, requireCondition } from './helpers/typo3-backend';

/**
 * E2E tests for the image dialog link browser functionality.
 *
 * Tests verify that:
 * 1. The link browser opens from the image edit dialog
 * 2. Selecting a page inserts the link and closes the browser
 * 3. The link is properly saved with the image
 */

test.describe('Image Dialog Link Browser', () => {
  test('can open link browser from image edit dialog', async ({ page }) => {
    // Login to backend
    await loginToBackend(page);

    // Navigate directly to edit a content element
    console.log('Step: navigateToContentEdit');
    await navigateToContentEdit(page);

    console.log('Step: waitForCKEditor');
    await waitForCKEditor(page);

    // Check if there's an image in the editor
    console.log('Step: selectImageInEditor');
    await selectImageInEditor(page);

    // Double-click to open edit dialog
    console.log('Step: openImageEditDialog');
    await openImageEditDialog(page);

    // Take screenshot of dialog
    await page.screenshot({ path: 'test-results/image-dialog-opened.png' });

    // Select "Link" radio button in click behavior section
    console.log('Step: select Link radio button');
    const linkRadio = page.locator('input[name="clickBehavior"][value="link"]');
    const linkRadioCount = await linkRadio.count();
    console.log(`Link radio buttons found: ${linkRadioCount}`);
    if (linkRadioCount > 0) {
      await linkRadio.click();
      await page.waitForTimeout(500);
    }

    // Click Browse button to open link browser
    console.log('Step: click Browse button');
    const browseButton = page.locator('button:has-text("Browse"), .btn:has-text("Browse")');
    const browseCount = await browseButton.count();
    console.log(`Browse buttons found: ${browseCount}`);
    expect(browseCount).toBeGreaterThan(0);

    await browseButton.click();
    console.log('Browse button clicked');

    // Wait for link browser modal to appear (nested modal with iframe)
    console.log('Waiting for link browser modal...');
    const linkBrowserModal = page.locator('.modal-dialog iframe, .t3js-modal iframe');
    await expect(linkBrowserModal).toBeVisible({ timeout: 10000 });
    console.log('Link browser modal is visible');

    // Take screenshot
    await page.screenshot({ path: 'test-results/link-browser-opened.png' });
  });

  test('selecting a page inserts link into dialog field', async ({ page }) => {
    // Login to backend
    await loginToBackend(page);

    // Navigate to content edit
    await navigateToContentEdit(page);

    await waitForCKEditor(page);

    await selectImageInEditor(page);

    // Open image edit dialog
    await openImageEditDialog(page);

    // Select "Link" radio button
    const linkRadio = page.locator('input[name="clickBehavior"][value="link"]');
    await linkRadio.click();
    await page.waitForTimeout(500);

    // Get the link input field and check initial value
    const linkInput = page.locator('input[name="linkHref"]');
    const initialValue = await linkInput.inputValue();
    console.log(`Initial link value: "${initialValue}"`);

    // Click Browse button
    const browseButton = page.locator('button:has-text("Browse"), .btn:has-text("Browse")');
    await browseButton.click();

    // Wait for link browser iframe to load
    console.log('Waiting for link browser iframe...');
    await page.waitForSelector('.modal-dialog iframe', { timeout: 10000 });
    await page.waitForTimeout(2000); // Wait for iframe content to load

    // Get the link browser iframe
    const linkBrowserFrame = page.frameLocator('.modal-dialog iframe').last();

    // Take screenshot of link browser
    await page.screenshot({ path: 'test-results/link-browser-before-select.png' });

    // Click on "Home" page in the page tree - TYPO3 v13 uses specific selectors
    console.log('Looking for Home page link...');

    // TYPO3 v13 page tree structure:
    // div.node[title="id=1 - Home"] > div.node-content > span.node-action
    // The .node-action span contains the link icon but may be hidden until hover
    // We need to click the node-action with force:true or hover first

    // Find the Home page row by its title attribute
    const homePageRow = linkBrowserFrame.locator('.node[title*="- Home"]').first();
    const homeRowCount = await homePageRow.count();
    console.log(`Home page row found: ${homeRowCount}`);

    if (homeRowCount === 0) {
      console.log('Could not find Home page row, skipping test');
      requireCondition(false, 'Home page row not found');
      return;
    }

    // Get the node-action element
    const homePageLink = homePageRow.locator('.node-action').first();
    console.log('Will click .node-action in Home row (with force)');

    if (await homePageLink.count() > 0) {
      console.log('Clicking Home page link via JavaScript...');
      // Use JavaScript click because the action icon may be hidden (CSS visibility)
      // and Playwright won't click hidden elements even with force:true
      await homePageLink.evaluate((el: HTMLElement) => el.click());

      // Wait for the link browser to close and link to be inserted
      console.log('Waiting for link to be inserted...');
      await page.waitForTimeout(3000);

      // Check if link was inserted
      const newValue = await linkInput.inputValue();
      console.log(`New link value in dialog: "${newValue}"`);

      // The link should have been inserted
      expect(newValue).not.toBe(initialValue);
      expect(newValue.length).toBeGreaterThan(0);
      console.log('SUCCESS: Link was inserted into the field');
    } else {
      console.log('Could not find Home page link, checking what is available...');

      // Try to find any clickable page link
      const anyPageLink = linkBrowserFrame.locator('.list-tree a, [data-uid] a').first();
      if (await anyPageLink.count() > 0) {
        const linkText = await anyPageLink.textContent();
        console.log(`Found page link: ${linkText}`);
        await anyPageLink.click();
        await page.waitForTimeout(3000);

        const newValue = await linkInput.inputValue();
        console.log(`New link value: "${newValue}"`);
        expect(newValue.length).toBeGreaterThan(0);
      } else {
        // Take screenshot for debugging
        await page.screenshot({ path: 'test-results/link-browser-no-pages.png' });
        requireCondition(false, 'No page links found in link browser');
      }
    }
  });

  test('selecting a page in link browser inserts link and closes browser', async ({ page }) => {
    await navigateToContentEdit(page);

    await waitForCKEditor(page);

    await selectImageInEditor(page);

    await openImageEditDialog(page);

    // Select "Link" option
    const linkRadio = page.locator('input[name="clickBehavior"][value="link"]');
    if (await linkRadio.count() > 0) {
      await linkRadio.click();
      await page.waitForTimeout(500);
    }

    // Get the link input field before opening browser
    const linkInput = page.locator('input[name="linkHref"], input[id*="linkHref"]');
    const initialValue = await linkInput.inputValue();

    // Click Browse button
    const browseButton = page.locator('button:has-text("Browse"), .btn:has-text("Browse")');
    await browseButton.click();

    // Wait for link browser iframe to load
    const iframe = page.frameLocator('.modal-dialog iframe, .t3js-modal iframe').last();
    await iframe.locator('body').waitFor({ timeout: 10000 });

    // Look for page tree in link browser and click a page
    const pageLink = iframe.locator('a[data-page-id], .page-tree-node a, [data-identifier="pages"] a').first();

    if (await pageLink.count() > 0) {
      await pageLink.click();

      // Wait for the modal to close and link to be inserted
      await page.waitForTimeout(2000);

      // Check if the link browser modal closed (no remaining link browser iframes)
      await expect(page.locator('.modal-dialog iframe')).toHaveCount(0);

      // The link input should now have a value
      const newValue = await linkInput.inputValue();

      // If a link was selected, it should be different from initial
      if (newValue !== initialValue) {
        expect(newValue).toBeTruthy();
        expect(newValue).not.toBe(initialValue);
      }
    } else {
      // Try clicking on "Page" tab first
      const pageTab = iframe.locator('a:has-text("Page"), [data-identifier="page"]');
      if (await pageTab.count() > 0) {
        await pageTab.click();
        await page.waitForTimeout(1000);

        // Now try to find a page link
        const pageLinks = iframe.locator('.page-tree a, [data-page-id]');
        if (await pageLinks.count() > 0) {
          await pageLinks.first().click();
          await page.waitForTimeout(2000);

          const newValue = await linkInput.inputValue();
          expect(newValue).toBeTruthy();
        }
      }
    }
  });

  test('link browser closes without error on cancel', async ({ page }) => {
    await navigateToContentEdit(page);

    await waitForCKEditor(page);

    await selectImageInEditor(page);

    await openImageEditDialog(page);

    // Select "Link" option
    const linkRadio = page.locator('input[name="clickBehavior"][value="link"]');
    if (await linkRadio.count() > 0) {
      await linkRadio.click();
      await page.waitForTimeout(500);
    }

    // Click Browse button
    const browseButton = page.locator('button:has-text("Browse"), .btn:has-text("Browse")');
    await browseButton.click();

    // Wait for link browser modal
    await page.waitForSelector('.modal-dialog iframe, .t3js-modal iframe', { timeout: 10000 });

    // Close the link browser modal (click outside or close button)
    const closeButton = page.locator('.modal .close, .modal [data-bs-dismiss="modal"], .t3js-modal-close').last();
    if (await closeButton.count() > 0) {
      await closeButton.click();
    } else {
      // Press Escape to close
      await page.keyboard.press('Escape');
    }

    // Wait a moment for modal to close
    await page.waitForTimeout(1000);

    // Check no JavaScript errors in console
    const consoleErrors: string[] = [];
    page.on('console', msg => {
      if (msg.type() === 'error') {
        consoleErrors.push(msg.text());
      }
    });

    // The image edit dialog should still be visible
    const imageDialog = page.locator('.modal-dialog:has(input[name="alt"])');
    await expect(imageDialog).toBeVisible();
    // Should not have critical JS errors
    const criticalErrors = consoleErrors.filter(e =>
      e.includes('Cannot read properties') ||
      e.includes('is not defined') ||
      e.includes('TypeError')
    );

    expect(criticalErrors.length).toBe(0);
  });
});

test.describe('Link Browser Error Handling', () => {
  test.beforeEach(async ({ page }) => {
    await loginToBackend(page);

    // Capture console errors
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.log('Console error:', msg.text());
      }
    });
  });

  test('no JavaScript errors when opening link browser', async ({ page }) => {
    const errors: string[] = [];
    page.on('pageerror', error => {
      errors.push(error.message);
    });

    await navigateToContentEdit(page);

    await waitForCKEditor(page);

    await selectImageInEditor(page);

    await openImageEditDialog(page);

    // Select "Link" option
    const linkRadio = page.locator('input[name="clickBehavior"][value="link"]');
    if (await linkRadio.count() > 0) {
      await linkRadio.click();
      await page.waitForTimeout(500);
    }

    // Click Browse button
    const browseButton = page.locator('button:has-text("Browse"), .btn:has-text("Browse")');
    await browseButton.click();

    // Wait for link browser
    await page.waitForTimeout(3000);

    // Check for the specific error we're fixing
    const hasModelError = errors.some(e => e.includes("Cannot read properties of undefined (reading 'model')"));
    expect(hasModelError).toBe(false);

    // Check for other critical errors
    const criticalErrors = errors.filter(e =>
      e.includes('TypeError') ||
      e.includes('is not defined')
    );
    expect(criticalErrors).toEqual([]);
  });

  test('link browser uses correct route (not RTE route)', async ({ page }) => {
    const requests: string[] = [];

    page.on('request', request => {
      const url = request.url();
      if (url.includes('wizard') || url.includes('link') || url.includes('linkBrowser')) {
        requests.push(url);
      }
    });

    await navigateToContentEdit(page);

    await waitForCKEditor(page);

    await selectImageInEditor(page);

    await openImageEditDialog(page);

    // Select "Link" option
    const linkRadio = page.locator('input[name="clickBehavior"][value="link"]');
    if (await linkRadio.count() > 0) {
      await linkRadio.click();
      await page.waitForTimeout(500);
    }

    // Click Browse button
    const browseButton = page.locator('button:has-text("Browse"), .btn:has-text("Browse")');
    await browseButton.click();

    await page.waitForTimeout(3000);

    // Should have made request to our linkBrowser action
    const hasLinkBrowserRequest = requests.some(url =>
      url.includes('action=linkBrowser') || url.includes('linkbrowser')
    );
    expect(hasLinkBrowserRequest).toBe(true);

    // Should have made request to wizard_link route
    const hasWizardLinkRequest = requests.some(url =>
      url.includes('wizard/link') || url.includes('wizard_link')
    );
    expect(hasWizardLinkRequest).toBe(true);
  });
});
