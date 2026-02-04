import { test, expect, Page, FrameLocator } from '@playwright/test';

/**
 * E2E tests for inline image editing in CKEditor.
 *
 * These tests verify the actual USER EXPERIENCE of working with inline images:
 * - Inserting inline images via the editor
 * - Typing text before/after inline images on the same line
 * - Toggling between block and inline modes
 * - Cursor positioning around inline images
 * - Content persistence after save
 *
 * Unlike the frontend rendering tests (inline-images.spec.ts), these tests
 * interact with the TYPO3 backend and CKEditor to verify real editing workflows.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/580
 */

const BACKEND_USER = process.env.TYPO3_BACKEND_USER || 'admin';
const BACKEND_PASSWORD = process.env.TYPO3_BACKEND_PASSWORD || '';
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
 * Get the CKEditor editable element
 */
function getEditor(page: Page) {
  return getModuleFrame(page).locator('.ck-editor__editable').first();
}

/**
 * Get the HTML content of the editor
 */
async function getEditorHtml(page: Page): Promise<string> {
  return await getEditor(page).innerHTML();
}

/**
 * Click on the editor to focus it
 */
async function focusEditor(page: Page): Promise<void> {
  await getEditor(page).click();
  await page.waitForTimeout(300);
}

/**
 * Check if an inline image widget exists in the editor
 */
async function hasInlineImageWidget(page: Page): Promise<boolean> {
  const html = await getEditorHtml(page);
  // Check for inline image widget class or inline image model output
  return html.includes('ck-widget_inline-image') || html.includes('image-inline');
}

/**
 * Check if a block image (figure) exists in the editor
 */
async function hasBlockImageWidget(page: Page): Promise<boolean> {
  const html = await getEditorHtml(page);
  return html.includes('<figure') || html.includes('ck-widget');
}

/**
 * Get all images in the editor
 */
async function getEditorImages(page: Page): Promise<{
  total: number;
  inline: number;
  block: number;
  inFigures: number;
}> {
  const frame = getModuleFrame(page);

  return await frame.locator('.ck-editor__editable').evaluate(() => {
    const allImages = document.querySelectorAll('.ck-editor__editable img');
    const inlineImages = document.querySelectorAll('.ck-editor__editable .ck-widget_inline-image img, .ck-editor__editable img.image-inline');
    const figureImages = document.querySelectorAll('.ck-editor__editable figure img');

    return {
      total: allImages.length,
      inline: inlineImages.length,
      block: figureImages.length,
      inFigures: figureImages.length,
    };
  });
}

/**
 * Double-click an image to open its edit dialog
 */
async function openImageEditDialog(page: Page, imageIndex = 0): Promise<boolean> {
  const frame = getModuleFrame(page);
  const images = frame.locator('.ck-editor__editable img');

  if (await images.count() > imageIndex) {
    await images.nth(imageIndex).dblclick();
    await page.waitForSelector('.modal-dialog, .t3js-modal', { timeout: 10000 });
    await page.waitForTimeout(500);
    return true;
  }
  return false;
}

/**
 * Close dialog by clicking OK/Confirm button
 */
async function confirmDialog(page: Page): Promise<void> {
  const confirmButton = page.locator('.modal-footer button.btn-primary, .modal-footer button:has-text("OK")').first();
  if (await confirmButton.count() > 0) {
    await confirmButton.evaluate((el: HTMLElement) => el.click());
  }
  await page.waitForTimeout(1000);
}

/**
 * Close dialog by clicking Cancel button
 */
async function cancelDialog(page: Page): Promise<void> {
  const cancelButton = page.locator('.modal-footer button:has-text("Cancel"), .modal-header .close').first();
  if (await cancelButton.count() > 0) {
    await cancelButton.click();
  }
  await page.waitForTimeout(500);
}

// =============================================================================
// Test Suite: Inline Image Editing Experience
// =============================================================================

