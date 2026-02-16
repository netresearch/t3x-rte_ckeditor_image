import { test, expect } from '@playwright/test';
import {
    loginToBackend,
    navigateToContentEdit,
    getModuleFrame,
    waitForCKEditor,
    getEditorHtml,
    gotoFrontendPage,
    requireCondition,
} from './helpers/typo3-backend';

/**
 * E2E tests for inline image issues #637, #638, #639.
 *
 * #637: Inline + link → Standalone.html instead of Link.html
 *       (consequence of #638 — corrupted structure causes wrong template)
 * #638: Duplicate <a> tags for internal t3:// links
 *       (double-link recovery upcast creates typo3image instead of typo3imageInline)
 * #639: Zoom/popup indicator not shown on inline images
 *       (inline editing downcast was missing indicator creation logic)
 *
 * Test data (runTests.sh):
 *   CE 39: Double-link corrupted inline image (<a><a><img class="image-inline"></a></a>)
 *   CE 40: Inline image with zoom (data-htmlarea-zoom="true")
 *   CE 41: Inline image with link (<a href="..."><img class="image-inline"></a>)
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/636
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/637
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/638
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/639
 */

// =============================================================================
// #638: Double-link recovery upcast creates correct element type
// =============================================================================

test.describe('Double-link recovery for inline images (#638)', () => {
    test.beforeEach(async ({ page }) => {
        await loginToBackend(page);
    });

    test('double-link inline image is recovered as inline widget, not block', async ({ page }) => {
        // CE 39: Contains <a><a><img class="image-inline"></a></a>
        await navigateToContentEdit(page, 39);
        await waitForCKEditor(page);

        const frame = getModuleFrame(page);

        // The corrupted double-link should be recovered as inline widget
        const inlineWidgets = frame.locator('.ck-editor__editable .ck-widget_inline-image');
        const blockFigures = frame.locator('.ck-editor__editable figure.ck-widget');

        const inlineCount = await inlineWidgets.count();
        const blockCount = await blockFigures.count();

        console.log(`Inline widgets: ${inlineCount}, Block figures: ${blockCount}`);

        // The inline image should be recovered as inline, not converted to block
        expect(
            inlineCount,
            'Double-link inline image should be recovered as inline widget (typo3imageInline), not block (typo3image)'
        ).toBeGreaterThan(0);
    });

    test('recovered inline image preserves image-inline class', async ({ page }) => {
        await navigateToContentEdit(page, 39);
        await waitForCKEditor(page);

        const editorHtml = await getEditorHtml(page);

        // The image-inline class must survive the double-link recovery
        expect(editorHtml).toContain('image-inline');
    });

    test('recovered inline image is inside a paragraph, not standalone', async ({ page }) => {
        await navigateToContentEdit(page, 39);
        await waitForCKEditor(page);

        const frame = getModuleFrame(page);

        // Inline images live inside <p> elements, block images are standalone <figure>s
        const inlineInParagraph = frame.locator('.ck-editor__editable p .ck-widget_inline-image');
        const count = await inlineInParagraph.count();

        expect(
            count,
            'Recovered inline image should be inside a paragraph element'
        ).toBeGreaterThan(0);
    });
});

// =============================================================================
// #639: Zoom/link indicators on inline images
// =============================================================================

