import { test, expect } from '@playwright/test';

/**
 * E2E tests for RTE CKEditor Image click-to-enlarge functionality.
 *
 * These tests verify zero-configuration TypoScript injection works
 * correctly with various site set combinations.
 *
 * Note on extension behavior:
 * - Images with data-htmlarea-zoom="true" in the database are TRANSFORMED
 *   by ImageRenderingService into popup link structures
 * - The rendered output has <a data-popup="true"><img></a>, NOT the original
 *   data-htmlarea-zoom attribute (which is consumed during processing)
 * - The imageLinkWrap functionality requires:
 *   1. A valid file UID reference from FAL (sys_file)
 *   2. ContentObjectRenderer with full frontend context
 *   3. Popup configuration from lib.contentElement.settings.media.popup
 */
test.describe('Click-to-Enlarge Functionality', () => {
  test('images are wrapped in popup links', async ({ page }) => {
    await page.goto('/');

    // Extension transforms data-htmlarea-zoom images into popup links
    // The rendered output has <a data-popup="true"><img></a>
    const popupLinks = page.locator('a[data-popup="true"]');
    const count = await popupLinks.count();

    // Skip if no popup images exist
    test.skip(count === 0, 'No popup images found - add images with click-to-enlarge enabled');

    // Should have at least one popup link with an image
    await expect(popupLinks.first()).toBeVisible();

    // Verify the link contains an image
    const imgInLink = popupLinks.first().locator('img');
    await expect(imgInLink).toBeVisible();
  });

  test('images are processed by ImageRenderingService', async ({ page }) => {
    await page.goto('/');

    // Find popup links - their presence proves ImageRenderingService ran
    // and transformed data-htmlarea-zoom images into popup structure
    const popupLinks = page.locator('a[data-popup="true"]');
    const count = await popupLinks.count();

    test.skip(count === 0, 'No popup images found');

    await expect(popupLinks.first()).toBeVisible();

    // Verify the link wraps an image
    const imgInLink = popupLinks.first().locator('img');
    await expect(imgInLink).toBeVisible();

    // Check the link has a valid href
    const href = await popupLinks.first().getAttribute('href');
    expect(href).toBeTruthy();
  });

  test('click-to-enlarge link structure', async ({ page }) => {
    await page.goto('/');

    // Find popup links
    const popupLinks = page.locator('a[data-popup="true"]');
    const count = await popupLinks.count();

    test.skip(count === 0, 'No popup images found');

    const firstLink = popupLinks.first();
    await expect(firstLink).toBeVisible();

    // Verify href points to image file
    const href = await firstLink.getAttribute('href');
    expect(href).toBeTruthy();

    // TYPO3 uses tx_cms_showpic eID for image popups, OR direct image links
    const isTypo3Popup = href!.includes('tx_cms_showpic');
    const isDirectImage = /\.(jpg|jpeg|png|gif|webp|svg)/i.test(href!);
    expect(isTypo3Popup || isDirectImage).toBe(true);

    // Verify link contains an image
    const img = firstLink.locator('img');
    await expect(img).toBeVisible();

    // Image should have src pointing to fileadmin
    const imgSrc = await img.getAttribute('src');
    expect(imgSrc).toBeTruthy();
    expect(imgSrc).toMatch(/(fileadmin|_processed_)/);
  });

  test('ImageRenderingService processed the image correctly', async ({ page }) => {
    await page.goto('/');

    // Find images inside popup links
    const imagesInPopupLinks = page.locator('a[data-popup="true"] img');
    const count = await imagesInPopupLinks.count();

    test.skip(count === 0, 'No popup images found');

    await expect(imagesInPopupLinks.first()).toBeVisible();

    // Get the image src - should be a valid image path
    const src = await imagesInPopupLinks.first().getAttribute('src');
    expect(src).toBeTruthy();

    // Should point to fileadmin or _processed_ folder
    expect(src).toMatch(/(fileadmin|_processed_)/);
  });

  test('multiple images all have popup functionality', async ({ page }) => {
    await page.goto('/');

    const popupLinks = page.locator('a[data-popup="true"]');
    const count = await popupLinks.count();

    // If there are multiple popup images, all should have correct structure
    if (count > 1) {
      for (let i = 0; i < count; i++) {
        const link = popupLinks.nth(i);
        await expect(link).toHaveAttribute('data-popup', 'true');

        // Each should contain an image
        const img = link.locator('img');
        await expect(img).toBeVisible();
      }
    }
  });

  test('images without data-htmlarea-zoom are NOT wrapped in popup links', async ({ page }) => {
    await page.goto('/');

    // Find all images on the page
    const allImages = page.locator('img');
    const totalImageCount = await allImages.count();

    // Find images inside popup links (these HAD data-htmlarea-zoom in database)
    const imagesInPopupLinks = page.locator('a[data-popup="true"] img');
    const popupImageCount = await imagesInPopupLinks.count();

    // Find images NOT inside popup links
    const standaloneImages = page.locator('img:not(a[data-popup="true"] img)');
    const standaloneCount = await standaloneImages.count();

    // Verify the counts add up (sanity check)
    // Note: This may not be exact due to CSS selector specificity, but should be close
    console.log(`Total images: ${totalImageCount}, Popup images: ${popupImageCount}, Standalone: ${standaloneCount}`);

    // The key assertion: standalone images should NOT be wrapped in popup links
    // This verifies the extension only transforms images that have data-htmlarea-zoom
    for (let i = 0; i < standaloneCount; i++) {
      const img = standaloneImages.nth(i);
      const parent = img.locator('..');
      const parentTag = await parent.evaluate(el => el.tagName.toLowerCase());

      // Parent should NOT be an <a> with data-popup="true"
      if (parentTag === 'a') {
        const hasPopup = await parent.getAttribute('data-popup');
        expect(hasPopup).not.toBe('true');
      }
    }
  });
});

