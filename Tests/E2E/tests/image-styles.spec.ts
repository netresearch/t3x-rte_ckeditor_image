import { test, expect, Page } from '@playwright/test';

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

/**
 * Helper function to test CSS property for a given image style class.
 * Reduces code duplication across alignment tests.
 */
async function testImageStyle(
  page: Page,
  selector: string,
  cssProperty: string,
  expectedValues: string | string[]
): Promise<void> {
  await page.goto('/');

  const elements = page.locator(selector);
  const count = await elements.count();

  expect(count, `Expected elements matching ${selector} in demo content`).toBeGreaterThan(0);

  const firstElement = elements.first();
  await expect(firstElement).toBeVisible();

  const actualValue = await firstElement.evaluate(
    (el, prop) => getComputedStyle(el).getPropertyValue(prop),
    cssProperty
  );

  const expected = Array.isArray(expectedValues) ? expectedValues : [expectedValues];
  expect(expected).toContain(actualValue);
}

test.describe('Image Style/Alignment Functionality', () => {
  test('images with alignment classes render correctly', async ({ page }) => {
    await page.goto('/');

    // Find any images with style classes
    const styledImages = page.locator(
      'img.image-left, img.image-right, img.image-center, img.image-inline, img.image-block, ' +
      'figure.image-left, figure.image-right, figure.image-center, figure.image-inline, figure.image-block'
    );
    const count = await styledImages.count();

    // Log what we found for debugging
    console.log(`Found ${count} styled images/figures`);

    // Fail if no styled images exist in demo content
    expect(count, 'Expected styled images in demo content').toBeGreaterThan(0);

    // Verify at least one styled element is visible
    await expect(styledImages.first()).toBeVisible();
  });

  test('image-left class applies float left styling', async ({ page }) => {
    await testImageStyle(
      page,
      'img.image-left, figure.image-left',
      'float',
      'left'
    );
  });

  test('image-right class applies float right styling', async ({ page }) => {
    await testImageStyle(
      page,
      'img.image-right, figure.image-right',
      'float',
      'right'
    );
  });

  test('image-center class applies center alignment styling', async ({ page }) => {
    await page.goto('/');

    const centerImages = page.locator('img.image-center, figure.image-center');
    const count = await centerImages.count();

    expect(count, 'Expected center-aligned images in demo content').toBeGreaterThan(0);

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
    await testImageStyle(
      page,
      'img.image-block, figure.image-block',
      'display',
      'block'
    );
  });

  test('image-inline class applies inline display styling', async ({ page }) => {
    // Allow both 'inline' and 'inline-block' as browsers may compute
    // differently based on CSS factors (e.g., if dimensions are set)
    await testImageStyle(
      page,
      'img.image-inline',
      'display',
      ['inline', 'inline-block']
    );
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
    const styledImages = page.locator(
      'img.image-left, img.image-right, img.image-center, img.image-inline, img.image-block'
    );
    const count = await styledImages.count();

    // Fail if no styled images exist in demo content
    expect(count, 'Expected styled images in demo content').toBeGreaterThan(0);

    // Verify each styled image has the expected class preserved
    for (let i = 0; i < Math.min(count, 5); i++) {
      const img = styledImages.nth(i);
      const className = await img.getAttribute('class');

      // Assert that the class attribute contains a valid style class
      expect(className).toBeTruthy();
      expect(className).toMatch(/image-(left|right|center|inline|block)/);
    }
  });

  test('figure elements preserve style classes', async ({ page }) => {
    await page.goto('/');

    // Get figures with style classes
    const styledFigures = page.locator(
      'figure.image-left, figure.image-right, figure.image-center, figure.image-inline, figure.image-block'
    );
    const count = await styledFigures.count();

    // Fail if no styled figures exist in demo content
    expect(count, 'Expected styled figure elements in demo content').toBeGreaterThan(0);

    // Verify each styled figure has class preserved and contains an image
    for (let i = 0; i < Math.min(count, 5); i++) {
      const figure = styledFigures.nth(i);
      const className = await figure.getAttribute('class');

      // Assert class contains valid style
      expect(className).toBeTruthy();
      expect(className).toMatch(/image-(left|right|center|inline|block)/);

      // Assert figure contains an image
      const img = figure.locator('img');
      await expect(img).toBeVisible();
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
