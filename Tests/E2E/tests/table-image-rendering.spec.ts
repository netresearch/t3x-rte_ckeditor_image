import { test, expect } from '@playwright/test';
import { gotoFrontendPage } from './helpers/typo3-backend';

/**
 * Regression tests for images inside CKEditor 5 table figures (#698).
 *
 * Bug: CKEditor 5 wraps tables in <figure class="table">. When parseFunc_RTE
 * captures this as an externalBlock, renderFigure() sees it's not an image
 * figure (hasFigureWrapper returns false) and returned the HTML unchanged.
 * Result: nested <figure class="image"> inside the table never got processed.
 *
 * Fix: renderFigure() now extracts the inner content of non-image figures
 * and re-processes it through parseFunc_RTE, avoiding infinite recursion
 * (the outer <figure> tags are stripped before re-processing).
 *
 * Test content: CE with <figure class="table"><table>...<figure class="image"><img>...
 * Two rows: one with zoom + caption, one plain image.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/698
 */
test.describe('Table Image Rendering (#698)', () => {
  test.beforeEach(async ({ page }) => {
    await gotoFrontendPage(page);
  });

  test('image inside table cell is rendered with processed src', async ({ page }) => {
    // Scope to table to avoid matching images from other CEs
    const img = page.locator('table img[alt="Table Image Zoom"]');
    expect(await img.count(), 'Expected table image with alt="Table Image Zoom"').toBeGreaterThan(0);

    await expect(img.first()).toBeVisible();

    // Image src should be resolved (not empty or broken)
    const src = await img.first().getAttribute('src');
    expect(src).toBeTruthy();
    expect(src).toMatch(/(fileadmin|_processed_)/);
  });

  test('image figure inside table has max-width style', async ({ page }) => {
    const img = page.locator('table img[alt="Table Image Zoom"]');
    expect(await img.count(), 'Expected table image with alt="Table Image Zoom"').toBeGreaterThan(0);

    // The image should be inside a <figure> with max-width style
    const figure = img.first().locator('xpath=ancestor::figure[contains(@class,"image")]');
    expect(await figure.count(), 'Image should be in a <figure class="image">').toBeGreaterThan(0);

    const style = await figure.first().getAttribute('style');
    expect(style, 'Figure should have inline max-width style').toBeTruthy();
    expect(style).toContain('max-width');
  });

  test('zoomable image in table gets popup link wrapper', async ({ page }) => {
    const img = page.locator('table img[alt="Table Image Zoom"]');
    expect(await img.count(), 'Expected zoomable table image').toBeGreaterThan(0);

    // The zoomable image should be wrapped in an <a> tag (popup link)
    const link = img.first().locator('xpath=ancestor::a');
    expect(await link.count(), 'Zoom image should be wrapped in <a> link').toBeGreaterThan(0);
  });

  test('plain image in table cell is rendered correctly', async ({ page }) => {
    const img = page.locator('table img[alt="Table Image Plain"]');
    expect(await img.count(), 'Expected plain table image').toBeGreaterThan(0);

    await expect(img.first()).toBeVisible();

    const src = await img.first().getAttribute('src');
    expect(src).toBeTruthy();
    expect(src).toMatch(/(fileadmin|_processed_)/);
  });

  test('table figure caption is preserved in rendered output', async ({ page }) => {
    // Scope to table cells to avoid matching captions from other CEs
    const caption = page.locator('table figcaption', { hasText: 'Zoomable image in table' });
    expect(await caption.count(), 'Expected figcaption with table image caption text').toBeGreaterThan(0);
  });

  test('linked image in table cell has correct link wrapper', async ({ page }) => {
    const img = page.locator('table img[alt="Table Image Linked"]');
    expect(await img.count(), 'Expected linked table image').toBeGreaterThan(0);

    await expect(img.first()).toBeVisible();

    // Linked image should be wrapped in <a> tag with the original href
    const link = img.first().locator('xpath=ancestor::a');
    expect(await link.count(), 'Linked image should be wrapped in <a>').toBeGreaterThan(0);

    const href = await link.first().getAttribute('href');
    expect(href).toContain('typo3.org');
  });
});
