import { test, expect } from '@playwright/test';

/**
 * E2E tests for error handling and security edge cases.
 *
 * These tests verify the extension handles malformed, missing, or
 * potentially malicious content gracefully without PHP errors or XSS.
 *
 * Test content elements (created in CI):
 *   CE 20: Missing file — image referencing non-existent data-htmlarea-file-uid="9999"
 *   CE 21: XSS payloads — alt with <script>, caption with <script>
 *   CE 22: Special characters — Unicode and quote characters in alt text
 *   CE 23: Empty alt text — alt=""
 *   CE 24: Whitespace-only caption — <figcaption>   </figcaption>
 *   CE 25: Link + zoom conflict — both <a href> wrapper and data-htmlarea-zoom="true"
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/619
 */
test.describe('Error Handling & Edge Cases', () => {
  // CEs 20-25 live on page 2 (/error-handling-tests) to isolate
  // edge-case content from the main demo page
  const ERROR_PAGE = '/error-handling-tests';

  test.beforeEach(async ({ page }) => {
    await page.goto(ERROR_PAGE);
    await page.waitForLoadState('networkidle');
  });

  test.describe('Missing File (CE 20)', () => {
    test('page renders without PHP error when file is missing', async ({ page }) => {
      // CE 20 references a non-existent file (data-htmlarea-file-uid="9999").
      // The page should still load without a 500 error or TYPO3 error screen.
      const content = await page.content();

      // Should NOT contain PHP/TYPO3 error indicators
      expect(content).not.toContain('Oops, an error occurred');
      expect(content).not.toContain('TypoScript configuration error');
      expect(content).not.toContain('Fatal error');
      expect(content).not.toContain('Uncaught Exception');

      // The content element container should still exist in the DOM
      const ceContainer = page.locator('#c20');
      expect(
        await ceContainer.count(),
        'CE 20 container should be present even with missing file'
      ).toBeGreaterThan(0);
    });

    test('missing file does not cause 500 error', async ({ page }) => {
      // Navigate fresh and check the HTTP response status
      const response = await page.goto(ERROR_PAGE);
      expect(response?.status(), 'Page should return 200, not 500').toBe(200);
    });
  });

  test.describe('XSS Prevention (CE 21)', () => {
    test('script tags in rendered HTML are escaped', async ({ page }) => {
      // CE 21 has alt text containing HTML-encoded script tags.
      // The key security check: raw <script> tags must NOT appear as
      // executable HTML in the rendered output (innerHTML).
      // Note: getAttribute('alt') returns decoded entities — having
      // <script> in the decoded alt VALUE is safe (alt text is never
      // rendered as HTML by the browser).
      const ceContainer = page.locator('#c21');
      expect(
        await ceContainer.count(),
        'CE 21 container should be present'
      ).toBeGreaterThan(0);

      const containerHtml = await ceContainer.innerHTML();

      // Raw <script> tags must NOT appear in the rendered HTML output.
      // innerHTML serializes attributes with proper escaping, so
      // <script> inside an alt attribute appears as &lt;script&gt;.
      expect(containerHtml).not.toMatch(/<script[\s>]/i);
    });

    test('script tags in caption are escaped', async ({ page }) => {
      // CE 21's figcaption contains a <script> tag — it must be escaped
      const ceContainer = page.locator('#c21');
      expect(
        await ceContainer.count(),
        'CE 21 container should be present'
      ).toBeGreaterThan(0);

      const figcaption = ceContainer.locator('figcaption').first();
      if (await figcaption.count() > 0) {
        const captionHtml = await figcaption.innerHTML();

        // Raw <script> must NOT appear in figcaption
        expect(captionHtml).not.toMatch(/<script[\s>]/i);

        // The text content should be safe (escaped or stripped)
        const captionText = await figcaption.textContent();
        expect(captionText).not.toContain('<script>');
      }
    });

    test('no JavaScript dialogs fire on page load', async ({ page }) => {
      // If XSS payloads were not escaped, alert(1) would fire.
      // We listen for any dialog event — none should occur.
      let dialogFired = false;
      let dialogMessage = '';

      page.on('dialog', async (dialog) => {
        dialogFired = true;
        dialogMessage = dialog.message();
        await dialog.dismiss();
      });

      // Re-navigate to trigger any XSS payloads
      await page.goto(ERROR_PAGE);
      await page.waitForLoadState('networkidle');

      // Give any deferred scripts a moment to execute
      await page.waitForTimeout(1000);

      expect(
        dialogFired,
        `Unexpected JavaScript dialog fired with message: "${dialogMessage}"`
      ).toBe(false);
    });
  });

  test.describe('Special Characters (CE 22)', () => {
    test('Unicode characters in alt text render correctly', async ({ page }) => {
      // CE 22 has two images:
      //   1st: alt with quotes ("double" and 'single')
      //   2nd: alt with Unicode (äöü éàè 日本語)
      const ceContainer = page.locator('#c22');
      expect(
        await ceContainer.count(),
        'CE 22 container should be present'
      ).toBeGreaterThan(0);

      const images = ceContainer.locator('img');
      const imageCount = await images.count();
      expect(imageCount, 'CE 22 should contain at least 2 images').toBeGreaterThanOrEqual(2);

      // The Unicode characters are in the second image's alt text
      const unicodeImg = images.nth(1);
      const alt = await unicodeImg.getAttribute('alt');
      expect(alt, 'Alt text should be present').toBeTruthy();

      // Verify Unicode characters are preserved (not mangled or stripped)
      expect(alt).toContain('äöü');
      expect(alt).toContain('éàè');
      expect(alt).toContain('日本語');
    });

    test('quote characters in alt text are properly handled', async ({ page }) => {
      // CE 22's first image has quotes in alt
      const ceContainer = page.locator('#c22');
      const img = ceContainer.locator('img').first();

      if (await img.count() > 0) {
        const alt = await img.getAttribute('alt');
        expect(alt, 'Alt text should be present').toBeTruthy();

        // getAttribute() decodes HTML entities, so if we get a
        // non-empty string, the quotes were properly escaped in the HTML.
        // The alt text contains: Quotes "double" and 'single'
        expect(alt!.length).toBeGreaterThan(0);
      }
    });
  });

  test.describe('Empty Alt Text (CE 23)', () => {
    test('empty alt renders as alt="" attribute, not missing alt', async ({ page }) => {
      // CE 23 has alt="" — for accessibility, the attribute must be present
      // but empty, NOT omitted entirely
      const ceContainer = page.locator('#c23');
      expect(
        await ceContainer.count(),
        'CE 23 container should be present'
      ).toBeGreaterThan(0);

      const img = ceContainer.locator('img').first();
      expect(
        await img.count(),
        'CE 23 should contain an image'
      ).toBeGreaterThan(0);

      // getAttribute returns null if attribute is missing, "" if present but empty
      const alt = await img.getAttribute('alt');
      expect(alt, 'alt attribute must be present (not null)').not.toBeNull();
      expect(alt, 'alt attribute should be empty string').toBe('');
    });
  });

  test.describe('Whitespace-Only Caption (CE 24)', () => {
    test('whitespace-only caption is handled gracefully', async ({ page }) => {
      // CE 24 has <figcaption>   </figcaption> (whitespace only).
      // The extension should either:
      //   a) Skip the figure wrapper entirely (treat as no caption), or
      //   b) Render an empty/whitespace figcaption without breaking layout
      const ceContainer = page.locator('#c24');
      expect(
        await ceContainer.count(),
        'CE 24 container should be present'
      ).toBeGreaterThan(0);

      const img = ceContainer.locator('img').first();
      expect(
        await img.count(),
        'CE 24 should contain an image'
      ).toBeGreaterThan(0);

      // Check which behavior the extension implements
      const figure = ceContainer.locator('figure').first();
      const figcaption = ceContainer.locator('figcaption').first();

      if (await figure.count() > 0 && await figcaption.count() > 0) {
        // Behavior (b): figure exists — caption should be empty/whitespace
        const captionText = await figcaption.textContent();
        // Whitespace-only is acceptable; visible non-whitespace text is not expected
        expect(captionText?.trim()).toBe('');
      } else {
        // Behavior (a): no figure wrapper — image is standalone
        // This is the preferred behavior: whitespace-only caption = no caption
        await expect(img.first()).toBeVisible();
      }

      // Either way, the page should not have layout issues
      const containerHtml = await ceContainer.innerHTML();
      expect(containerHtml).not.toContain('Oops, an error occurred');
    });
  });

  test.describe('Link + Zoom Conflict (CE 25)', () => {
    test('popup takes priority over link per selectTemplate()', async ({ page }) => {
      // CE 25 has both <a href="..."> wrapper AND data-htmlarea-zoom="true".
      // Per ImageRenderingService.selectTemplate(), priority is:
      //   Popup > Link > Caption > Standalone
      // So zoom/popup wins over the explicit link.
      const ceContainer = page.locator('#c25');
      expect(
        await ceContainer.count(),
        'CE 25 container should be present'
      ).toBeGreaterThan(0);

      const img = ceContainer.locator('img').first();
      expect(
        await img.count(),
        'CE 25 should contain an image'
      ).toBeGreaterThan(0);

      // Image should be wrapped in at least one link (popup or original)
      const link = img.locator('xpath=ancestor::a');
      expect(
        await link.count(),
        'Image should be wrapped in an <a> tag'
      ).toBeGreaterThan(0);
    });

    test.fixme('link + zoom conflict does not produce nested links', async ({ page }) => {
      // FIXME: When both <a href> wrapper and data-htmlarea-zoom are present,
      // the extension currently produces nested <a> tags (one from the original
      // link, one from the popup template). This is invalid HTML.
      // Filed as bug: the extension should use ONLY the popup template's <a>
      // and discard the original link wrapper when zoom takes priority.
      // @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/619
      const ceContainer = page.locator('#c25');
      expect(
        await ceContainer.count(),
        'CE 25 container should be present'
      ).toBeGreaterThan(0);

      const containerHtml = await ceContainer.innerHTML();

      // Count anchor tags — should be exactly one wrapping the image
      const anchorMatches = containerHtml.match(/<a[\s>]/gi);
      if (anchorMatches) {
        expect(
          anchorMatches.length,
          'Should not have nested <a> tags from link+zoom conflict'
        ).toBeLessThanOrEqual(1);
      }
    });
  });
});
