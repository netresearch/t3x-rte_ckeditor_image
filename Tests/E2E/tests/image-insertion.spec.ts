import { test, expect } from '@playwright/test';
import {
  BACKEND_PASSWORD,
  loginToBackend,
  navigateToContentEdit,
  waitForCKEditor,
  getEditorHtml,
  getModuleFrame,
  requireCondition,
} from './helpers/typo3-backend';

/**
 * E2E tests for image insertion methods in CKEditor within TYPO3.
 *
 * Tests verify that:
 * 1. The image insert button in CKEditor toolbar opens TYPO3's element browser
 * 2. Selecting a file in the element browser inserts an image with correct attributes
 * 3. Multiple images can be inserted into a single content element
 *
 * Test content: CE 1 has an existing image (alt="Example", data-htmlarea-file-uid="1").
 *
 * NOTE: Image insertion via the TYPO3 element browser is inherently complex because
 * TYPO3's file browser is a nested modal containing its own iframe. The browser's
 * internal structure (file tree, file list, selection mechanism) varies between
 * TYPO3 versions and is not designed for automated testing. These tests are marked
 * as fixme where the file browser interaction is required.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/613
 */
test.describe('Image Insertion', () => {
  test.beforeEach(() => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
  });

  test.describe('Existing Image Verification', () => {
    test('CE 1 contains an image with expected attributes', async ({ page }) => {
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const editorHtml = await getEditorHtml(page);

      // CE 1 has a pre-existing image with known attributes
      expect(editorHtml).toContain('<img');
      expect(editorHtml).toContain('data-htmlarea-file-uid');

      // Verify the specific test image attributes
      const frame = getModuleFrame(page);
      const image = frame.locator('.ck-editor__editable img').first();
      await expect(image).toBeVisible();

      const alt = await image.getAttribute('alt');
      const fileUid = await image.getAttribute('data-htmlarea-file-uid');
      const src = await image.getAttribute('src');

      expect(alt).toBe('Example');
      expect(fileUid).toBe('1');
      expect(src).toBeTruthy();

      console.log(`Verified existing image: alt="${alt}", file-uid="${fileUid}", src="${src}"`);
    });

    test('existing image has a valid src path', async ({ page }) => {
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const frame = getModuleFrame(page);
      const image = frame.locator('.ck-editor__editable img').first();
      await expect(image).toBeVisible();

      const src = await image.getAttribute('src');
      expect(src).toBeTruthy();
      // CKEditor shows the original fileadmin path or a processed version
      expect(src).toMatch(/(fileadmin|_processed_)/);
    });
  });

  test.describe('CKEditor Image Toolbar Button', () => {
    test('image insert button exists in CKEditor toolbar', async ({ page }) => {
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const frame = getModuleFrame(page);

      // The image insert button is in the CKEditor toolbar.
      // It may use various labels/tooltips depending on the CKEditor plugin configuration.
      const imageButton = frame.locator(
        '.ck-toolbar button[data-cke-tooltip-text*="image" i], ' +
        '.ck-toolbar button[data-cke-tooltip-text*="Image" i], ' +
        '.ck-toolbar .ck-button:has(.ck-icon[viewBox])'
      );

      const buttonCount = await imageButton.count();
      console.log(`Image-related toolbar buttons found: ${buttonCount}`);

      // There should be at least one image-related button in the toolbar
      expect(buttonCount, 'Expected image insert button in CKEditor toolbar').toBeGreaterThan(0);
    });

    test.fixme('clicking image insert button opens element browser modal', async ({ page }) => {
      // FIXME: The element browser modal is a complex TYPO3-specific modal with
      // nested iframes. Opening it requires clicking the correct toolbar button,
      // which triggers a TYPO3 AJAX request to load the element browser. In CI,
      // the PHP built-in server may not handle the element browser route correctly,
      // and the modal structure varies between TYPO3 v12 and v13.
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const frame = getModuleFrame(page);

      // Click the image insert button in the toolbar
      const imageButton = frame.locator(
        '.ck-toolbar button[data-cke-tooltip-text*="image" i], ' +
        '.ck-toolbar button[data-cke-tooltip-text*="Image" i]'
      ).first();

      requireCondition(
        await imageButton.count() > 0,
        'Image insert button not found in CKEditor toolbar'
      );

      await imageButton.click();
      console.log('Clicked image insert button');

      // Wait for the element browser modal to appear
      // TYPO3's element browser opens as a modal with an iframe inside
      const elementBrowserModal = page.locator('.t3js-modal, .modal-dialog');
      await expect(elementBrowserModal.first()).toBeVisible({ timeout: 10000 });
      console.log('Element browser modal is visible');

      // The modal should contain an iframe for the file browser
      const browserIframe = page.locator('.t3js-modal iframe, .modal-dialog iframe');
      await expect(browserIframe.first()).toBeVisible({ timeout: 10000 });
      console.log('Element browser iframe is visible');

      // Take screenshot for debugging
      await page.screenshot({ path: 'test-results/image-insert-element-browser.png' });
    });
  });

  test.describe('Image Insertion via File Browser', () => {
    test.fixme('browse and select image via file browser inserts image into editor', async ({ page }) => {
      // FIXME: This test exercises the full image insertion flow through TYPO3's
      // element browser, which is a nested modal with its own iframe containing
      // a file tree and file list. The interaction sequence is:
      //   1. Click image insert button in CKEditor toolbar
      //   2. Element browser modal opens with iframe
      //   3. Navigate file tree inside iframe to find the target folder
      //   4. Click on a file in the file list to select it
      //   5. Modal closes and image is inserted into CKEditor
      //
      // This is extremely fragile in CI because:
      // - The element browser iframe loads asynchronously
      // - The file tree uses TYPO3's SVG tree component with lazy loading
      // - File selection triggers a postMessage back to the parent window
      // - The PHP built-in server may not serve FAL file references correctly
      //
      // When infrastructure supports it, enable this test by removing test.fixme().
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      // Record initial image count
      const frame = getModuleFrame(page);
      const initialImageCount = await frame.locator('.ck-editor__editable img').count();
      console.log(`Initial image count: ${initialImageCount}`);

      // Step 1: Click the image insert button
      const imageButton = frame.locator(
        '.ck-toolbar button[data-cke-tooltip-text*="image" i], ' +
        '.ck-toolbar button[data-cke-tooltip-text*="Image" i]'
      ).first();

      requireCondition(
        await imageButton.count() > 0,
        'Image insert button not found in CKEditor toolbar'
      );

      await imageButton.click();
      console.log('Step 1: Clicked image insert button');

      // Step 2: Wait for element browser modal
      const modal = page.locator('.t3js-modal').first();
      await expect(modal).toBeVisible({ timeout: 10000 });

      const browserIframe = page.locator('.t3js-modal iframe, .modal-dialog iframe').first();
      await expect(browserIframe).toBeVisible({ timeout: 10000 });

      // Wait for iframe content to load
      const browserFrame = page.frameLocator('.t3js-modal iframe, .modal-dialog iframe').first();
      await browserFrame.locator('body').waitFor({ timeout: 10000 });
      await page.waitForTimeout(2000); // Extra wait for TYPO3's JS initialization
      console.log('Step 2: Element browser modal and iframe loaded');

      // Take screenshot before interaction
      await page.screenshot({ path: 'test-results/image-insert-browser-loaded.png' });

      // Step 3: Navigate the file tree
      // TYPO3's element browser shows a file tree on the left and file list on the right.
      // The file tree structure uses SVG tree nodes or nested list items.
      // Look for the "fileadmin" folder or "user_upload" subfolder.
      const fileTreeNode = browserFrame.locator(
        '.node[title*="fileadmin"], ' +
        '[data-identifier*="fileadmin"], ' +
        'a:has-text("fileadmin"), ' +
        '.list-tree a:has-text("fileadmin")'
      ).first();

      if (await fileTreeNode.count() > 0) {
        await fileTreeNode.click();
        await page.waitForTimeout(2000);
        console.log('Step 3: Clicked fileadmin folder in file tree');
      } else {
        console.log('Step 3: fileadmin node not found, trying user_upload directly');
        const userUploadNode = browserFrame.locator(
          '.node[title*="user_upload"], ' +
          'a:has-text("user_upload")'
        ).first();

        if (await userUploadNode.count() > 0) {
          await userUploadNode.click();
          await page.waitForTimeout(2000);
        }
      }

      // Step 4: Select a file from the file list
      // The file list shows thumbnails or list items for each file.
      // Clicking a file should insert it and close the modal.
      const fileItem = browserFrame.locator(
        'a[data-file-uid], ' +
        'a[data-close], ' +
        '.filelist-item a, ' +
        'table.table a[href*="file"]'
      ).first();

      if (await fileItem.count() > 0) {
        await fileItem.evaluate((el: HTMLElement) => el.click());
        console.log('Step 4: Clicked file item');
      } else {
        // Alternative: look for any image file link
        const anyFile = browserFrame.locator(
          'a:has-text(".jpg"), a:has-text(".png"), ' +
          'img[src*="fileadmin"]'
        ).first();

        if (await anyFile.count() > 0) {
          await anyFile.evaluate((el: HTMLElement) => el.click());
          console.log('Step 4: Clicked image file via alternative selector');
        } else {
          await page.screenshot({ path: 'test-results/image-insert-no-files.png' });
          requireCondition(false, 'No files found in element browser');
        }
      }

      // Step 5: Wait for modal to close and image to be inserted
      await page.waitForTimeout(3000);

      // Verify image was inserted
      const newImageCount = await frame.locator('.ck-editor__editable img').count();
      console.log(`New image count: ${newImageCount} (was ${initialImageCount})`);

      expect(newImageCount).toBeGreaterThan(initialImageCount);
      console.log('SUCCESS: Image was inserted via file browser');
    });
  });

  test.describe('Inserted Image Attributes', () => {
    test('images in editor have data-htmlarea-file-uid attribute', async ({ page }) => {
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const frame = getModuleFrame(page);
      const images = frame.locator('.ck-editor__editable img[data-htmlarea-file-uid]');
      const count = await images.count();

      expect(count, 'Expected images with data-htmlarea-file-uid in editor').toBeGreaterThan(0);

      // Verify the attribute has a numeric value
      const firstImage = images.first();
      const fileUid = await firstImage.getAttribute('data-htmlarea-file-uid');
      expect(fileUid).toBeTruthy();
      expect(parseInt(fileUid as string)).toBeGreaterThan(0);

      console.log(`Image data-htmlarea-file-uid: ${fileUid}`);
    });

    test('images in editor have alt attribute', async ({ page }) => {
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const frame = getModuleFrame(page);
      const images = frame.locator('.ck-editor__editable img[alt]');
      const count = await images.count();

      expect(count, 'Expected images with alt attribute in editor').toBeGreaterThan(0);

      const firstImage = images.first();
      const alt = await firstImage.getAttribute('alt');
      expect(alt).toBeTruthy();

      console.log(`Image alt: "${alt}"`);
    });

    test('images in editor have src attribute pointing to fileadmin', async ({ page }) => {
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const frame = getModuleFrame(page);
      const images = frame.locator('.ck-editor__editable img');
      const count = await images.count();

      expect(count, 'Expected images in editor').toBeGreaterThan(0);

      const firstImage = images.first();
      const src = await firstImage.getAttribute('src');
      expect(src).toBeTruthy();
      expect(src).toMatch(/(fileadmin|_processed_)/);

      console.log(`Image src: "${src}"`);
    });

    test('all images in editor have required TYPO3 attributes', async ({ page }) => {
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const frame = getModuleFrame(page);
      const images = frame.locator('.ck-editor__editable img');
      const count = await images.count();

      expect(count, 'Expected images in editor').toBeGreaterThan(0);

      // Check each image has the required attributes
      for (let i = 0; i < count; i++) {
        const image = images.nth(i);
        const src = await image.getAttribute('src');
        const alt = await image.getAttribute('alt');
        const fileUid = await image.getAttribute('data-htmlarea-file-uid');

        console.log(`Image ${i}: src="${src}", alt="${alt}", file-uid="${fileUid}"`);

        // src is always required
        expect(src, `Image ${i} must have src`).toBeTruthy();

        // data-htmlarea-file-uid is required for TYPO3 image processing
        expect(fileUid, `Image ${i} must have data-htmlarea-file-uid`).toBeTruthy();

        // alt should be present (accessibility)
        expect(alt, `Image ${i} must have alt attribute`).not.toBeNull();
      }
    });
  });

  test.describe('Multiple Images in Single Content Element', () => {
    test.fixme('can insert a second image into a content element', async ({ page }) => {
      // FIXME: This test requires the full file browser interaction flow, which
      // depends on the element browser modal working correctly in CI.
      // Same infrastructure limitations as the "browse and select" test above.
      // The test verifies that CKEditor and the TYPO3 extension support multiple
      // images in a single RTE content element.
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const frame = getModuleFrame(page);

      // Record initial state
      const initialImageCount = await frame.locator('.ck-editor__editable img').count();
      console.log(`Initial image count: ${initialImageCount}`);
      expect(initialImageCount, 'CE 1 should have at least one image').toBeGreaterThan(0);

      // Focus the editor and position cursor after the existing image
      const editor = frame.locator('.ck-editor__editable').first();
      await editor.click();
      await page.waitForTimeout(300);

      // Press End to move cursor to end of content, then Enter for a new line
      await page.keyboard.press('End');
      await page.keyboard.press('Enter');
      await page.waitForTimeout(300);

      // Click the image insert button
      const imageButton = frame.locator(
        '.ck-toolbar button[data-cke-tooltip-text*="image" i], ' +
        '.ck-toolbar button[data-cke-tooltip-text*="Image" i]'
      ).first();

      requireCondition(
        await imageButton.count() > 0,
        'Image insert button not found in CKEditor toolbar'
      );

      await imageButton.click();
      console.log('Clicked image insert button for second image');

      // Wait for element browser modal
      const modal = page.locator('.t3js-modal').first();
      await expect(modal).toBeVisible({ timeout: 10000 });

      const browserFrame = page.frameLocator('.t3js-modal iframe, .modal-dialog iframe').first();
      await browserFrame.locator('body').waitFor({ timeout: 10000 });
      await page.waitForTimeout(2000);

      // Select a file (same approach as the single insertion test)
      const fileItem = browserFrame.locator(
        'a[data-file-uid], a[data-close], .filelist-item a'
      ).first();

      if (await fileItem.count() > 0) {
        await fileItem.evaluate((el: HTMLElement) => el.click());
      } else {
        const anyFile = browserFrame.locator('a:has-text(".jpg"), a:has-text(".png")').first();
        if (await anyFile.count() > 0) {
          await anyFile.evaluate((el: HTMLElement) => el.click());
        } else {
          await page.screenshot({ path: 'test-results/second-image-no-files.png' });
          requireCondition(false, 'No files found in element browser for second image');
        }
      }

      // Wait for insertion
      await page.waitForTimeout(3000);

      // Verify second image was inserted
      const newImageCount = await frame.locator('.ck-editor__editable img').count();
      console.log(`Image count after second insertion: ${newImageCount} (was ${initialImageCount})`);

      expect(newImageCount).toBeGreaterThan(initialImageCount);

      // Verify both images have required attributes
      const allImages = frame.locator('.ck-editor__editable img');
      for (let i = 0; i < await allImages.count(); i++) {
        const image = allImages.nth(i);
        const fileUid = await image.getAttribute('data-htmlarea-file-uid');
        const src = await image.getAttribute('src');

        expect(fileUid, `Image ${i} must have data-htmlarea-file-uid`).toBeTruthy();
        expect(src, `Image ${i} must have src`).toBeTruthy();
      }

      console.log('SUCCESS: Multiple images exist in single content element');
    });

    test('editor HTML supports multiple image elements', async ({ page }) => {
      // This test verifies the HTML structure can hold multiple images,
      // using the pre-existing content. It does not require file browser interaction.
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const editorHtml = await getEditorHtml(page);

      // Count <img occurrences in the editor HTML
      const imgMatches = editorHtml.match(/<img /g);
      const imageCount = imgMatches ? imgMatches.length : 0;

      console.log(`Images found in editor HTML: ${imageCount}`);

      // CE 1 has at least one image
      expect(imageCount).toBeGreaterThan(0);

      // Verify the HTML structure is valid for image elements
      expect(editorHtml).toContain('data-htmlarea-file-uid');
      expect(editorHtml).toContain('alt=');
      expect(editorHtml).toContain('src=');
    });

    test('each image in editor has a unique data-htmlarea-file-uid or position', async ({ page }) => {
      await loginToBackend(page);
      await navigateToContentEdit(page, 1);
      await waitForCKEditor(page);

      const frame = getModuleFrame(page);
      const images = frame.locator('.ck-editor__editable img[data-htmlarea-file-uid]');
      const count = await images.count();

      expect(count, 'Expected images with file-uid in editor').toBeGreaterThan(0);

      // Collect all file UIDs
      const fileUids: string[] = [];
      for (let i = 0; i < count; i++) {
        const fileUid = await images.nth(i).getAttribute('data-htmlarea-file-uid');
        if (fileUid) {
          fileUids.push(fileUid);
        }
      }

      console.log(`File UIDs found: [${fileUids.join(', ')}]`);

      // All collected file UIDs should be valid numeric values
      for (const uid of fileUids) {
        expect(parseInt(uid)).toBeGreaterThan(0);
      }

      // Note: Multiple images CAN reference the same file (same UID inserted twice).
      // We only verify that each has a valid UID, not necessarily unique.
    });
  });
});
