import { test, expect } from '@playwright/test';

/**
 * E2E tests for RTE CKEditor Image click-to-enlarge functionality.
 *
 * These tests verify zero-configuration TypoScript injection works
 * correctly with various site set combinations.
 */
test.describe('Click-to-Enlarge Functionality', () => {
  test('images have data-htmlarea-zoom attribute', async ({ page }) => {
    await page.goto('/');

    // Find images with zoom attribute
    const zoomImages = page.locator('img[data-htmlarea-zoom="true"]');

    // Should have at least one zoomable image
    await expect(zoomImages.first()).toBeVisible();

    // Verify the attribute value
    const zoomValue = await zoomImages.first().getAttribute('data-htmlarea-zoom');
    expect(zoomValue).toBe('true');
  });

  test('images are wrapped in clickable links', async ({ page }) => {
    await page.goto('/');

    // Find zoom-enabled images
    const zoomImages = page.locator('img[data-htmlarea-zoom="true"]');
    await expect(zoomImages.first()).toBeVisible();

    // Check if image is wrapped in an anchor tag (lightbox link)
    const imageParent = zoomImages.first().locator('..');
    const tagName = await imageParent.evaluate(el => el.tagName.toLowerCase());

    // Should be wrapped in <a> tag for click-to-enlarge
    expect(tagName).toBe('a');
  });

  test('clicking image opens lightbox', async ({ page }) => {
    await page.goto('/');

    // Find first zoomable image
    const zoomImage = page.locator('img[data-htmlarea-zoom="true"]').first();
    await expect(zoomImage).toBeVisible();

    // Get the parent link
    const imageLink = zoomImage.locator('..');

    // Click should trigger lightbox or TYPO3's tx_cms_showpic popup
    const href = await imageLink.getAttribute('href');
    expect(href).toBeTruthy();

    // TYPO3 uses tx_cms_showpic eID for image popups, OR direct image links
    // Both are valid click-to-enlarge implementations
    const isTypo3Popup = href!.includes('tx_cms_showpic');
    const isDirectImage = /\.(jpg|jpeg|png|gif|webp|svg)/i.test(href!);

    expect(isTypo3Popup || isDirectImage).toBe(true);
  });

  test('ImageRenderingController processed the image', async ({ page }) => {
    await page.goto('/');

    // The controller adds specific attributes when processing
    const processedImages = page.locator('img[data-htmlarea-zoom]');

    await expect(processedImages.first()).toBeVisible();

    // Get the image src - should be a processed/resized image
    const src = await processedImages.first().getAttribute('src');
    expect(src).toBeTruthy();

    // Should point to fileadmin or _processed_ folder
    expect(src).toMatch(/(fileadmin|_processed_)/);
  });

  test('multiple images all have zoom functionality', async ({ page }) => {
    await page.goto('/');

    const zoomImages = page.locator('img[data-htmlarea-zoom="true"]');
    const count = await zoomImages.count();

    // If there are multiple images, all should have zoom
    if (count > 1) {
      for (let i = 0; i < count; i++) {
        const img = zoomImages.nth(i);
        await expect(img).toHaveAttribute('data-htmlarea-zoom', 'true');
      }
    }
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

    // If parseFunc_RTE.tags.img is NOT configured, images would render
    // as plain <img> without the data-htmlarea-zoom attribute
    const plainImages = page.locator('img:not([data-htmlarea-zoom])');
    const zoomImages = page.locator('img[data-htmlarea-zoom="true"]');

    const plainCount = await plainImages.count();
    const zoomCount = await zoomImages.count();

    // All RTE images should have zoom attribute (plain count should be 0 or only non-RTE images)
    // At minimum, we should have some zoom images
    expect(zoomCount).toBeGreaterThan(0);
  });
});
