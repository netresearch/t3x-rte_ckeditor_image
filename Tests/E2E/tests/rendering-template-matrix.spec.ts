import { test, expect } from '@playwright/test';
import { templateTestImage } from './helpers/selectors';

/**
 * Template rendering matrix — one test per Fluid template.
 *
 * ImageRenderingService.selectTemplate() has 6 branches:
 *   1. Standalone     — bare <img> (no link, no caption)
 *   2. WithCaption    — <figure><img><figcaption>
 *   3. Link           — <a href="..."><img>
 *   4. LinkWithCaption — <figure><a><img></a><figcaption>
 *   5. Popup          — <a data-popup="true"><img>
 *   6. PopupWithCaption — <figure><a data-popup="true"><img></a><figcaption>
 *
 * Test content: CEs 14-19 each contain one image with alt="Template {Name}"
 * to enable precise element selection.
 *
 * These tests catch template selection bugs and Fluid rendering issues.
 */
test.describe('Rendering Template Matrix', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
    });

    test('Standalone template: bare <img> without wrapper', async ({ page }) => {
        const img = page.locator(templateTestImage('Standalone'));
        expect(await img.count(), 'Expected Standalone template image (CE 14)').toBeGreaterThan(0);

        await expect(img.first()).toBeVisible();

        // Should have standard attributes
        await expect(img.first()).toHaveAttribute('alt', 'Template Standalone');
        const src = await img.first().getAttribute('src');
        expect(src).toMatch(/(fileadmin|_processed_)/);

        // Should NOT be in a <figure>
        const parent = img.first().locator('..');
        const parentTag = await parent.evaluate(el => el.tagName.toLowerCase());
        expect(parentTag).not.toBe('figure');

        // Should NOT be in an <a>
        expect(parentTag).not.toBe('a');
    });

    test('WithCaption template: <figure><img><figcaption>', async ({ page }) => {
        const img = page.locator(templateTestImage('WithCaption'));
        expect(await img.count(), 'Expected WithCaption template image (CE 15)').toBeGreaterThan(0);

        await expect(img.first()).toBeVisible();

        // Should be inside a <figure>
        const figure = img.first().locator('xpath=ancestor::figure');
        expect(await figure.count(), 'Image should be in a <figure>').toBeGreaterThan(0);

        // Figure should have class "image"
        const figureClass = await figure.first().getAttribute('class');
        expect(figureClass).toMatch(/image/);

        // Should have <figcaption>
        const caption = figure.first().locator('figcaption');
        expect(await caption.count(), 'Figure should have <figcaption>').toBeGreaterThan(0);
        const captionText = await caption.textContent();
        expect(captionText?.trim()).toBe('Template caption text');

        // Should NOT be in an <a> (no link)
        const link = img.first().locator('xpath=ancestor::a');
        expect(await link.count()).toBe(0);
    });

    test('Link template: <a href="..."><img>', async ({ page }) => {
        const img = page.locator(templateTestImage('Link'));
        expect(await img.count(), 'Expected Link template image (CE 16)').toBeGreaterThan(0);

        await expect(img.first()).toBeVisible();

        // Should be inside an <a>
        const link = img.first().locator('xpath=ancestor::a');
        expect(await link.count(), 'Image should be wrapped in <a>').toBeGreaterThan(0);

        // Link should have href
        const href = await link.first().getAttribute('href');
        expect(href).toBeTruthy();
        expect(href).toContain('example.com');

        // Should NOT have data-popup (that's the Popup template)
        const dataPopup = await link.first().getAttribute('data-popup');
        expect(dataPopup).toBeNull();

        // Should NOT be in a <figure> (no caption)
        const figure = img.first().locator('xpath=ancestor::figure');
        expect(await figure.count()).toBe(0);
    });

    test('LinkWithCaption template: <figure><a><img></a><figcaption>', async ({ page }) => {
        const img = page.locator(templateTestImage('LinkWithCaption'));
        expect(await img.count(), 'Expected LinkWithCaption template image (CE 17)').toBeGreaterThan(0);

        await expect(img.first()).toBeVisible();

        // Should be inside a <figure>
        const figure = img.first().locator('xpath=ancestor::figure');
        expect(await figure.count(), 'Image should be in a <figure>').toBeGreaterThan(0);

        // Should be inside an <a> within the figure
        const link = img.first().locator('xpath=ancestor::a');
        expect(await link.count(), 'Image should be wrapped in <a>').toBeGreaterThan(0);

        const href = await link.first().getAttribute('href');
        expect(href).toBeTruthy();
        expect(href).toContain('example.com');

        // Should NOT have data-popup
        const dataPopup = await link.first().getAttribute('data-popup');
        expect(dataPopup).toBeNull();

        // Should have <figcaption>
        const caption = figure.first().locator('figcaption');
        expect(await caption.count(), 'Figure should have <figcaption>').toBeGreaterThan(0);
        const captionText = await caption.textContent();
        expect(captionText?.trim()).toBe('Linked caption text');
    });

    test('Popup template: <a data-popup="true"><img>', async ({ page }) => {
        const img = page.locator(templateTestImage('Popup'));
        expect(await img.count(), 'Expected Popup template image (CE 18)').toBeGreaterThan(0);

        await expect(img.first()).toBeVisible();

        // Should be inside an <a> with data-popup="true"
        const link = img.first().locator('xpath=ancestor::a');
        expect(await link.count(), 'Image should be wrapped in popup <a>').toBeGreaterThan(0);

        await expect(link.first()).toHaveAttribute('data-popup', 'true');

        // Should have lightbox rel attribute
        const rel = await link.first().getAttribute('rel');
        expect(rel).toMatch(/lightbox/);

        // Should NOT be in a <figure> (no caption)
        const figure = img.first().locator('xpath=ancestor::figure');
        expect(await figure.count()).toBe(0);
    });

    test('PopupWithCaption template: <figure><a data-popup><img></a><figcaption>', async ({ page }) => {
        const img = page.locator(templateTestImage('PopupWithCaption'));
        expect(await img.count(), 'Expected PopupWithCaption template image (CE 19)').toBeGreaterThan(0);

        await expect(img.first()).toBeVisible();

        // Should be inside a <figure>
        const figure = img.first().locator('xpath=ancestor::figure');
        expect(await figure.count(), 'Image should be in a <figure>').toBeGreaterThan(0);

        // Should be inside an <a> with data-popup="true"
        const link = img.first().locator('xpath=ancestor::a');
        expect(await link.count(), 'Image should be wrapped in popup <a>').toBeGreaterThan(0);

        await expect(link.first()).toHaveAttribute('data-popup', 'true');

        const rel = await link.first().getAttribute('rel');
        expect(rel).toMatch(/lightbox/);

        // Should have <figcaption>
        const caption = figure.first().locator('figcaption');
        expect(await caption.count(), 'Figure should have <figcaption>').toBeGreaterThan(0);
        const captionText = await caption.textContent();
        expect(captionText?.trim()).toBe('Popup caption text');
    });
});
