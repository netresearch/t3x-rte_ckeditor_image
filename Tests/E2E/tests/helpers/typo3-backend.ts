import { Page, FrameLocator, expect } from '@playwright/test';

/**
 * Shared helpers for TYPO3 backend E2E tests.
 *
 * Extracted from duplicated code across 6 backend spec files to provide
 * a single source of truth for common backend interactions.
 */

export const BACKEND_USER = process.env.TYPO3_BACKEND_USER || 'admin';
export const BACKEND_PASSWORD = process.env.TYPO3_BACKEND_PASSWORD || '';
export const BASE_URL = process.env.BASE_URL || 'https://v13.rte-ckeditor-image.ddev.site';
export const TYPO3_VERSION = process.env.TYPO3_VERSION || '13';

/**
 * Navigate to a frontend page with retry logic for infrastructure readiness.
 *
 * In CI, the PHP-FPM container may not be fully ready when the first
 * frontend request arrives, causing Apache proxy errors (502/503).
 * This helper retries the navigation up to 3 times with a 2s delay.
 */
export async function gotoFrontendPage(page: Page, path: string = '/'): Promise<void> {
    const url = `${BASE_URL}${path}`;
    const maxAttempts = 3;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        let response;
        try {
            response = await page.goto(url, { timeout: 30000 });
        } catch (error) {
            // Navigation itself failed (connection refused, proxy timeout)
            if (attempt < maxAttempts) {
                console.log(`Frontend navigation failed (attempt ${attempt}/${maxAttempts}), retrying in 2s...`);
                await page.waitForTimeout(2000);
                continue;
            }
            throw error;
        }
        await page.waitForLoadState('networkidle');

        const status = response?.status() ?? 0;
        if (status >= 200 && status < 500) {
            return;
        }

        // 5xx — infrastructure not ready yet (502 proxy error, 503 service unavailable)
        if (attempt < maxAttempts) {
            console.log(`Frontend returned ${status} (attempt ${attempt}/${maxAttempts}), retrying in 2s...`);
            await page.waitForTimeout(2000);
        }
    }

    throw new Error(`Frontend at ${url} still returning errors after ${maxAttempts} attempts`);
}

/**
 * Assert a precondition — fails hard in every environment.
 *
 * Both CI and local dev have fixed test setups, so a failing
 * precondition always indicates a real problem that must be fixed.
 */
export function requireCondition(condition: boolean, message: string): void {
    expect(condition, message).toBe(true);
}

/**
 * Login to TYPO3 backend.
 * Handles both TYPO3 v12 and v13 login form selectors.
 */
export async function loginToBackend(page: Page): Promise<void> {
    await gotoFrontendPage(page, '/typo3/');

    const loginForm = page.locator(
        'form[name="loginform"], #typo3-login-form, input[name="username"], #t3-username'
    );
    const isLoginPage = await loginForm.count() > 0;

    if (!isLoginPage) {
        return; // Already logged in
    }

    const usernameInput = page.locator('input[name="username"], #t3-username').first();
    const passwordInput = page.locator('input[name="p_field"], input[name="password"], #t3-password').first();

    await usernameInput.fill(BACKEND_USER);
    await passwordInput.fill(BACKEND_PASSWORD);
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle', { timeout: 30000 });

    const backendIndicators = page.locator(
        '.modulemenu, .typo3-module-menu, [data-modulemenu], .scaffold, typo3-backend-sidebar-toggle'
    );
    await expect(backendIndicators.first(), 'TYPO3 backend did not render after login (no module menu, scaffold, or sidebar toggle found)').toBeVisible({ timeout: 30000 });
}

/**
 * Navigate directly to edit a content element with RTE.
 * Uses direct URL to avoid complex page tree navigation.
 */
export async function navigateToContentEdit(page: Page, contentId: number = 1): Promise<void> {
    const editUrl = `${BASE_URL}/typo3/record/edit?edit[tt_content][${contentId}]=edit&returnUrl=/typo3/`;
    await page.goto(editUrl, { timeout: 30000 });
    await page.waitForLoadState('networkidle');

    const moduleFrame = page.frameLocator('iframe').first();
    await moduleFrame.locator('.ck-editor__editable, .ck-content').first().waitFor({ timeout: 45000 });
}

/**
 * Get the module frame locator (TYPO3 backend content is in an iframe).
 */
export function getModuleFrame(page: Page): FrameLocator {
    return page.frameLocator('iframe').first();
}

/**
 * Wait for CKEditor to be ready (inside module iframe).
 */
export async function waitForCKEditor(page: Page): Promise<void> {
    const frame = getModuleFrame(page);
    await frame.locator('.ck-editor__editable').first().waitFor({ timeout: 15000 });
}

/**
 * Double-click image to open edit dialog (inside module iframe).
 */
