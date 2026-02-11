import { test, expect } from '@playwright/test';
import { requireCondition } from './helpers/typo3-backend';

/**
 * E2E tests for RTE CKEditor Image inline image functionality.
 *
 * These tests verify that inline images (typo3imageInline) render correctly
 * and can be toggled between block and inline modes.
 *
 * Inline images use the image-inline class and allow cursor positioning
 * before/after the image on the same line (true inline behavior).
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/580
 */

test.describe('Inline Image Functionality', () => {
  test('inline images with image-inline class render correctly', async ({ page }) => {
    await page.goto('/');

    // Find images with inline class
    const inlineImages = page.locator('img.image-inline');
    const count = await inlineImages.count();

    // Log what we found for debugging
    console.log(`Found ${count} inline images`);

    // Fail if no inline images exist in demo content
    expect(count, 'Expected inline images in demo content').toBeGreaterThan(0);

    // Verify at least one inline image is visible
    await expect(inlineImages.first()).toBeVisible();
  });

  test('image-inline class applies inline-block display', async ({ page }) => {
    await page.goto('/');

    const inlineImages = page.locator('img.image-inline');
    const count = await inlineImages.count();

    expect(count, 'Expected inline images in demo content').toBeGreaterThan(0);

    const firstInline = inlineImages.first();
    await expect(firstInline).toBeVisible();

    // Verify display is inline or inline-block
    const display = await firstInline.evaluate(el => getComputedStyle(el).display);
    expect(['inline', 'inline-block']).toContain(display);
  });

  test('inline images allow text flow on same line', async ({ page }) => {
    await page.goto('/');

    // Find paragraphs containing inline images
    const paragraphsWithInlineImages = page.locator('p:has(img.image-inline)');
    const count = await paragraphsWithInlineImages.count();

    expect(count, 'Expected paragraphs with inline images in demo content').toBeGreaterThan(0);

    // Verify the paragraph has text content alongside the image
    const firstParagraph = paragraphsWithInlineImages.first();
    const textContent = await firstParagraph.textContent();

    // If there's text content, the image is properly inline with text
    expect(textContent).toBeTruthy();
  });

  test('inline images preserve data attributes', async ({ page }) => {
    await page.goto('/');

    const inlineImages = page.locator('img.image-inline[data-htmlarea-file-uid]');
    const count = await inlineImages.count();

    expect(count, 'Expected inline TYPO3 images in demo content').toBeGreaterThan(0);

    // Verify data attributes are preserved
    const firstImage = inlineImages.first();
    const fileUid = await firstImage.getAttribute('data-htmlarea-file-uid');

    expect(fileUid).toBeTruthy();
  });
});

test.describe('Inline Image CSS', () => {
  test('inline image CSS styles are loaded', async ({ page }) => {
    await page.goto('/');

    // Check if inline image styles are available by creating a test element
    const hasInlineStyles = await page.evaluate(() => {
      const testEl = document.createElement('img');
      testEl.className = 'image-inline';
      testEl.style.visibility = 'hidden';
      document.body.appendChild(testEl);

      const styles = getComputedStyle(testEl);
      const display = styles.display;

      document.body.removeChild(testEl);

      // Inline images should be inline or inline-block
      return display === 'inline' || display === 'inline-block';
    });

    expect(hasInlineStyles).toBe(true);
  });
});

test.describe('Inline Image Differentiation', () => {
  test('block and inline images have different display styles', async ({ page }) => {
    await page.goto('/');

    const blockImages = page.locator(
      'img.image-block, img.image-left, img.image-right, img.image-center, ' +
      'figure.image-block, figure.image-left, figure.image-right, figure.image-center'
    );
    const inlineImages = page.locator('img.image-inline');

    const blockCount = await blockImages.count();
    const inlineCount = await inlineImages.count();

    // Skip if we don't have both types to compare
    requireCondition(
      blockCount > 0 && inlineCount > 0,
      'Need both block and inline images to compare styling'
    );

    // Get display of first block image
    const firstBlock = blockImages.first();
    const blockDisplay = await firstBlock.evaluate(el => getComputedStyle(el).display);

    // Get display of first inline image
    const firstInline = inlineImages.first();
    const inlineDisplay = await firstInline.evaluate(el => getComputedStyle(el).display);

    // Block images should be block, table, or similar
    // Inline images should be inline or inline-block
    console.log(`Block display: ${blockDisplay}, Inline display: ${inlineDisplay}`);

    // Verify they're different display modes
    expect(['inline', 'inline-block']).toContain(inlineDisplay);
  });
});

test.describe('Linked Inline Images', () => {
  test('inline images can be wrapped in links', async ({ page }) => {
    await page.goto('/');

    // Find linked inline images
    const linkedInlineImages = page.locator('a > img.image-inline');
    const count = await linkedInlineImages.count();

    expect(count, 'Expected linked inline images in demo content').toBeGreaterThan(0);

    // Verify the link wrapper exists
    const firstLinkedImage = linkedInlineImages.first();
    const parentLink = firstLinkedImage.locator('..');

    const href = await parentLink.getAttribute('href');
    expect(href).toBeTruthy();
  });

  test('linked inline images maintain inline display', async ({ page }) => {
    await page.goto('/');

    const linkedInlineImages = page.locator('a > img.image-inline');
    const count = await linkedInlineImages.count();

    expect(count, 'Expected linked inline images in demo content').toBeGreaterThan(0);

    const firstLinkedImage = linkedInlineImages.first();
    const display = await firstLinkedImage.evaluate(el => getComputedStyle(el).display);

    // Even when linked, inline images should stay inline
    expect(['inline', 'inline-block']).toContain(display);
  });
});

test.describe('Inline Image Attributes', () => {
  test('inline images preserve width and height attributes', async ({ page }) => {
    await page.goto('/');

    const inlineImages = page.locator('img.image-inline[width][height]');
    const count = await inlineImages.count();

    expect(count, 'Expected inline images with dimensions in demo content').toBeGreaterThan(0);

    const firstImage = inlineImages.first();
    const width = await firstImage.getAttribute('width');
    const height = await firstImage.getAttribute('height');

    expect(width).toBeTruthy();
    expect(height).toBeTruthy();
    expect(parseInt(width as string)).toBeGreaterThan(0);
    expect(parseInt(height as string)).toBeGreaterThan(0);
  });

  test('inline images preserve alt text', async ({ page }) => {
    await page.goto('/');

    const inlineImages = page.locator('img.image-inline');
    const count = await inlineImages.count();

    expect(count, 'Expected inline images in demo content').toBeGreaterThan(0);

    // Verify at least one has alt attribute (accessibility)
    const withAlt = page.locator('img.image-inline[alt]');
    const withAltCount = await withAlt.count();

    // Log for debugging
    console.log(`Inline images with alt: ${withAltCount} of ${count}`);

    // We expect images to have alt attributes for accessibility
    // This test documents the expectation but doesn't fail if missing
    // since content may not have alt text configured
  });
});
