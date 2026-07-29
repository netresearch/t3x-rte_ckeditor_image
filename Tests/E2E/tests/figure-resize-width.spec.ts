import { test, expect } from '@playwright/test';
import { gotoFrontendPage } from './helpers/typo3-backend';

/**
 * Regression tests for CKEditor 5 image resize in the frontend (#863).
 *
 * CKEditor 5's resize handles store the chosen size as a `width` declaration on
 * the <figure> element (class `image_resized`) and leave the <img> at its
 * intrinsic pixel size. The frontend replaced that declaration with the computed
 * `max-width`, so a 25%-wide image rendered at full width.
 *
 * These tests assert rendered geometry rather than markup: unit and functional
 * tests can only prove which attributes are emitted, but the bug was that the
 * image came out the wrong SIZE. Only a real layout engine settles that.
 *
 * Fixture: CE "Image Resize (#863)" seeded in Build/Scripts/runTests.sh.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/863
 */
test.describe('Figure Resize Width (#863)', () => {
  test.beforeEach(async ({ page }) => {
    await gotoFrontendPage(page);
  });

  /**
   * Width of an element relative to the width of its offset parent.
   */
  async function relativeWidth(page: import('@playwright/test').Page, alt: string): Promise<number> {
    return page.evaluate((imageAlt) => {
      const img = document.querySelector<HTMLImageElement>(`img[alt="${imageAlt}"]`);
      if (img === null) {
        throw new Error(`No image with alt="${imageAlt}"`);
      }

      // The outermost element the extension emits for this image: the figure
      // when one is rendered, otherwise the image itself.
      const box: HTMLElement = img.closest('figure') ?? img;
      const parent = box.parentElement;
      if (parent === null) {
        throw new Error(`No parent for alt="${imageAlt}"`);
      }

      return box.getBoundingClientRect().width / parent.getBoundingClientRect().width;
    }, alt);
  }

  test('percentage resize with caption renders at the stored fraction', async ({ page }) => {
    const ratio = await relativeWidth(page, 'Resize Percent Caption');

    // Stored as width:25%. Before the fix this rendered at the full container
    // width (ratio ~1.0), which is exactly the reported symptom.
    expect(ratio).toBeGreaterThan(0.2);
    expect(ratio).toBeLessThan(0.3);
  });

  test('percentage resize without caption renders at the stored fraction', async ({ page }) => {
    // No caption means no <figure> wrapper is emitted (see #595), so the width
    // has to travel on the <img> instead.
    const ratio = await relativeWidth(page, 'Resize Percent No Caption');

    expect(ratio).toBeGreaterThan(0.2);
    expect(ratio).toBeLessThan(0.3);
  });

  test('image fills its resized figure instead of overflowing it', async ({ page }) => {
    // The <img> keeps its intrinsic width attribute (400). Without a width:100%
    // on the image, a figure narrowed to 25% would simply be overflowed.
    const overflow = await page.evaluate(() => {
      const img = document.querySelector<HTMLImageElement>('img[alt="Resize Percent Caption"]');
      const figure = img?.closest('figure');
      if (img === undefined || img === null || figure === undefined || figure === null) {
        throw new Error('Resized figure not found');
      }

      return img.getBoundingClientRect().width - figure.getBoundingClientRect().width;
    });

    expect(overflow).toBeLessThanOrEqual(1);
  });

  test('resized image keeps its aspect ratio', async ({ page }) => {
    // width/height attributes are 400x300. A relative width without height:auto
    // would keep the height pinned at 300 and squash the image.
    const ratio = await page.evaluate(() => {
      const img = document.querySelector<HTMLImageElement>('img[alt="Resize Percent Caption"]');
      if (img === null) {
        throw new Error('Resized image not found');
      }

      const rect = img.getBoundingClientRect();

      return rect.width / rect.height;
    });

    expect(ratio).toBeGreaterThan(1.2);
    expect(ratio).toBeLessThan(1.5);
  });

  test('pixel resize renders at the stored pixel width', async ({ page }) => {
    const width = await page.evaluate(() => {
      const img = document.querySelector<HTMLImageElement>('img[alt="Resize Pixels Caption"]');
      const figure = img?.closest('figure');
      if (figure === undefined || figure === null) {
        throw new Error('Pixel-resized figure not found');
      }

      return figure.getBoundingClientRect().width;
    });

    expect(width).toBeGreaterThan(110);
    expect(width).toBeLessThan(130);
  });

  test('resized linked image keeps both its width and its link', async ({ page }) => {
    const ratio = await relativeWidth(page, 'Resize Linked');

    expect(ratio).toBeGreaterThan(0.2);
    expect(ratio).toBeLessThan(0.3);

    const link = page.locator('img[alt="Resize Linked"]').locator('xpath=ancestor::a');
    expect(await link.count(), 'Resized linked image should stay wrapped in <a>').toBeGreaterThan(0);
    expect(await link.first().getAttribute('href')).toContain('typo3.org');
  });

  test('image that was never resized renders at its intrinsic width', async ({ page }) => {
    // No author width, so the figure is bounded only by the computed max-width
    // (the 400px intrinsic width from #667) and must gain no width declaration.
    const figure = await page.evaluate(() => {
      const img = document.querySelector<HTMLImageElement>('img[alt="Resize Never Applied"]');
      const element = img?.closest('figure');
      if (element === undefined || element === null) {
        throw new Error('Unresized figure not found');
      }

      return {
        width: element.getBoundingClientRect().width,
        style: element.getAttribute('style') ?? '',
      };
    });

    expect(figure.width).toBeGreaterThan(390);
    expect(figure.width).toBeLessThan(410);
    expect(figure.style).toContain('max-width');
    expect(figure.style).not.toMatch(/(^|;)\s*width\s*:/);
  });

  test('unsafe declarations from the stored style never reach the output', async ({ page }) => {
    const style = await page.evaluate(() => {
      const img = document.querySelector<HTMLImageElement>('img[alt="Resize Unsafe Style"]');
      const figure = img?.closest('figure');

      return figure?.getAttribute('style') ?? '';
    });

    expect(style).not.toContain('expression');
    expect(style).not.toContain('evil.example');
    expect(style).not.toContain('url(');
  });

  test('page contains no leaked Fluid expressions', async ({ page }) => {
    // A malformed inline f:if renders verbatim into the HTML rather than
    // failing, so the rendered document is the only place it shows up.
    const html = await page.content();

    expect(html).not.toContain('f:if(');
  });
});