export async function openImageEditDialog(page: Page, imageIndex: number = 0): Promise<void> {
    const frame = getModuleFrame(page);
    const images = frame.locator('.ck-editor__editable img');
    expect(await images.count(), `Expected at least ${imageIndex + 1} image(s) in CKEditor`).toBeGreaterThan(imageIndex);
    await images.nth(imageIndex).dblclick();
    await page.locator('.t3js-modal').first().waitFor({ state: 'visible', timeout: 20000 });
    await expect(page.locator('.modal-title, .modal-header-title, .t3js-modal-title').first()).toBeVisible();
}

/**
 * Close the image dialog by clicking the confirm/save button.
 *
 * Uses retry logic with multiple click strategies because the TYPO3
 * modal sometimes doesn't close on the first click (especially when
 * CKEditor is still processing the confirm callback).
 */
export async function confirmImageDialog(page: Page): Promise<void> {
    const confirmButton = page.locator(
        '.modal-footer button.btn-primary, .modal-footer button.btn-default:has-text("OK"), .modal-footer button:has-text("OK")'
    ).first();

    await expect(confirmButton, 'Confirm button not found in image dialog').toBeVisible();

    // Use .t3js-modal (the outer container) to avoid strict mode violation
    // (.modal-dialog is a child and also matches).
    const modal = page.locator('.t3js-modal').first();

    // Try up to 3 times with different click strategies.
    // Each attempt uses a different method because the button click
    // can fail silently when CKEditor JS handlers are still initializing.
    const strategies = [
        async () => { await confirmButton.evaluate((el: HTMLElement) => el.click()); },
        async () => { await confirmButton.click({ force: true }); },
        async () => { await confirmButton.focus(); await page.keyboard.press('Enter'); },
    ];

    for (let i = 0; i < strategies.length; i++) {
        await strategies[i]();
        try {
            await modal.waitFor({ state: 'hidden', timeout: 7000 });
            return;
        } catch {
            // Modal still visible — try next strategy
        }
    }

    throw new Error('confirmImageDialog: modal did not close after 3 attempts');
}

/**
 * Cancel/close the image dialog.
 */
export async function cancelImageDialog(page: Page): Promise<void> {
    const cancelButton = page.locator(
        '.modal-footer button.btn-default, .modal-footer button[name="cancel"], button:has-text("Cancel"), .modal-header .close, button.close, .t3js-modal-close, .modal-header-close'
    ).first();
    await expect(cancelButton, 'Cancel button not found in image dialog').toBeVisible();
    await cancelButton.click();
    await page.locator('.t3js-modal').first().waitFor({ state: 'hidden', timeout: 10000 });
}

/**
 * Get the HTML content of the CKEditor editing view DOM.
 *
 * NOTE: The editing view DOM may differ from CKEditor's data output.
 * For example, linked images have no <a> wrapper in the editing view
 * (they use indicator badges instead) but do have <a> in the data output.
 * Use getCKEditorData() when you need the data-layer HTML (e.g., for
 * verifying link structures).
 */
export async function getEditorHtml(page: Page): Promise<string> {
    const frame = getModuleFrame(page);
    return await frame.locator('.ck-editor__editable').innerHTML();
}

/**
 * Get the CKEditor data output (editor.getData()).
 *
 * Unlike getEditorHtml() which returns the editing view DOM, this returns
 * the serialized data output — the HTML that will be saved to the database.
 * This includes <a> wrappers around linked images, proper figcaption
 * structure, and other data-layer differences from the editing view.
 */
export async function getCKEditorData(page: Page): Promise<string> {
    const frame = getModuleFrame(page);
    return await frame.locator('.ck-editor__editable').evaluate((el) => {
        // CKEditor 5 stores the editor instance on the editable element
        const editor = (el as any).ckeditorInstance;
        if (editor && typeof editor.getData === 'function') {
            return editor.getData();
        }
        // Fallback: return DOM innerHTML if no CKEditor instance found
        return el.innerHTML;
    });
}

/**
 * Save the content element via the save button in the docheader.
 */
export async function saveContentElement(page: Page): Promise<void> {
    const frame = getModuleFrame(page);
    const saveButton = frame.locator(
        'button[name="_savedok"], button[value="1"][name="_savedok"], .t3js-editform-submitButton'
    ).first();

    await expect(saveButton, 'Save button not found in docheader').toBeVisible();
    await saveButton.click();
    await page.waitForLoadState('networkidle');
}

/**
 * Click on an image in CKEditor to select it (inside module iframe).
 */
export async function selectImageInEditor(page: Page): Promise<void> {
    const frame = getModuleFrame(page);
    const image = frame.locator('.ck-editor__editable img, .ck-editor__editable figure.image');
    expect(await image.count(), 'No images found in CKEditor').toBeGreaterThan(0);
    await image.first().click();
}
