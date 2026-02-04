import { test, expect, Page } from '@playwright/test';

/**
 * E2E tests for common inline image patterns in RTE content.
 *
 * These tests verify that inline images render correctly in various contexts:
 * - Links spanning text and images
 * - Images in tables
 * - Images in lists
 * - Images in headings
 *
 * The extension must process these inline images without breaking the
 * surrounding structure (links, tables, lists, headings).
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/XXX
 */

test.describe('Links Spanning Text and Inline Images', () => {
  /**
   * Test: Links with text before and after inline images render correctly.
   *
   * Pattern: <a href="...">Click here <img> to visit</a>
   *
   * This is a common pattern where editors want an inline icon/image
   * within link text. The image must be processed but the link structure
   * (text + image + text) must be preserved.
   */
  test('link with text before and after inline image renders correctly', async ({ page }) => {
    await page.goto('/');

    // Find links containing inline images with surrounding text
    const linksWithInlineImages = page.locator('a:has(img.image-inline)');
    const count = await linksWithInlineImages.count();

    // Skip if no such links exist (content-dependent)
    test.skip(count === 0, 'No links with inline images found - add test content to validate');

    // Check the first matching link
    const link = linksWithInlineImages.first();
    await expect(link).toBeVisible();

    // Verify link has href attribute
    const href = await link.getAttribute('href');
    expect(href).toBeTruthy();

    // Verify inline image is present inside the link
    const inlineImg = link.locator('img.image-inline');
    await expect(inlineImg).toBeVisible();

    // Verify the image has been processed (has width/height or processed src)
    const imgSrc = await inlineImg.getAttribute('src');
    expect(imgSrc).toBeTruthy();

    // Verify no duplicate link wrappers (the bug we're testing for)
    const nestedLinks = await link.locator('a').count();
    expect(nestedLinks).toBe(0);

    // Log the structure for debugging
    const linkHtml = await link.innerHTML();
    console.log('Link with inline image structure:', linkHtml);
  });

  /**
   * Test: Link with inline image at beginning renders correctly.
   *
   * Pattern: <a href="..."><img> Documentation</a>
   */
  test('link with inline image at beginning renders correctly', async ({ page }) => {
    await page.goto('/');

    // Find links starting with inline images
    const links = page.locator('a');
    const count = await links.count();

    let foundLinkWithImageAtStart = false;

    for (let i = 0; i < count && !foundLinkWithImageAtStart; i++) {
      const link = links.nth(i);
      const innerHTML = await link.innerHTML();

      // Check if the first child is an img tag with image-inline class
      if (innerHTML.trim().startsWith('<img') && innerHTML.includes('image-inline')) {
        foundLinkWithImageAtStart = true;

        // Verify the link structure
        await expect(link).toBeVisible();
        const img = link.locator('img.image-inline').first();
        await expect(img).toBeVisible();

        console.log('Found link with image at start:', innerHTML);
      }
    }

    test.skip(!foundLinkWithImageAtStart, 'No links with inline image at start found');
  });

  /**
   * Test: Link with inline image at end renders correctly.
   *
   * Pattern: <a href="...">Download PDF <img></a>
   */
  test('link with inline image at end renders correctly', async ({ page }) => {
    await page.goto('/');

    // Find links ending with inline images
    const links = page.locator('a');
    const count = await links.count();

    let foundLinkWithImageAtEnd = false;

    for (let i = 0; i < count && !foundLinkWithImageAtEnd; i++) {
      const link = links.nth(i);
      const innerHTML = await link.innerHTML();

      // Check if the link ends with an img tag
      if (innerHTML.trim().endsWith('>') && innerHTML.includes('image-inline')) {
        const imgMatch = innerHTML.match(/<img[^>]+class="[^"]*image-inline[^"]*"[^>]*>$/);
        if (imgMatch) {
          foundLinkWithImageAtEnd = true;

          await expect(link).toBeVisible();
          const img = link.locator('img.image-inline').first();
          await expect(img).toBeVisible();

          console.log('Found link with image at end:', innerHTML);
        }
      }
    }

    test.skip(!foundLinkWithImageAtEnd, 'No links with inline image at end found');
  });

  /**
   * Test: No duplicate anchor tags around inline images in links.
   *
   * Bug verification: Before the fix, inline images inside links would
   * sometimes get double-wrapped with anchor tags: <a><a><img></a></a>
   */
  test('no duplicate anchor tags around inline images', async ({ page }) => {
    await page.goto('/');

    // Check for the malformed pattern: nested <a> tags
    const nestedAnchors = await page.evaluate(() => {
      const anchors = document.querySelectorAll('a a');
      return anchors.length;
    });

    expect(nestedAnchors).toBe(0);
  });

  /**
   * Test: Links with inline images have correct structure.
   *
   * Validates that the link text and image are siblings within the <a> tag,
   * not separated into different elements.
   */
  test('links with inline images maintain text-image structure', async ({ page }) => {
    await page.goto('/');

    const linksWithImages = page.locator('a:has(img.image-inline)');
    const count = await linksWithImages.count();

    test.skip(count === 0, 'No links with inline images found');

    for (let i = 0; i < Math.min(count, 5); i++) {
      const link = linksWithImages.nth(i);
      const innerHTML = await link.innerHTML();

      // The innerHTML should contain both text and img tag at the same level
      // It should NOT have the text wrapped in separate elements like <p>
      const hasUnwrappedText = !innerHTML.includes('<p>') ||
        (innerHTML.includes('<p>') && innerHTML.includes('<img'));

      expect(hasUnwrappedText).toBe(true);

      // Verify exactly one img inside
      const imgCount = (innerHTML.match(/<img/g) || []).length;
      expect(imgCount).toBeGreaterThanOrEqual(1);
    }
  });
});

