import { test, expect } from '@playwright/test';

/**
 * E2E tests for linked image rendering.
 *
 * Tests the fix for issue #565: duplicate links when images are wrapped in <a> tags.
 * These tests verify that the frontend renders linked images correctly without
 * creating duplicate <a> elements.
 *
 * The issue occurs when:
 * 1. User wraps an image in a link in CKEditor
 * 2. Content is saved with <a><img></a> structure
 * 3. On rendering, duplicate <a> tags could appear if not properly handled
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/565
 */

test.describe('Linked Image Rendering (#565)', () => {
  test('page renders without errors', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.status()).toBe(200);

    // Should not contain TYPO3 error messages
    const content = await page.content();
    expect(content).not.toContain('Oops, an error occurred');
    expect(content).not.toContain('TypoScript configuration error');
  });

  test('linked image has exactly one <a> wrapper', async ({ page }) => {
    await page.goto('/');

    // Find the test-linked-image class we added to the test content
    const linkedImages = page.locator('a.test-linked-image');
    const count = await linkedImages.count();

    // Skip if no linked image test content exists
    test.skip(count === 0, 'No test-linked-image content found');

    // Should have exactly one link with this class
    expect(count).toBe(1);

    // The link should contain an img
    const img = linkedImages.first().locator('img');
    await expect(img).toBeVisible();

    // Verify href is correct
    const href = await linkedImages.first().getAttribute('href');
    expect(href).toBe('https://example.com');

    // Verify target is preserved
    const target = await linkedImages.first().getAttribute('target');
    expect(target).toBe('_blank');
  });

  test('linked image does not have duplicate <a> tags', async ({ page }) => {
    await page.goto('/');

    // Get the full HTML to check for duplicates
    const pageContent = await page.content();

    // Look for the linked image section
    const hasLinkedImageTest = pageContent.includes('test-linked-image');
    test.skip(!hasLinkedImageTest, 'No linked image test content found');

    // Find the content element containing our linked image test
    // The structure should be: <a class="test-linked-image"><img></a>
    // NOT: <a class="test-linked-image"><a><img></a></a> (duplicate)

    const linkedImageLink = page.locator('a.test-linked-image');
    await expect(linkedImageLink).toBeVisible();

    // Check that the image is a DIRECT descendant of the link, not wrapped in another <a>
    const innerHtml = await linkedImageLink.innerHTML();

    // Count <a> tags inside the link - should be 0 (no nested links)
    const nestedLinkMatches = innerHtml.match(/<a(?:\s|>)/gi);
    const nestedLinkCount = nestedLinkMatches ? nestedLinkMatches.length : 0;

    expect(nestedLinkCount).toBe(0);
  });

  test('simple linked image renders with single link', async ({ page }) => {
    await page.goto('/');

    // Find simple link test content
    const simpleLinks = page.locator('a.test-simple-link');
    const count = await simpleLinks.count();

    test.skip(count === 0, 'No test-simple-link content found');

    // Should have exactly one
    expect(count).toBe(1);

    // Should contain image
    const img = simpleLinks.first().locator('img');
    await expect(img).toBeVisible();

    // Verify href
    const href = await simpleLinks.first().getAttribute('href');
    expect(href).toBe('https://typo3.org');

    // No nested links
    const innerHtml = await simpleLinks.first().innerHTML();
    const nestedLinks = innerHtml.match(/<a(?:\s|>)/gi);
    expect(nestedLinks).toBeNull();
  });

  test('linked image to netresearch.de renders correctly', async ({ page }) => {
    await page.goto('/');

    // Find the test-figure-linked class (now on the <a> element, not figure)
    const linkedImages = page.locator('a.test-figure-linked');
    const count = await linkedImages.count();

    test.skip(count === 0, 'No test-figure-linked content found');

    // Should have exactly one link with this class
    expect(count).toBe(1);

    // Verify href points to netresearch.de
    const href = await linkedImages.first().getAttribute('href');
    expect(href).toBe('https://netresearch.de');

    // Should contain an image
    const img = linkedImages.first().locator('img');
    await expect(img).toBeVisible();

    // No nested links
    const innerHtml = await linkedImages.first().innerHTML();
    const nestedLinks = innerHtml.match(/<a(?:\s|>)/gi);
    expect(nestedLinks).toBeNull();
  });

  test('linked image preserves all link attributes', async ({ page }) => {
    await page.goto('/');

    const linkedImage = page.locator('a.test-linked-image');
    const count = await linkedImage.count();

    test.skip(count === 0, 'No test-linked-image content found');

    // Check href
    const href = await linkedImage.getAttribute('href');
    expect(href).toBe('https://example.com');

    // Check target (should be preserved)
    const target = await linkedImage.getAttribute('target');
    expect(target).toBe('_blank');

    // Check title if present
    const title = await linkedImage.getAttribute('title');
    expect(title).toBe('Example Link');
  });
});