test.describe('Inline image indicators (#639)', () => {
    test.beforeEach(async ({ page }) => {
        await loginToBackend(page);
    });

    test('inline image with zoom shows zoom indicator in editor', async ({ page }) => {
        // CE 40: Contains inline image with data-htmlarea-zoom="true"
        await navigateToContentEdit(page, 40);
        await waitForCKEditor(page);

        const frame = getModuleFrame(page);

        const zoomIndicator = frame.locator(
            '.ck-editor__editable .ck-widget_inline-image .ck-image-indicator--zoom'
        );
        const count = await zoomIndicator.count();

        console.log(`Zoom indicators in inline widgets: ${count}`);

        expect(
            count,
            'Inline image with zoom should show zoom indicator (magnifying glass)'
        ).toBeGreaterThan(0);
    });

    test('inline image with link shows link indicator in editor', async ({ page }) => {
        // CE 41: Contains <a href="..."><img class="image-inline"></a>
        await navigateToContentEdit(page, 41);
        await waitForCKEditor(page);

        const frame = getModuleFrame(page);

        const linkIndicator = frame.locator(
            '.ck-editor__editable .ck-widget_inline-image .ck-image-indicator--link'
        );
        const count = await linkIndicator.count();

        console.log(`Link indicators in inline widgets: ${count}`);

        expect(
            count,
            'Inline image with link should show link indicator (chain icon)'
        ).toBeGreaterThan(0);
    });

    test('inline image without zoom or link shows no indicators', async ({ page }) => {
        // CE 7: First inline image has no zoom and no link
        await navigateToContentEdit(page, 7);
        await waitForCKEditor(page);

        const frame = getModuleFrame(page);

        const inlineWidgets = frame.locator('.ck-editor__editable .ck-widget_inline-image');
        requireCondition(await inlineWidgets.count() > 0, 'No inline images in CE 7');

        // First inline image in CE 7 is plain (no zoom, no link)
        const firstWidget = inlineWidgets.first();
        const indicators = firstWidget.locator('.ck-image-indicators');
        const indicatorCount = await indicators.count();

        expect(
            indicatorCount,
            'Plain inline image should not have any indicators'
        ).toBe(0);
    });

    test('indicator container has correct CSS structure', async ({ page }) => {
        // CE 40: Inline image with zoom
        await navigateToContentEdit(page, 40);
        await waitForCKEditor(page);

        const frame = getModuleFrame(page);

        const indicatorContainer = frame.locator(
            '.ck-editor__editable .ck-widget_inline-image .ck-image-indicators'
        );
        const count = await indicatorContainer.count();

        requireCondition(count > 0, 'No indicator container found in inline widget');

        // Verify the indicator container has correct CSS class
        const containerClass = await indicatorContainer.first().getAttribute('class');
        expect(containerClass).toContain('ck-image-indicators');

        // Verify each indicator badge has correct structure
        const indicators = indicatorContainer.first().locator('.ck-image-indicator');
        const indicatorCount = await indicators.count();
        expect(indicatorCount).toBeGreaterThan(0);
    });

    test('block images still show indicators (regression)', async ({ page }) => {
        // CE 32: Block image with data-htmlarea-zoom="true"
        await navigateToContentEdit(page, 32);
        await waitForCKEditor(page);

        const frame = getModuleFrame(page);

        const blockZoomIndicator = frame.locator(
            '.ck-editor__editable figure.ck-widget .ck-image-indicator--zoom'
        );
        const count = await blockZoomIndicator.count();

        console.log(`Block zoom indicators: ${count}`);

        expect(
            count,
            'Block image with zoom should still show indicator (regression check)'
        ).toBeGreaterThan(0);
    });
});

// =============================================================================
// #637: Inline + link renders correct template on frontend
// =============================================================================

test.describe('Frontend rendering of inline linked images (#637)', () => {
    test('inline image with link renders with anchor wrapper', async ({ page }) => {
        await gotoFrontendPage(page);

        // Find linked inline images in frontend output
        const linkedInlineImages = page.locator('a > img.image-inline');
        const count = await linkedInlineImages.count();

        expect(
            count,
            'Expected linked inline images in frontend output (Link.html template produces <a> wrapper)'
        ).toBeGreaterThan(0);

        // Verify the link wrapper has a real href
        const firstLinked = linkedInlineImages.first();
        const parentLink = firstLinked.locator('..');
        const href = await parentLink.getAttribute('href');

        expect(
            href,
            'Linked inline image should have href attribute (confirms Link.html was used, not Standalone.html)'
        ).toBeTruthy();
    });

    test('inline image with zoom has popup link on frontend', async ({ page }) => {
        await gotoFrontendPage(page);

        // data-htmlarea-zoom is consumed by PHP rendering and NOT preserved on
        // the rendered <img>. The Popup.html template wraps the image in an <a>
        // pointing to the image file. Target by known alt text from test data.
        const zoomInlineImage = page.locator('img.image-inline[alt="Zoom Inline"]');
        const count = await zoomInlineImage.count();

        requireCondition(
            count > 0,
            'No zoom inline image (alt="Zoom Inline") found on frontend'
        );

        // Verify the zoom image is wrapped in a popup link by the Popup.html template
        const parentLink = zoomInlineImage.locator('..');

        const parentTagName = (await parentLink.evaluate((el) => el.tagName)).toLowerCase();
        expect(
            parentTagName,
            'Zoom-enabled inline image should be wrapped in an anchor tag'
        ).toBe('a');

        const href = await parentLink.getAttribute('href');
        expect(
            href,
            'Popup link wrapping inline zoom image should have href attribute'
        ).toBeTruthy();

        // Popup.html uses onclick with openPic() or href pointing to image file
        const onclick = (await parentLink.getAttribute('onclick')) || '';
        const isPopupLink = onclick.includes('openPic') || (href && href.includes('/fileadmin/'));

        expect(
            isPopupLink,
            'Popup link should have openPic onclick or href to image file'
        ).toBe(true);
    });
});
