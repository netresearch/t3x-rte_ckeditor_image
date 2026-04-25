import { test } from '@playwright/test';
import { gotoFrontendPage } from './helpers/typo3-backend';

/**
 * Diagnostic spec for #799 — dumps outerHTML of every <a target="_blank"> link
 * with an http(s) href on the rendered demo page, plus a few neighboring counts.
 *
 * Goal: identify the exact link the original spec's loose locator was matching
 * before #797 tightened the scope. Once we have ground truth, this spec can
 * be deleted and the real bug (if any) fixed in extension code.
 *
 * Run with TYPO3 v14.3 (any variant) — output appears in CI logs.
 */
test.describe('DIAGNOSTIC #799', () => {
  test('dump outerHTML of every a[target=_blank][href^=http]', async ({ page }) => {
    await gotoFrontendPage(page);

    const matched = page.locator('a[target="_blank"][href^="http"]');
    const matchedCount = await matched.count();
    console.log(`[#799] a[target="_blank"][href^="http"] count = ${matchedCount}`);
    for (let i = 0; i < matchedCount; i++) {
      const html = await matched.nth(i).evaluate((el) => el.outerHTML);
      // Truncate inner content to keep logs scannable.
      const truncated = html.length > 400 ? html.slice(0, 400) + '…' : html;
      console.log(`[#799] match[${i}] = ${truncated}`);
    }

    // Cross-check: how many a[target=_blank] total (any href).
    const anyTarget = page.locator('a[target="_blank"]');
    const anyCount = await anyTarget.count();
    console.log(`[#799] a[target="_blank"] total = ${anyCount}`);

    // Cross-check: how many a[href^=http] total.
    const anyExt = page.locator('a[href^="http"]');
    const anyExtCount = await anyExt.count();
    console.log(`[#799] a[href^="http"] total = ${anyExtCount}`);
  });
});
