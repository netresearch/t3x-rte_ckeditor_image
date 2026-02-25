import { test, expect } from '@playwright/test';
import { loginToBackend, BASE_URL } from './helpers/typo3-backend';

/**
 * Content Blocks backend preview — tests the RteImagePreview ViewHelper.
 *
 * These tests verify that the custom ViewHelper correctly renders
 * backend previews for Content Blocks elements containing RTE images.
 *
 * Prerequisites:
 *   - friendsoftypo3/content-blocks must be installed (-c flag)
 *   - The netresearch/rte-image-demo Content Block must be registered
 *   - Test content with CType 'netresearch_rteimagedemo' on page uid=3
 *
 * Run with:
 *   Build/Scripts/runTests.sh -s e2e -t 13 -c "friendsoftypo3/content-blocks" -- tests/content-blocks-preview.spec.ts
 */

test.describe('Content Blocks Backend Preview', () => {
  test.skip(!process.env.CONTENT_BLOCKS_ENABLED, 'Requires Content Blocks (use -c "friendsoftypo3/content-blocks")');

  test.beforeEach(async ({ page }) => {
    await loginToBackend(page);
  });

  test('Content Block preview shows image thumbnails via ViewHelper', async ({ page }) => {
    // Navigate to Page module — the Content Blocks demo page (uid=3)
    const pageModuleUrl = `${BASE_URL}/typo3/module/web/layout?id=3`;
    await page.goto(pageModuleUrl, { timeout: 30000 });
    await page.waitForLoadState('networkidle');

    // TYPO3 backend uses iframes — get the module content frame
    const frame = page.frameLocator('iframe').first();

    // Wait for the page module to render content elements
    await frame.locator('.t3-page-ce').first().waitFor({ timeout: 15000 });

    // Verify the page has content elements (our Content Block CEs on page 3)
    const ceCount = await frame.locator('.t3-page-ce').count();
    expect(ceCount, 'Page 3 should have Content Block content elements').toBeGreaterThan(0);

    // Content Blocks with our ViewHelper should render <img> tags in the preview
    // The ViewHelper outputs <img> tags from the RTE bodytext
    const previewImages = frame.locator('.t3-page-ce img[src*="fileadmin"], .t3-page-ce img[src*="_processed_"]');
    const imageCount = await previewImages.count();
    expect(imageCount, 'Expected at least one image in Content Block backend preview').toBeGreaterThan(0);

    // First preview image should be visible
    await expect(previewImages.first()).toBeVisible();
  });

  test('Content Block preview renders paragraph text from RTE', async ({ page }) => {
    const pageModuleUrl = `${BASE_URL}/typo3/module/web/layout?id=3`;
    await page.goto(pageModuleUrl, { timeout: 30000 });
    await page.waitForLoadState('networkidle');

    const frame = page.frameLocator('iframe').first();
    await frame.locator('.t3-page-ce').first().waitFor({ timeout: 15000 });

    // The ViewHelper preserves <p> tags — verify paragraph text from CE 42 is visible
    // CE 42 bodytext starts with "This content uses a Content Block type"
    const cbText = frame.locator('.t3-page-ce:has-text("Content Block type")');
    const cbCount = await cbText.count();
    expect(cbCount, 'Expected Content Block CE with "Content Block type" text').toBeGreaterThan(0);

    // CE 43 bodytext contains "Inline images work in Content Blocks too"
    const inlineText = frame.locator('.t3-page-ce:has-text("Inline images work")');
    const inlineCount = await inlineText.count();
    expect(inlineCount, 'Expected Content Block CE with inline image text').toBeGreaterThan(0);
  });

  test('Content Block preview does not contain disallowed tags', async ({ page }) => {
    const pageModuleUrl = `${BASE_URL}/typo3/module/web/layout?id=3`;
    await page.goto(pageModuleUrl, { timeout: 30000 });
    await page.waitForLoadState('networkidle');

    const frame = page.frameLocator('iframe').first();
    await frame.locator('.t3-page-ce').first().waitFor({ timeout: 15000 });

    // Get the full HTML of all CE previews on page 3
    const allCEs = frame.locator('.t3-page-ce');
    const ceCount = await allCEs.count();
    expect(ceCount, 'Page 3 should have Content Block content elements').toBeGreaterThan(0);

    // Check each CE preview for the absence of disallowed tags
    // The ViewHelper strips everything except <img> and <p>
    for (let i = 0; i < ceCount; i++) {
      const ce = allCEs.nth(i);
      const innerHTML = await ce.innerHTML();

      // The preview should not contain <script>, <style>, or <div> tags
      // (These would indicate the ViewHelper's strip_tags is not working)
      expect(innerHTML, `CE ${i}: should not contain <script> in preview`).not.toMatch(/<script[\s>]/i);
      expect(innerHTML, `CE ${i}: should not contain <style> in preview`).not.toMatch(/<style[\s>]/i);
    }
  });
});