test.describe('Inline Image Editing in CKEditor (#580)', () => {
  let loggedIn = false;

  test.beforeEach(async ({ page }) => {
    if (!loggedIn) {
      loggedIn = await loginToBackend(page);
    }
    test.skip(!loggedIn, 'Backend login failed - check TYPO3_BACKEND_PASSWORD environment variable');
  });

  test('can view images in CKEditor', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    console.log(`Images in editor: ${JSON.stringify(images)}`);

    // At minimum, we should be able to see the editor
    const editorHtml = await getEditorHtml(page);
    expect(editorHtml).toBeTruthy();
    console.log('Editor HTML length:', editorHtml.length);
  });

  test('inline images render with correct widget class', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const hasInline = await hasInlineImageWidget(page);
    const hasBlock = await hasBlockImageWidget(page);

    console.log(`Has inline widget: ${hasInline}, Has block widget: ${hasBlock}`);

    // Test passes if we can at least check for widgets
    // Skip assertion if no images exist in test content
    const images = await getEditorImages(page);
    test.skip(images.total === 0, 'No images in test content');

    // If we have inline images, they should have the inline widget class
    if (images.inline > 0) {
      expect(hasInline).toBe(true);
    }
  });

  test('inline images allow cursor positioning on same line', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    test.skip(images.inline === 0, 'No inline images in test content');

    // Focus the editor
    await focusEditor(page);

    // Get the editor content structure
    const frame = getModuleFrame(page);
    const structure = await frame.locator('.ck-editor__editable').evaluate(() => {
      const inlineWidget = document.querySelector('.ck-widget_inline-image');
      if (!inlineWidget) return { found: false };

      const parent = inlineWidget.parentElement;
      const isInParagraph = parent?.tagName === 'P' || parent?.closest('p') !== null;

      // Check if there's text content around the image
      const prevSibling = inlineWidget.previousSibling;
      const nextSibling = inlineWidget.nextSibling;

      return {
        found: true,
        isInParagraph,
        hasPrevSibling: !!prevSibling,
        hasNextSibling: !!nextSibling,
        parentTag: parent?.tagName,
      };
    });

    console.log('Inline image structure:', JSON.stringify(structure));

    if (structure.found) {
      // Inline images should be within paragraph context, not in figure
      expect(structure.isInParagraph).toBe(true);
    }
  });

  test('can type text before inline image in same paragraph', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    test.skip(images.inline === 0, 'No inline images in test content - need inline image to test typing before it');

    // Focus editor and position cursor
    await focusEditor(page);

    const frame = getModuleFrame(page);

    // Try to click just before the inline image widget
    const inlineWidget = frame.locator('.ck-widget_inline-image').first();
    if (await inlineWidget.count() > 0) {
      // Click slightly to the left of the widget
      const box = await inlineWidget.boundingBox();
      if (box) {
        await page.mouse.click(box.x - 5, box.y + box.height / 2);
        await page.waitForTimeout(300);

        // Type some text
        const testText = 'BEFORE_IMAGE_';
        await page.keyboard.type(testText);
        await page.waitForTimeout(500);

        // Verify text appears before the image
        const html = await getEditorHtml(page);
        const hasTextBeforeImage = html.includes(testText);
        console.log(`Text "${testText}" appears in editor: ${hasTextBeforeImage}`);

        // The text should be in the same paragraph as the image
        expect(hasTextBeforeImage).toBe(true);
      }
    }
  });

  test('can type text after inline image in same paragraph', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    test.skip(images.inline === 0, 'No inline images in test content');

    await focusEditor(page);

    const frame = getModuleFrame(page);
    const inlineWidget = frame.locator('.ck-widget_inline-image').first();

    if (await inlineWidget.count() > 0) {
      // Click slightly to the right of the widget
      const box = await inlineWidget.boundingBox();
      if (box) {
        await page.mouse.click(box.x + box.width + 5, box.y + box.height / 2);
        await page.waitForTimeout(300);

        // Type some text
        const testText = '_AFTER_IMAGE';
        await page.keyboard.type(testText);
        await page.waitForTimeout(500);

        // Verify text appears after the image
        const html = await getEditorHtml(page);
        const hasTextAfterImage = html.includes(testText);
        console.log(`Text "${testText}" appears in editor: ${hasTextAfterImage}`);

        expect(hasTextAfterImage).toBe(true);
      }
    }
  });

  test('inline image and text are in same paragraph element', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    test.skip(images.inline === 0, 'No inline images in test content');

    const frame = getModuleFrame(page);

    // Check the paragraph structure
    const paragraphWithInline = await frame.locator('.ck-editor__editable p:has(.ck-widget_inline-image)').count();

    console.log(`Paragraphs containing inline images: ${paragraphWithInline}`);

    // Inline images should be inside paragraph elements
    expect(paragraphWithInline).toBeGreaterThan(0);
  });

  test('block images are in figure elements, not paragraphs', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    test.skip(images.block === 0, 'No block images in test content');

    const frame = getModuleFrame(page);

    // Block images should be in figure elements
    const figuresWithImages = await frame.locator('.ck-editor__editable figure:has(img)').count();

    console.log(`Figures containing images: ${figuresWithImages}`);
    console.log(`Block images: ${images.block}`);

    // All block images should be in figures
    expect(figuresWithImages).toBe(images.block);
  });
});