test.describe('Linked Image vs Popup Image Distinction', () => {
  test('linked images do NOT have data-popup attribute', async ({ page }) => {
    await page.goto('/');

    // Linked images should NOT have data-popup (that's for click-to-enlarge)
    const linkedImage = page.locator('a.test-linked-image');
    const count = await linkedImage.count();

    test.skip(count === 0, 'No test-linked-image content found');

    // Should NOT have data-popup
    const popup = await linkedImage.getAttribute('data-popup');
    expect(popup).toBeNull();
  });

  test('popup images have data-popup attribute', async ({ page }) => {
    await page.goto('/');

    // Find popup links (from click-to-enlarge content)
    const popupLinks = page.locator('a[data-popup="true"]');
    const popupCount = await popupLinks.count();

    test.skip(popupCount === 0, 'No popup images found');

    // Popup links should have data-popup="true"
    await expect(popupLinks.first()).toHaveAttribute('data-popup', 'true');
  });

  test('linked images and popup images can coexist on same page', async ({ page }) => {
    await page.goto('/');

    // Find linked images (our test content)
    const linkedImages = page.locator('a.test-linked-image');
    const linkedCount = await linkedImages.count();

    // Find popup images
    const popupImages = page.locator('a[data-popup="true"] img');
    const popupCount = await popupImages.count();

    // Skip if neither exists
    test.skip(linkedCount === 0 && popupCount === 0, 'No linked or popup images found');

    // Log what we found for debugging
    console.log(`Found ${linkedCount} linked images and ${popupCount} popup images`);

    // Both types should be renderable on the same page without conflicts
    // This is a sanity check that our fix doesn't break popup images
    if (linkedCount > 0) {
      await expect(linkedImages.first()).toBeVisible();
    }
    if (popupCount > 0) {
      await expect(popupImages.first()).toBeVisible();
    }
  });
});

test.describe('Image Rendering Service Integration', () => {
  test('linked images have processed image paths', async ({ page }) => {
    await page.goto('/');

    const linkedImage = page.locator('a.test-linked-image img');
    const count = await linkedImage.count();

    test.skip(count === 0, 'No test-linked-image image found');

    // Get the src attribute
    const src = await linkedImage.getAttribute('src');
    expect(src).toBeTruthy();

    // Should be a valid processed or original image path
    // (either fileadmin/ or _processed_/)
    expect(src).toMatch(/(fileadmin|_processed_)/);
  });

  test('linked images preserve alt text', async ({ page }) => {
    await page.goto('/');

    const linkedImage = page.locator('a.test-linked-image img');
    const count = await linkedImage.count();

    test.skip(count === 0, 'No test-linked-image image found');

    const alt = await linkedImage.getAttribute('alt');
    expect(alt).toBeTruthy();
    expect(alt).toBe('Linked Image');
  });

  test('test-figure-linked images have correct structure', async ({ page }) => {
    await page.goto('/');

    // test-figure-linked class is on the <a> element
    const linkedImages = page.locator('a.test-figure-linked');
    const count = await linkedImages.count();

    test.skip(count === 0, 'No test-figure-linked content found');

    // The link should contain an image directly
    // NOT: a > a > img (duplicate links)
    const img = linkedImages.first().locator('img');
    await expect(img).toBeVisible();

    // Verify image has correct attributes
    const alt = await img.getAttribute('alt');
    expect(alt).toBe('Captioned Linked Image');

    // Verify no nested links
    const innerHtml = await linkedImages.first().innerHTML();
    const nestedLinks = innerHtml.match(/<a(?:\s|>)/gi);
    expect(nestedLinks).toBeNull();
  });
});