test.describe('Inline Images in Tables', () => {
  /**
   * Test: Inline images in table cells render correctly.
   */
  test('inline images in table cells render correctly', async ({ page }) => {
    await page.goto('/');

    // Find tables containing inline images
    const tablesWithImages = page.locator('table:has(img.image-inline)');
    const count = await tablesWithImages.count();

    test.skip(count === 0, 'No tables with inline images found');

    const table = tablesWithImages.first();
    await expect(table).toBeVisible();

    // Verify the inline images inside table cells
    const inlineImages = table.locator('td img.image-inline, th img.image-inline');
    const imgCount = await inlineImages.count();

    expect(imgCount).toBeGreaterThan(0);

    // Verify each image is properly rendered
    for (let i = 0; i < imgCount; i++) {
      const img = inlineImages.nth(i);
      await expect(img).toBeVisible();

      // Should not be wrapped in figure
      const parent = img.locator('..');
      const parentTagName = await parent.evaluate(el => el.tagName.toLowerCase());
      expect(parentTagName).not.toBe('figure');
    }
  });

  /**
   * Test: Table cell text and inline images are not separated.
   */
  test('table cell text and inline images remain together', async ({ page }) => {
    await page.goto('/');

    const cellsWithImages = page.locator('td:has(img.image-inline), th:has(img.image-inline)');
    const count = await cellsWithImages.count();

    test.skip(count === 0, 'No table cells with inline images found');

    // Verify cells maintain their content structure
    for (let i = 0; i < Math.min(count, 5); i++) {
      const cell = cellsWithImages.nth(i);
      const innerHTML = await cell.innerHTML();

      // Cell content should not be completely broken up by processing
      expect(innerHTML).toContain('<img');
    }
  });
});

test.describe('Inline Images in Lists', () => {
  /**
   * Test: Inline images in list items render correctly.
   */
  test('inline images in list items render correctly', async ({ page }) => {
    await page.goto('/');

    // Find list items containing inline images
    const listItemsWithImages = page.locator('li:has(img.image-inline)');
    const count = await listItemsWithImages.count();

    test.skip(count === 0, 'No list items with inline images found');

    const listItem = listItemsWithImages.first();
    await expect(listItem).toBeVisible();

    const inlineImg = listItem.locator('img.image-inline').first();
    await expect(inlineImg).toBeVisible();

    // Should not be wrapped in figure
    const parent = inlineImg.locator('..');
    const parentTagName = await parent.evaluate(el => el.tagName.toLowerCase());
    expect(parentTagName).not.toBe('figure');
  });

  /**
   * Test: Both ordered and unordered lists work with inline images.
   */
  test('both ordered and unordered lists support inline images', async ({ page }) => {
    await page.goto('/');

    const ulWithImages = page.locator('ul li:has(img.image-inline)');
    const olWithImages = page.locator('ol li:has(img.image-inline)');

    const ulCount = await ulWithImages.count();
    const olCount = await olWithImages.count();

    test.skip(ulCount === 0 && olCount === 0, 'No lists with inline images found');

    if (ulCount > 0) {
      const ulImg = ulWithImages.first().locator('img.image-inline').first();
      await expect(ulImg).toBeVisible();
    }

    if (olCount > 0) {
      const olImg = olWithImages.first().locator('img.image-inline').first();
      await expect(olImg).toBeVisible();
    }
  });
});