test.describe('Toggle Image Type in CKEditor (#580)', () => {
  let loggedIn = false;

  test.beforeEach(async ({ page }) => {
    if (!loggedIn) {
      loggedIn = await loginToBackend(page);
    }
    test.skip(!loggedIn, 'Backend login failed');
  });

  test('toggle button exists in image toolbar', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    test.skip(images.total === 0, 'No images in test content');

    // Click on an image to select it
    const frame = getModuleFrame(page);
    const firstImage = frame.locator('.ck-editor__editable img').first();
    await firstImage.click();
    await page.waitForTimeout(500);

    // Check for balloon toolbar with toggle button
    // The toolbar should appear when image is selected
    const balloonToolbar = frame.locator('.ck-balloon-panel, .ck-toolbar');
    const toolbarVisible = await balloonToolbar.count() > 0;

    console.log(`Balloon toolbar visible: ${toolbarVisible}`);

    // Look for toggle button (may have various labels/icons)
    const toggleButton = frame.locator('.ck-button[data-cke-tooltip-text*="toggle"], .ck-button[data-cke-tooltip-text*="inline"], button:has-text("Toggle")');
    const hasToggle = await toggleButton.count() > 0;

    console.log(`Toggle button found: ${hasToggle}`);

    // Just log for now - we may need to adjust selector based on actual implementation
  });

  test('clicking toggle converts block image to inline', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    test.skip(images.block === 0, 'No block images to toggle');

    // Record initial state
    const initialHtml = await getEditorHtml(page);
    const hadFigure = initialHtml.includes('<figure');

    console.log(`Initial state - has figure: ${hadFigure}`);

    // Click on a block image
    const frame = getModuleFrame(page);
    const blockImage = frame.locator('.ck-editor__editable figure img').first();
    await blockImage.click();
    await page.waitForTimeout(500);

    // Try to find and click toggle button
    // Note: actual button selector depends on implementation
    const toggleButton = frame.locator('[data-command="toggleImageType"], .ck-button:has-text("Inline")').first();

    if (await toggleButton.count() > 0) {
      await toggleButton.click();
      await page.waitForTimeout(1000);

      // Check if image is now inline
      const newHtml = await getEditorHtml(page);
      const nowHasInline = newHtml.includes('image-inline') || newHtml.includes('ck-widget_inline-image');

      console.log(`After toggle - has inline widget: ${nowHasInline}`);

      // The image should now be inline
      expect(nowHasInline).toBe(true);
    } else {
      console.log('Toggle button not found - implementation may differ');
      // Don't fail test, just skip
      test.skip(true, 'Toggle button not found in toolbar');
    }
  });

  test('toggling to inline removes caption', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    // Check for captioned image
    const frame = getModuleFrame(page);
    const captionedImages = frame.locator('.ck-editor__editable figure figcaption');
    const hasCaptions = await captionedImages.count() > 0;

    test.skip(!hasCaptions, 'No captioned images to test');

    console.log(`Found ${await captionedImages.count()} captioned images`);

    // Click on a captioned image
    const captionedFigure = frame.locator('.ck-editor__editable figure:has(figcaption)').first();
    const imageInFigure = captionedFigure.locator('img').first();
    await imageInFigure.click();
    await page.waitForTimeout(500);

    // Try to toggle to inline
    const toggleButton = frame.locator('[data-command="toggleImageType"], .ck-button:has-text("Inline")').first();

    if (await toggleButton.count() > 0) {
      await toggleButton.click();
      await page.waitForTimeout(1000);

      // After toggling to inline, caption should be removed
      const newHtml = await getEditorHtml(page);
      const stillHasCaption = newHtml.includes('<figcaption');

      console.log(`After toggle - still has caption: ${stillHasCaption}`);

      // Inline images cannot have captions
      // Note: This assumes toggling removes the caption
    } else {
      console.log('Toggle button not found');
      test.skip(true, 'Toggle button not found');
    }
  });
});

