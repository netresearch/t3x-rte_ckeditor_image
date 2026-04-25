import { test, expect } from '@playwright/test';
import { gotoFrontendPage } from './helpers/typo3-backend';

/**
 * E2E tests for default link attribute preservation (#718).
 *
 * Bug: Since v13.6.0, `tags.a >` cleared the default parseFunc config
 * from fluid_styled_content, causing missing link attributes:
 * - target="_blank" (from extTarget config for external links)
 * - rel="noreferrer" (added by typolink for target="_blank")
 * - Custom a tag configurations
 *
 * Fix: Removed `tags.a >` — our extension now only adds a preUserFunc
 * for nested link stripping, preserving the default typolink behavior.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/718
 */
test.describe('Link Default Attributes (#718)', () => {
  test('external links with target="_blank" have rel="noreferrer"', async ({ page }) => {
    await gotoFrontendPage(page);

    // Scope to **editorial external links** that go through `tags.a` parseFunc and
    // typolink's `LinkFactory::addSecurityRelValues()`. Filter by `href^="http"` so
    // we don't pick up our own popup/lightbox links — those carry `target="_blank"`
    // too, but use internal `/fileadmin/...` URLs and intentionally set
    // `rel="lightbox-group-rte"` from a code path that bypasses typolink (cf.
    // ImageRenderingService template selection for the Popup/Zoom variants).
    const externalLinks = page.locator('a[target="_blank"][href^="http"]');
    const count = await externalLinks.count();

    expect(count, 'Expected editorial external links with target="_blank" in rendered content').toBeGreaterThan(0);

    // CRITICAL: typolink adds rel="noreferrer" for target="_blank" links.
    // This was the primary regression in #718 — tags.a > removed typolink processing.
    for (let i = 0; i < Math.min(count, 5); i++) {
      const rel = await externalLinks.nth(i).getAttribute('rel');
      expect(
        rel,
        `External link ${i} with target="_blank" should have rel attribute containing "noreferrer"`,
      ).toContain('noreferrer');
    }
  });

  test('linked inline images preserve target attribute', async ({ page }) => {
    await gotoFrontendPage(page);

    // Same scoping as above — popup/lightbox links wrap images too, but they
    // legitimately don't carry `rel="noreferrer"` (their rel is "lightbox-group-rte"
    // and they target internal file URLs). Restrict to editorial external links.
    const linkedImages = page.locator('a[target="_blank"][href^="http"] img');
    const count = await linkedImages.count();

    expect(count, 'Expected editorial linked images with target="_blank" parent').toBeGreaterThan(0);

    // Get the parent <a> and verify attributes
    const parentLink = page.locator('a[target="_blank"][href^="http"]:has(img)').first();
    const href = await parentLink.getAttribute('href');
    expect(href, 'Link href should be present').toBeTruthy();

    const target = await parentLink.getAttribute('target');
    expect(target).toBe('_blank');

    // Verify rel="noreferrer" is also present on linked images
    const rel = await parentLink.getAttribute('rel');
    expect(rel, 'Linked image with target="_blank" should have rel containing "noreferrer"').toContain('noreferrer');
  });

  test('links are not double-wrapped after processing', async ({ page }) => {
    await gotoFrontendPage(page);

    // Get all links that contain images
    const imageLinks = page.locator('a:has(img.image-inline)');
    const count = await imageLinks.count();

    if (count === 0) {
      test.skip();
      return;
    }

    // Check each linked image for nested <a> tags
    for (let i = 0; i < Math.min(count, 5); i++) {
      const innerHTML = await imageLinks.nth(i).innerHTML();
      const nestedAnchors = innerHTML.match(/<a[\s>]/gi);
      expect(
        nestedAnchors,
        `Link ${i} should not have nested <a> tags: ${innerHTML.substring(0, 200)}`,
      ).toBeNull();
    }
  });
});
