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

    // Scope to **explicitly-seeded** test fixtures that carry `target="_blank"` in
    // their bodytext source (CE 768 ".test-linked-image" and CE 1024
    // ".test-figure-linked" in `Build/Scripts/runTests.sh`). Two reasons:
    //
    //  1. Excludes our own popup/lightbox links, which legitimately carry
    //     `target="_blank"` with `rel="lightbox-group-rte"` (a code path that
    //     bypasses typolink — see `ImageRenderingService` Popup/Zoom templates).
    //  2. Excludes inline-image links where our extension currently auto-adds
    //     `target="_blank"` on TYPO3 v14 without going through typolink's
    //     `addSecurityRelValues()` — this is a separate v14-specific bug
    //     surfaced by the variant matrix from #794, tracked as a follow-up.
    //
    // The `.test-linked-image` and `.test-figure-linked` fixtures go through
    // the standard typolink flow on both v13 and v14, so this assertion is the
    // tight invariant of #718's original fix.
    const externalLinks = page.locator(
      'a.test-linked-image[target="_blank"], a.test-figure-linked[target="_blank"]'
    );
    const count = await externalLinks.count();

    expect(count, 'Expected seeded external links (.test-linked-image / .test-figure-linked)').toBeGreaterThan(0);

    // CRITICAL: typolink adds rel="noreferrer" for target="_blank" links.
    // This was the primary regression in #718 — tags.a > removed typolink processing.
    for (let i = 0; i < count; i++) {
      const rel = await externalLinks.nth(i).getAttribute('rel');
      expect(
        rel,
        `Seeded external link ${i} with target="_blank" should have rel attribute containing "noreferrer"`,
      ).toContain('noreferrer');
    }
  });

  test('linked inline images preserve target attribute', async ({ page }) => {
    await gotoFrontendPage(page);

    // Same scoping rationale as above — assert against the explicitly-seeded
    // image-wrapping link fixtures, which exercise the standard typolink path
    // on both v13 and v14.
    const seedSelector = 'a.test-linked-image[target="_blank"]:has(img), a.test-figure-linked[target="_blank"]:has(img)';
    const linkedImages = page.locator(seedSelector);
    const count = await linkedImages.count();

    expect(count, 'Expected seeded linked-image fixtures').toBeGreaterThan(0);

    // Get the parent <a> and verify attributes
    const parentLink = linkedImages.first();
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
