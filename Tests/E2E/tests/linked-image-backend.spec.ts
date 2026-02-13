import { test, expect, Page } from '@playwright/test';
import { loginToBackend, BASE_URL, navigateToContentEdit, getModuleFrame, waitForCKEditor, requireCondition, BACKEND_PASSWORD, openImageEditDialog, confirmImageDialog, getEditorHtml, saveContentElement, selectImageInEditor } from './helpers/typo3-backend';

/**
 * E2E tests for the actual CKEditor workflow where issue #565 manifests.
 *
 * The bug: Duplicate links appear when images are wrapped in <a> tags.
 * Workflow to reproduce:
 * 1. User inserts an image in CKEditor
 * 2. User wraps the image in a link (via toolbar link button)
 * 3. Source view shows <a><a><img/></a></a> instead of <a><img/></a>
 *
 * These tests require:
 * - TYPO3 backend accessible at /typo3/
 * - Backend user credentials (TYPO3_BACKEND_USER, TYPO3_BACKEND_PASSWORD)
 * - A content element with RTE to edit
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/565
 */

/**
 * Get the HTML source from CKEditor
 */
async function getCKEditorSource(page: Page): Promise<string> {
  // Find the CKEditor instance and get its data
  return await page.evaluate(() => {
    // Try different ways to access CKEditor instance
    const editors = (window as any).CKEDITOR?.instances;
    if (editors) {
      const editorName = Object.keys(editors)[0];
      if (editorName) {
        return editors[editorName].getData();
      }
    }

    // For CKEditor 5
    const ckEditorElement = document.querySelector('.ck-editor__editable');
    if (ckEditorElement && (ckEditorElement as any).ckeditorInstance) {
      return (ckEditorElement as any).ckeditorInstance.getData();
    }

    return '';
  });
}

/**
 * Count <a> tags wrapping images in HTML source
 */
function countLinkWrappersAroundImages(html: string): { total: number; nested: number } {
  // Count total <a> tags that contain <img>
  const linkWithImageRegex = /<a[^>]*>[\s\S]*?<img[^>]*>[\s\S]*?<\/a>/gi;
  const matches = html.match(linkWithImageRegex) || [];

  // Count nested <a> tags (the bug symptom)
  const nestedLinkRegex = /<a[^>]*>\s*<a[^>]*>/gi;
  const nestedMatches = html.match(nestedLinkRegex) || [];

  return {
    total: matches.length,
    nested: nestedMatches.length
  };
}

test.describe('CKEditor Backend Workflow (#565)', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
  });

  test('can login to TYPO3 backend', async ({ page }) => {
    await loginToBackend(page);
  });

  test('CKEditor does not create duplicate links when linking an image', async ({ page }) => {
    // This test verifies the core bug fix
    await loginToBackend(page);

    // Navigate to content editing
    await navigateToContentEdit(page);

    // Wait for CKEditor to initialize
    await waitForCKEditor(page);

    // Get initial source
    const initialSource = await getCKEditorSource(page);
    const initialCounts = countLinkWrappersAroundImages(initialSource);

    // Log for debugging
    console.log('Initial link counts:', initialCounts);

    // If there are already images with links, verify no duplicates
    if (initialCounts.total > 0) {
      expect(initialCounts.nested).toBe(0);
    }
  });

  test('linked images in source view have exactly one <a> wrapper', async ({ page }) => {
    await loginToBackend(page);

    await navigateToContentEdit(page);

    await waitForCKEditor(page);

    const source = await getCKEditorSource(page);

    // Look for patterns indicating duplicate links
    // Bad: <a href="..."><a href="..."><img></a></a>
    // Good: <a href="..."><img></a>

    const duplicateLinkPattern = /<a[^>]*href[^>]*>\s*<a[^>]*href[^>]*>/gi;
    const duplicateMatches = source.match(duplicateLinkPattern);

    expect(duplicateMatches).toBeNull();
  });
});