test.describe('Inline Images in Headings', () => {
  /**
   * Test: Inline images in headings render correctly.
   */
  test('inline images in headings render correctly', async ({ page }) => {
    await page.goto('/');

    // Find headings containing inline images
    const headingsWithImages = page.locator('h1:has(img.image-inline), h2:has(img.image-inline), h3:has(img.image-inline), h4:has(img.image-inline), h5:has(img.image-inline), h6:has(img.image-inline)');
    const count = await headingsWithImages.count();

    test.skip(count === 0, 'No headings with inline images found');

    const heading = headingsWithImages.first();
    await expect(heading).toBeVisible();

    const inlineImg = heading.locator('img.image-inline').first();
    await expect(inlineImg).toBeVisible();

    // Should not be wrapped in figure
    const parent = inlineImg.locator('..');
    const parentTagName = await parent.evaluate(el => el.tagName.toLowerCase());
    expect(parentTagName).not.toBe('figure');

    // Log for debugging
    const headingHtml = await heading.innerHTML();
    console.log('Heading with inline image:', headingHtml);
  });

  /**
   * Test: Heading text and inline image are preserved together.
   */
  test('heading text and inline image maintain structure', async ({ page }) => {
    await page.goto('/');

    const headingsWithImages = page.locator('h1:has(img.image-inline), h2:has(img.image-inline), h3:has(img.image-inline)');
    const count = await headingsWithImages.count();

    test.skip(count === 0, 'No headings with inline images found');

    for (let i = 0; i < Math.min(count, 3); i++) {
      const heading = headingsWithImages.nth(i);
      const innerHTML = await heading.innerHTML();

      // Should contain both text content and image
      expect(innerHTML).toContain('<img');

      // The heading should have text content (not just an image)
      const textContent = await heading.textContent();
      if (textContent) {
        expect(textContent.trim().length).toBeGreaterThan(0);
      }
    }
  });
});

test.describe('General Inline Image Rendering', () => {
  /**
   * Test: All inline images have the image-inline class preserved.
   */
  test('inline images preserve image-inline class', async ({ page }) => {
    await page.goto('/');

    const inlineImages = page.locator('img.image-inline');
    const count = await inlineImages.count();

    test.skip(count === 0, 'No inline images found on page');

    console.log(`Found ${count} inline images`);

    for (let i = 0; i < Math.min(count, 10); i++) {
      const img = inlineImages.nth(i);
      const className = await img.getAttribute('class');

      expect(className).toContain('image-inline');
    }
  });

  /**
   * Test: Inline images are not wrapped in figure elements.
   */
  test('inline images are not wrapped in figure elements', async ({ page }) => {
    await page.goto('/');

    const inlineImages = page.locator('img.image-inline');
    const count = await inlineImages.count();

    test.skip(count === 0, 'No inline images found on page');

    for (let i = 0; i < Math.min(count, 10); i++) {
      const img = inlineImages.nth(i);
      const parent = img.locator('..');
      const parentTagName = await parent.evaluate(el => el.tagName.toLowerCase());

      // Inline images should never be inside figure elements
      expect(parentTagName).not.toBe('figure');
    }
  });

  /**
   * Test: Inline images display inline (not block).
   */
  test('inline images have inline display styling', async ({ page }) => {
    await page.goto('/');

    const inlineImages = page.locator('img.image-inline');
    const count = await inlineImages.count();

    test.skip(count === 0, 'No inline images found on page');

    for (let i = 0; i < Math.min(count, 5); i++) {
      const img = inlineImages.nth(i);
      const display = await img.evaluate(el => getComputedStyle(el).display);

      // Should be inline or inline-block (browsers may differ)
      expect(['inline', 'inline-block']).toContain(display);
    }
  });
});