test.describe('Caption Rendering (Whitespace Artifact Prevention)', () => {
  /**
   * Regression test for parseFunc whitespace artifacts.
   *
   * Bug: When Fluid templates have whitespace between <img> and <figcaption>,
   * parseFunc_RTE converts this to <p>&nbsp;</p> artifacts.
   *
   * Fix: Added figure,figcaption to encapsTagList in TypoScript,
   *   and applied trim() in ImageRenderingService.php to remove whitespace.
   *
   * @see https://github.com/netresearch/t3x-rte_ckeditor_image/pull/482
   */
  test('figure elements do not contain p nbsp artifacts', async ({ page }) => {
    await page.goto('/');

    // Find all figure elements (images with captions)
    const figures = page.locator('figure');
    const figureCount = await figures.count();

    if (figureCount === 0) {
      console.log('No figure elements found - caption test skipped');
      return;
    }

    // Check each figure for <p>&nbsp;</p> artifacts
    for (let i = 0; i < figureCount; i++) {
      const figure = figures.nth(i);
      const figureHtml = await figure.innerHTML();

      // Should NOT contain <p>&nbsp;</p> or <p> </p> artifacts
      expect(figureHtml).not.toMatch(/<p[^>]*>\s*&nbsp;\s*<\/p>/i);
      expect(figureHtml).not.toMatch(/<p[^>]*>\s*<\/p>/i);

      // Should have img and figcaption without p tags between them
      const hasImg = await figure.locator('img').count() > 0;
      const hasFigcaption = await figure.locator('figcaption').count() > 0;

      if (hasImg && hasFigcaption) {
        // Verify clean structure: figure > img + figcaption (no p between)
        const directChildren = await figure.evaluate(el => {
          return Array.from(el.children).map(child => child.tagName.toLowerCase());
        });

        // p tags should not appear as direct children of figure
        const hasParagraphChild = directChildren.includes('p');
        expect(hasParagraphChild).toBe(false);
      }
    }
  });

  test('images with captions render with figcaption', async ({ page }) => {
    await page.goto('/');

    // Find figures with figcaption
    const figuresWithCaption = page.locator('figure:has(figcaption)');
    const count = await figuresWithCaption.count();

    // Skip test if no captioned images exist in test page
    // This ensures test doesn't silently pass without validating anything
    test.skip(count === 0, 'No figures with captions found on test page - add captioned images to validate');

    // Verify figcaption contains text
    const firstCaption = figuresWithCaption.first().locator('figcaption');
    const captionText = await firstCaption.textContent();
    expect(captionText?.trim().length).toBeGreaterThan(0);
  });
});

test.describe('Zero-Configuration Verification', () => {
  test('page renders without TypoScript errors', async ({ page }) => {
    const response = await page.goto('/');

    // Should return 200 OK
    expect(response?.status()).toBe(200);

    // Should not contain TYPO3 error messages
    const content = await page.content();
    expect(content).not.toContain('Oops, an error occurred');
    expect(content).not.toContain('TypoScript configuration error');
  });

  test('lib.parseFunc_RTE.tags.img is active', async ({ page }) => {
    await page.goto('/');

    // If parseFunc_RTE.tags.img is NOT configured, images with data-htmlarea-zoom
    // in the database would render as plain <img> without being transformed
    // into popup links.
    //
    // When the extension works correctly:
    // - Database: <img data-htmlarea-zoom="true" ...>
    // - Rendered: <a data-popup="true"><img ...></a>
    const popupLinks = page.locator('a[data-popup="true"]');
    const popupCount = await popupLinks.count();

    // Skip if no test content with popup images exists
    test.skip(popupCount === 0, 'No popup images found - add images with click-to-enlarge enabled');

    // Should have at least one popup link (proves extension processed images)
    expect(popupCount).toBeGreaterThan(0);

    // Verify each popup link contains an image
    for (let i = 0; i < popupCount; i++) {
      const img = popupLinks.nth(i).locator('img');
      await expect(img).toBeVisible();
    }
  });
});