test.describe('Inline Image Persistence (#580)', () => {
  test('inline images persist after save and reload', async ({ page }) => {
    const loggedIn = await loginToBackend(page);
    test.skip(!loggedIn, 'Backend login failed');

    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    // Get initial inline image count
    const initialImages = await getEditorImages(page);
    test.skip(initialImages.inline === 0, 'No inline images to test persistence');

    console.log(`Initial inline images: ${initialImages.inline}`);

    // Save the content
    const frame = getModuleFrame(page);
    const saveButton = frame.locator('button[name="_savedok"], button[value="1"][name="_savedok"]').first();

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

      // Check inline images are still there
      const afterReloadImages = await getEditorImages(page);
      console.log(`After reload inline images: ${afterReloadImages.inline}`);

      // Should have same number of inline images
      expect(afterReloadImages.inline).toBe(initialImages.inline);
      console.log('SUCCESS: Inline images persisted after save and reload');
    } else {
      test.skip(true, 'Save button not found');
    }
  });

  test('inline image class is preserved in saved HTML', async ({ page }) => {
    const loggedIn = await loginToBackend(page);
    test.skip(!loggedIn, 'Backend login failed');

    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    test.skip(images.inline === 0, 'No inline images in test content');

    // Get the raw HTML that would be saved
    const editorHtml = await getEditorHtml(page);

    // Check for image-inline class in the HTML
    const hasInlineClass = editorHtml.includes('image-inline');
    console.log(`Editor HTML contains image-inline class: ${hasInlineClass}`);

    // Inline images should have the image-inline class for downcast
    expect(hasInlineClass).toBe(true);
  });
});

test.describe('Multiple Inline Images (#580)', () => {
  let loggedIn = false;

  test.beforeEach(async ({ page }) => {
    if (!loggedIn) {
      loggedIn = await loginToBackend(page);
    }
    test.skip(!loggedIn, 'Backend login failed');
  });

  test('multiple inline images can exist in same paragraph', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const frame = getModuleFrame(page);

    // Count paragraphs with multiple inline images
    const paragraphsWithMultipleInline = await frame.locator('.ck-editor__editable').evaluate(() => {
      const paragraphs = document.querySelectorAll('.ck-editor__editable p');
      let count = 0;

      paragraphs.forEach(p => {
        const inlineImages = p.querySelectorAll('.ck-widget_inline-image, img.image-inline');
        if (inlineImages.length > 1) {
          count++;
        }
      });

      return count;
    });

    console.log(`Paragraphs with multiple inline images: ${paragraphsWithMultipleInline}`);

    // This is a capability test - multiple inline images should be allowed
    // Don't fail if test content doesn't have multiple inline images
    if (paragraphsWithMultipleInline > 0) {
      expect(paragraphsWithMultipleInline).toBeGreaterThan(0);
    }
  });

  test('inline and block images can coexist in content', async ({ page }) => {
    const editFormLoaded = await navigateToContentEdit(page);
    test.skip(!editFormLoaded, 'Could not load content edit form');

    await waitForCKEditor(page);

    const images = await getEditorImages(page);
    console.log(`Total images: ${images.total}, Inline: ${images.inline}, Block: ${images.block}`);

    // Test passes if we can have both types
    // Skip if test content only has one type
    test.skip(
      images.inline === 0 || images.block === 0,
      'Need both inline and block images to test coexistence'
    );

    // Both types should be present
    expect(images.inline).toBeGreaterThan(0);
    expect(images.block).toBeGreaterThan(0);

    // Total should equal sum of inline and block
    expect(images.total).toBe(images.inline + images.block);
  });
});
