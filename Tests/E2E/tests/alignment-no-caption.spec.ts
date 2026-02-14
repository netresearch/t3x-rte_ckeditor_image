import { test, expect } from '@playwright/test';
import { gotoFrontendPage } from './helpers/typo3-backend';

/**
 * Regression tests for alignment classes without caption.
 *
 * Bug #595: Images with alignment classes (image-left, image-center, image-right)
 * but WITHOUT a caption were incorrectly wrapped in <figure> elements.
 * The fix ensures only captioned images get <figure> wrappers.
 *
 * Template selection logic (ImageRenderingService.selectTemplate()):
 *   $needsFigureWrapper = $hasCaption;
 *
 * Test content:
 *   CE 12: Alignment WITHOUT caption — should render as bare <img class="image-...">
 *   CE 13: Alignment WITH caption — should render as <figure class="..."><img><figcaption>
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/595
 */
test.describe('Alignment Without Caption (#595)', () => {
  test.beforeEach(async ({ page }) => {
    await gotoFrontendPage(page);
  });

  test('image-left without caption renders as bare <img>, not <figure>', async ({ page }) => {
    const img = page.locator('img[alt="Align Left No Caption"]');
    expect(await img.count(), 'Expected left-aligned image without caption (CE 12)').toBeGreaterThan(0);

    await expect(img.first()).toBeVisible();

    // Should have the alignment class on the <img> element
    await expect(img.first()).toHaveAttribute('class', /image-left/);

    // Should NOT be wrapped in a <figure> element
    const figure = img.first().locator('xpath=ancestor::figure');
    expect(await figure.count(), 'Aligned image without caption should NOT be in a <figure>').toBe(0);
  });

  test('image-center without caption renders as bare <img>, not <figure>', async ({ page }) => {
    const img = page.locator('img[alt="Align Center No Caption"]');
    expect(await img.count(), 'Expected center-aligned image without caption (CE 12)').toBeGreaterThan(0);

    await expect(img.first()).toBeVisible();
    await expect(img.first()).toHaveAttribute('class', /image-center/);

    const figure = img.first().locator('xpath=ancestor::figure');
    expect(await figure.count(), 'Aligned image without caption should NOT be in a <figure>').toBe(0);
  });

  test('image-right without caption renders as bare <img>, not <figure>', async ({ page }) => {
    const img = page.locator('img[alt="Align Right No Caption"]');
    expect(await img.count(), 'Expected right-aligned image without caption (CE 12)').toBeGreaterThan(0);

    await expect(img.first()).toBeVisible();
    await expect(img.first()).toHaveAttribute('class', /image-right/);

    const figure = img.first().locator('xpath=ancestor::figure');
    expect(await figure.count(), 'Aligned image without caption should NOT be in a <figure>').toBe(0);
  });
});

test.describe('Alignment With Caption (#595)', () => {
  test.beforeEach(async ({ page }) => {
    await gotoFrontendPage(page);
  });

  test('image-left with caption renders as <figure> with figcaption', async ({ page }) => {
    const img = page.locator('img[alt="Align Left With Caption"]');
    expect(await img.count(), 'Expected left-aligned captioned image (CE 13)').toBeGreaterThan(0);

    // Should be inside a <figure> element
    const figure = img.first().locator('xpath=ancestor::figure');
    expect(await figure.count(), 'Captioned image should be in a <figure>').toBeGreaterThan(0);

    // Figure should have the alignment class
    const figureClass = await figure.first().getAttribute('class');
    expect(figureClass).toMatch(/image-left/);

    // Should have a figcaption
    const caption = figure.first().locator('figcaption');
    expect(await caption.count(), 'Figure should have a <figcaption>').toBeGreaterThan(0);
    const captionText = await caption.textContent();
    expect(captionText?.trim()).toBe('Left caption');
  });

  test('image-center with caption renders as <figure> with figcaption', async ({ page }) => {
    const img = page.locator('img[alt="Align Center With Caption"]');
    expect(await img.count(), 'Expected center-aligned captioned image (CE 13)').toBeGreaterThan(0);

    const figure = img.first().locator('xpath=ancestor::figure');
    expect(await figure.count(), 'Captioned image should be in a <figure>').toBeGreaterThan(0);

    const figureClass = await figure.first().getAttribute('class');
    expect(figureClass).toMatch(/image-center/);

    const caption = figure.first().locator('figcaption');
    const captionText = await caption.textContent();
    expect(captionText?.trim()).toBe('Center caption');
  });

  test('image-right with caption renders as <figure> with figcaption', async ({ page }) => {
    const img = page.locator('img[alt="Align Right With Caption"]');
    expect(await img.count(), 'Expected right-aligned captioned image (CE 13)').toBeGreaterThan(0);

    const figure = img.first().locator('xpath=ancestor::figure');
    expect(await figure.count(), 'Captioned image should be in a <figure>').toBeGreaterThan(0);

    const figureClass = await figure.first().getAttribute('class');
    expect(figureClass).toMatch(/image-right/);

    const caption = figure.first().locator('figcaption');
    const captionText = await caption.textContent();
    expect(captionText?.trim()).toBe('Right caption');
  });

  test('alignment class is on <figure> not <img> when caption is present', async ({ page }) => {
    // When a caption is present, the alignment class should move to <figure>
    // and NOT remain on the <img> element (TagInFigure partial excludes class)
    const img = page.locator('img[alt="Align Left With Caption"]');
    expect(await img.count()).toBeGreaterThan(0);

    const imgClass = await img.first().getAttribute('class');
    // img inside figure should NOT have the alignment class
    expect(imgClass || '').not.toMatch(/image-left/);
  });
});
