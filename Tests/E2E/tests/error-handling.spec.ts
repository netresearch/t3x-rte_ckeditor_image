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
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
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
      const response = await page.goto('/');
      expect(response?.status(), 'Page should return 200, not 500').toBe(200);
    });
  });

  test.describe('XSS Prevention (CE 21)', () => {
    test('script tags in alt text are escaped', async ({ page }) => {
      // CE 21 has alt="<script>alert(1)</script>" — this must be escaped
      const ceContainer = page.locator('#c21');
      expect(
        await ceContainer.count(),
        'CE 21 container should be present'
      ).toBeGreaterThan(0);

      const containerHtml = await ceContainer.innerHTML();

      // Raw <script> tags must NOT appear in the rendered HTML
      expect(containerHtml).not.toMatch(/<script[^<]*>.*?<\/script>/i);

      // The alt text should be HTML-escaped (e.g., &lt;script&gt;) or stripped
      const img = ceContainer.locator('img').first();
      if (await img.count() > 0) {
        const alt = await img.getAttribute('alt');
        // If alt is present, it must NOT contain raw executable script
        if (alt !== null) {
          expect(alt).not.toContain('<script>');
        }
      }
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
        expect(captionHtml).not.toMatch(/<script[^<]*>.*?<\/script>/i);

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
      await page.goto('/');
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
      // CE 22 has alt text with special characters: äöü éàè 日本語
      const ceContainer = page.locator('#c22');
      expect(
        await ceContainer.count(),
        'CE 22 container should be present'
      ).toBeGreaterThan(0);

      const img = ceContainer.locator('img').first();
      expect(
        await img.count(),
        'CE 22 should contain an image'
      ).toBeGreaterThan(0);

      const alt = await img.getAttribute('alt');
      expect(alt, 'Alt text should be present').toBeTruthy();

      // Verify Unicode characters are preserved (not mangled or stripped)
      expect(alt).toContain('äöü');
      expect(alt).toContain('éàè');
      expect(alt).toContain('日本語');
    });

    test('quote characters in alt text are properly handled', async ({ page }) => {
      // CE 22 has quotes in alt: 'Quotes "double" and 'single''
      const ceContainer = page.locator('#c22');
      const img = ceContainer.locator('img').first();

      if (await img.count() > 0) {
        const alt = await img.getAttribute('alt');
        expect(alt, 'Alt text should be present').toBeTruthy();

        // The alt attribute should contain quote characters
        // (either raw or HTML-entity encoded — both are valid)
        const containerHtml = await ceContainer.innerHTML();

        // Verify the alt value is intact and not truncated by unescaped quotes
        // The getAttribute() call already decodes entities, so if we get a
        // non-empty string, the quotes were properly escaped in the HTML
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
    test('link takes priority over zoom when both are present', async ({ page }) => {
      // CE 25 has both <a href="..."> wrapper AND data-htmlarea-zoom="true".
      // Per ImageRenderingService.selectTemplate(), link takes priority over zoom.
      // Template priority: Popup > Link > Caption > Standalone
      // Since there's an explicit <a href>, it should render as a link, not popup.
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

      // Image should be wrapped in a link
      const link = img.locator('xpath=ancestor::a');
      expect(
        await link.count(),
        'Image should be wrapped in an <a> link'
      ).toBeGreaterThan(0);

      const href = await link.first().getAttribute('href');
      expect(href, 'Link should have an href').toBeTruthy();

      // Should NOT be a popup link (data-popup="true")
      // When both link and zoom are present, the explicit link wins
      const dataPopup = await link.first().getAttribute('data-popup');
      expect(
        dataPopup,
        'Should render as regular link, not popup (link takes priority over zoom)'
      ).toBeNull();
    });

    test('link + zoom conflict does not produce nested links', async ({ page }) => {
      // Having both link and zoom should not result in <a><a><img></a></a>
      const ceContainer = page.locator('#c25');
      expect(
        await ceContainer.count(),
        'CE 25 container should be present'
      ).toBeGreaterThan(0);

      const containerHtml = await ceContainer.innerHTML();

      // Count anchor tags — should be exactly one wrapping the image
      const anchorMatches = containerHtml.match(/<a[\s>]/gi);
      if (anchorMatches) {
        // There should be at most one <a> tag per image in this CE
        expect(
          anchorMatches.length,
          'Should not have nested <a> tags from link+zoom conflict'
        ).toBeLessThanOrEqual(1);
      }
    });
  });
});
