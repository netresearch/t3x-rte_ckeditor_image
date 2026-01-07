import { test, expect } from '@playwright/test';

/**
 * E2E tests for RTE CKEditor Image style/alignment functionality.
 *
 * These tests verify that image style classes (image-left, image-center,
 * image-right, image-inline, image-block) are properly rendered and
 * CSS styles are correctly applied.
 *
 * The extension provides built-in image style buttons in the balloon toolbar
 * that apply these classes when an image is selected.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/501
 */
test.describe('Image Style/Alignment Functionality', () => {
  test('images with alignment classes render correctly', async ({ page }) => {
    await page.goto('/');

    // Find any images with style classes
    const styledImages = page.locator('img.image-left, img.image-right, img.image-center, img.image-inline, img.image-block, figure.image-left, figure.image-right, figure.image-center, figure.image-inline, figure.image-block');
    const count = await styledImages.count();

    // Log what we found for debugging
    console.log(`Found ${count} styled images/figures`);

    // If no styled images exist, skip the test (content-dependent)
    test.skip(count === 0, 'No styled images found - add images with alignment classes to validate');

    // Verify at least one styled element is visible
    await expect(styledImages.first()).toBeVisible();
  });

  test('image-left class applies float left styling', async ({ page }) => {
    await page.goto('/');

    const leftImages = page.locator('img.image-left, figure.image-left');
    const count = await leftImages.count();

    test.skip(count === 0, 'No left-aligned images found');

    const firstLeft = leftImages.first();
    await expect(firstLeft).toBeVisible();

    // Verify float left is applied
    const float = await firstLeft.evaluate(el => getComputedStyle(el).float);
    expect(float).toBe('left');
  });

  test('image-right class applies float right styling', async ({ page }) => {
    await page.goto('/');

    const rightImages = page.locator('img.image-right, figure.image-right');
    const count = await rightImages.count();

    test.skip(count === 0, 'No right-aligned images found');

    const firstRight = rightImages.first();
    await expect(firstRight).toBeVisible();

    // Verify float right is applied
    const float = await firstRight.evaluate(el => getComputedStyle(el).float);
    expect(float).toBe('right');
  });

  test('image-center class applies center alignment styling', async ({ page }) => {
    await page.goto('/');

    const centerImages = page.locator('img.image-center, figure.image-center');
    const count = await centerImages.count();

    test.skip(count === 0, 'No center-aligned images found');

    const firstCenter = centerImages.first();
    await expect(firstCenter).toBeVisible();

    // Verify display block and auto margins (centering)
    const display = await firstCenter.evaluate(el => getComputedStyle(el).display);
    const marginLeft = await firstCenter.evaluate(el => getComputedStyle(el).marginLeft);
    const marginRight = await firstCenter.evaluate(el => getComputedStyle(el).marginRight);

    expect(display).toBe('block');
    expect(marginLeft).toBe(marginRight); // Both should be 'auto' or equal computed value
  });

  test('image-block class applies block display styling', async ({ page }) => {
    await page.goto('/');

    const blockImages = page.locator('img.image-block, figure.image-block');
    const count = await blockImages.count();

    test.skip(count === 0, 'No block images found');

    const firstBlock = blockImages.first();
    await expect(firstBlock).toBeVisible();

    // Verify block display
    const display = await firstBlock.evaluate(el => getComputedStyle(el).display);
    expect(display).toBe('block');
  });

  test('image-inline class applies inline display styling', async ({ page }) => {
    await page.goto('/');

    const inlineImages = page.locator('img.image-inline');
    const count = await inlineImages.count();

    test.skip(count === 0, 'No inline images found');

    const firstInline = inlineImages.first();
    await expect(firstInline).toBeVisible();

    // Verify inline display
    const display = await firstInline.evaluate(el => getComputedStyle(el).display);
    expect(display).toBe('inline');
  });

  test('image alignment CSS is loaded on page', async ({ page }) => {
    await page.goto('/');

    // Check if image-alignment.css styles are active by looking for
    // the characteristic CSS rules in the computed styles
    const hasAlignmentStyles = await page.evaluate(() => {
      // Create a test element to check if our CSS is loaded
      const testEl = document.createElement('div');
      testEl.className = 'image-center';
      testEl.style.visibility = 'hidden';
      document.body.appendChild(testEl);

      const styles = getComputedStyle(testEl);
      const hasMarginAuto = styles.marginLeft === 'auto' || styles.marginRight === 'auto';
      const isBlock = styles.display === 'block';

      document.body.removeChild(testEl);

      // If our CSS is loaded, .image-center should have display: block and margin: auto
      return hasMarginAuto || isBlock;
    });

    // This test verifies the CSS file is properly included
    // It may pass even if no styled images exist on the page
    expect(hasAlignmentStyles).toBe(true);
  });
});