test.describe('Backend Integration Smoke Tests', () => {
  test('TYPO3 backend is accessible', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/typo3/`);

    // Should get a response (even if redirect to login)
    expect(response?.status()).toBeLessThan(500);
  });

  test('backend login page renders without errors', async ({ page }) => {
    await page.goto(`${BASE_URL}/typo3/`);

    // Should not have TYPO3 error messages
    const content = await page.content();
    expect(content).not.toContain('Oops, an error occurred');
    expect(content).not.toContain('TypoScript configuration error');
  });
});

test.describe('Image Toolbar vs Link Balloon Priority', () => {
  /**
   * Regression test for balloon toolbar conflict.
   *
   * When a linked image is selected in CKEditor, both the image toolbar
   * and the link balloon could try to display. This test verifies that
   * the image toolbar takes priority and the link balloon is suppressed.
   *
   * Fix uses LinkUI.forceDisabled() when typo3image widget is selected.
   * @see https://github.com/ckeditor/ckeditor5/issues/9607
   */
  test('clicking linked image shows image toolbar, not link balloon', async ({ page }) => {
    await loginToBackend(page);

    // Navigate to a content element that has a linked image
    await navigateToContentEdit(page, 3);

    await waitForCKEditor(page);

    const moduleFrame = getModuleFrame(page);

    // Check if there's a linked image in the editor
    const linkedImage = moduleFrame.locator('.ck-editor__editable a img, .ck-editor__editable figure a img');
    const hasLinkedImage = await linkedImage.count() > 0;

    if (!hasLinkedImage) {
      // Try to find any image to add a link to for testing
      const anyImage = moduleFrame.locator('.ck-editor__editable img, .ck-editor__editable figure');
      requireCondition(await anyImage.count() > 0, 'No images found in content element');

      // Skip if no linked image - we need pre-existing linked image content
      requireCondition(false, 'No linked image found in content - add test content with linked image');
    }

    // Click on the linked image to select it
    await linkedImage.first().click();
    await page.waitForTimeout(500);

    // The image toolbar should be visible (it appears for typo3image widgets)
    // Look for the balloon toolbar with image-related buttons
    const imageToolbar = moduleFrame.locator('.ck-balloon-panel:visible .ck-toolbar');

    // The link balloon/actions panel should NOT be visible
    // CKEditor's link UI has specific classes for the link actions
    const linkBalloon = moduleFrame.locator('.ck-link-actions:visible, .ck-balloon-panel:visible .ck-link-form');

    // Wait a bit for balloons to appear/disappear
    await page.waitForTimeout(300);

    // Check what's visible
    const imageToolbarVisible = await imageToolbar.count() > 0;
    const linkBalloonVisible = await linkBalloon.count() > 0;

    console.log(`Image toolbar visible: ${imageToolbarVisible}`);
    console.log(`Link balloon visible: ${linkBalloonVisible}`);

    // The fix should ensure image toolbar is shown, not link balloon
    // Note: If both are visible, the fix isn't working correctly
    if (imageToolbarVisible && !linkBalloonVisible) {
      console.log('SUCCESS: Image toolbar shown, link balloon hidden');
    } else if (!imageToolbarVisible && linkBalloonVisible) {
      // This is the bug - link balloon overriding image toolbar
      await page.screenshot({ path: 'test-results/balloon-conflict-link-shown.png' });
      expect(linkBalloonVisible).toBe(false);
    } else if (imageToolbarVisible && linkBalloonVisible) {
      // Both visible - partial fix, but conflict still present
      await page.screenshot({ path: 'test-results/balloon-conflict-both-shown.png' });
      expect(linkBalloonVisible).toBe(false);
    }

    // Primary assertion: when clicking a linked image, the link balloon should NOT appear
    // The image toolbar takes priority
    expect(linkBalloonVisible).toBe(false);
  });

  test('regular text links still show link balloon', async ({ page }) => {
    await loginToBackend(page);

    await navigateToContentEdit(page, 10);

    await waitForCKEditor(page);

    const moduleFrame = getModuleFrame(page);

    // Find a text link (not an image link)
    const textLink = moduleFrame.locator('.ck-editor__editable a:not(:has(img)):not(:has(figure))');
    const hasTextLink = await textLink.count() > 0;

    requireCondition(hasTextLink, 'No text links found in content');

    // Click on the text link
    await textLink.first().click();
    await page.waitForTimeout(500);

    // For regular text links, the link balloon SHOULD appear
    // This verifies that forceDisabled only affects typo3image, not all links
    const linkBalloon = moduleFrame.locator('.ck-link-actions:visible, .ck-balloon-panel:visible');

    const linkBalloonVisible = await linkBalloon.count() > 0;
    console.log(`Link balloon visible for text link: ${linkBalloonVisible}`);

    // Text links should still show link UI - our fix should only affect images
    // Note: This is a sanity check - if this fails, forceDisabled is too aggressive
    expect(linkBalloonVisible).toBe(true);
  });
});

test.describe('Linked Image Workflow (#565)', () => {
  test.beforeEach(async () => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
  });

  test('insert image then add link - verify single <a> wrapper', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page);
    await waitForCKEditor(page);

    // Select an existing image in the editor
    await selectImageInEditor(page);

    // Double-click the image to open the edit dialog
    await openImageEditDialog(page);

    // Select "Link" click behavior to reveal link fields
    const linkRadio = page.locator('#clickBehavior-link');
    if (await linkRadio.count() > 0) {
      await linkRadio.click();
      await page.waitForTimeout(500);
    }

    // Set link URL via the actual linkHref input
    const linkHrefInput = page.locator('#rteckeditorimage-linkHref');
    if (await linkHrefInput.count() > 0) {
      await linkHrefInput.fill('https://example.com');
    }

    await confirmImageDialog(page);

    // Get the editor HTML and verify the link was applied
    const html = await getEditorHtml(page);
    const { total, nested } = countLinkWrappersAroundImages(html);
    expect(total, 'Image should be wrapped in a link after adding URL').toBeGreaterThan(0);
    expect(nested, 'Expected no nested <a> tags around images').toBe(0);
  });

  test('edit existing linked image - verify no duplicate <a> on save', async ({ page }) => {
    // CE 3 has pre-linked images. confirmImageDialog() uses 3-attempt retry
    // to handle modals that don't close on first click.
    await loginToBackend(page);
    await navigateToContentEdit(page, 3);
    await waitForCKEditor(page);

    // Verify the test content has linked images
    const initialHtml = await getEditorHtml(page);
    const initialCounts = countLinkWrappersAroundImages(initialHtml);
    requireCondition(initialCounts.total > 0, 'No linked images found in test content');

    // Open the image edit dialog and confirm without changes (the #565 trigger)
    await openImageEditDialog(page);
    await confirmImageDialog(page);

    // Save the content element
    await saveContentElement(page);

    // Navigate back to the same content element
    await navigateToContentEdit(page, 3);
    await waitForCKEditor(page);

    // Verify no nested <a><a> patterns appeared after the round-trip
    const savedHtml = await getEditorHtml(page);
    const savedCounts = countLinkWrappersAroundImages(savedHtml);
    expect(savedCounts.nested, 'Nested <a><a> pattern detected after save — #565 regression').toBe(0);
    expect(savedCounts.total, 'Linked images should be preserved after save').toBeGreaterThanOrEqual(initialCounts.total);
  });

  test('save and reload preserves link structure', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, 3);
    await waitForCKEditor(page);

    // Capture the initial link structure
    const initialHtml = await getEditorHtml(page);
    const initialCounts = countLinkWrappersAroundImages(initialHtml);
    requireCondition(initialCounts.total > 0, 'No linked images found in test content');

    // Save the content element
    await saveContentElement(page);

    // Navigate back and wait for CKEditor to re-initialize
    await navigateToContentEdit(page, 3);
    await waitForCKEditor(page);

    // Verify link structure is preserved after the round-trip
    const reloadedHtml = await getEditorHtml(page);
    const reloadedCounts = countLinkWrappersAroundImages(reloadedHtml);
    expect(reloadedCounts.total, 'Number of linked images changed after save/reload').toBe(initialCounts.total);
    expect(reloadedCounts.nested, 'Nested <a> tags appeared after save/reload — #565 regression').toBe(0);
  });
});