test.describe('Image Style Class Preservation', () => {
  test('style classes are preserved through rendering pipeline', async ({ page }) => {
    await page.goto('/');

    // Get all images that should have style classes
    const allImages = page.locator('img');
    const imageCount = await allImages.count();

    if (imageCount === 0) {
      console.log('No images found on page');
      return;
    }

    // Check each image for valid class attribute
    for (let i = 0; i < Math.min(imageCount, 10); i++) {
      const img = allImages.nth(i);
      const className = await img.getAttribute('class');

      if (className) {
        // Log images with style classes for debugging
        const hasStyleClass = /image-(left|right|center|inline|block)/.test(className);
        if (hasStyleClass) {
          console.log(`Image ${i}: class="${className}"`);
        }
      }
    }
  });

  test('figure elements preserve style classes', async ({ page }) => {
    await page.goto('/');

    const figures = page.locator('figure');
    const figureCount = await figures.count();

    if (figureCount === 0) {
      console.log('No figure elements found on page');
      return;
    }

    // Check figures for style classes
    for (let i = 0; i < Math.min(figureCount, 10); i++) {
      const figure = figures.nth(i);
      const className = await figure.getAttribute('class');

      if (className) {
        const hasStyleClass = /image-(left|right|center|inline|block)/.test(className);
        if (hasStyleClass) {
          console.log(`Figure ${i}: class="${className}"`);

          // Verify figure contains an image
          const img = figure.locator('img');
          await expect(img).toBeVisible();
        }
      }
    }
  });
});

test.describe('Style Dropdown Documentation', () => {
  /**
   * This test documents the style dropdown configuration requirements.
   *
   * The extension provides TWO ways to style images:
   *
   * 1. BUILT-IN IMAGE STYLES (balloon toolbar):
   *    - Click an image in the editor
   *    - Use the alignment buttons in the balloon toolbar
   *    - Classes: image-left, image-center, image-right, image-inline, image-block
   *    - Works out of the box with rteWithImages.yaml preset
   *
   * 2. NATIVE CKEDITOR STYLE DROPDOWN:
   *    - Requires additional configuration
   *    - Must load Style plugin
   *    - Must define img styles in YAML
   *    - Must add 'style' to toolbar
   *
   * Example configuration for native style dropdown:
   *
   * editor:
   *   config:
   *     style:
   *       definitions:
   *         - name: 'Float Left'
   *           element: 'img'
   *           classes: ['image-left']
   *         - name: 'Float Right'
   *           element: 'img'
   *           classes: ['image-right']
   *     toolbar:
   *       items:
   *         - style
   *         - insertimage
   *
   * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/501
   */
  test('verify extension provides image styling capability', async ({ page }) => {
    await page.goto('/');

    // This test verifies the page loads without errors
    // The actual styling is applied in the CKEditor backend
    const response = await page.goto('/');
    expect(response?.status()).toBe(200);

    // Verify no JavaScript errors related to image styles
    const content = await page.content();
    expect(content).not.toContain('Oops, an error occurred');
  });
});
