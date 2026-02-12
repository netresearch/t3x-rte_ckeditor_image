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
    await page.goto(`${BASE_URL}/typo3/`, { timeout: 30000 });

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
        '.modulemenu, .typo3-module-menu, [data-modulemenu], .scaffold'
    );
    expect(await backendIndicators.count(), 'Backend login failed — check TYPO3_BACKEND_PASSWORD').toBeGreaterThan(0);
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
    await moduleFrame.locator('.ck-editor__editable, .ck-content').first().waitFor({ timeout: 20000 });
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
    expect(await images.count(), 'No images found in CKEditor').toBeGreaterThan(imageIndex);
    await images.nth(imageIndex).dblclick();
    await page.locator('.t3js-modal').first().waitFor({ state: 'visible', timeout: 10000 });
    await expect(page.locator('.modal-title').first()).toBeVisible();
}

/**
 * Close the image dialog by clicking the confirm/save button.
 */
export async function confirmImageDialog(page: Page): Promise<void> {
    const confirmButton = page.locator(
        '.modal-footer button.btn-primary, .modal-footer button.btn-default:has-text("OK"), .modal-footer button:has-text("OK")'
    ).first();

    await expect(confirmButton, 'Confirm button not found in image dialog').toBeVisible();
    await confirmButton.evaluate((el: HTMLElement) => el.click());
    // Wait for modal to close — use .t3js-modal (the outer container) to avoid
    // strict mode violation (.modal-dialog is a child and also matches)
    await page.locator('.t3js-modal').first().waitFor({ state: 'hidden', timeout: 10000 });
}

/**
 * Cancel/close the image dialog.
 */
export async function cancelImageDialog(page: Page): Promise<void> {
    const cancelButton = page.locator(
        '.modal-footer button.btn-default, .modal-footer button[name="cancel"], button:has-text("Cancel"), .modal-header .close, button.close'
    ).first();
    await expect(cancelButton, 'Cancel button not found in image dialog').toBeVisible();
    await cancelButton.click();
    await page.locator('.t3js-modal').first().waitFor({ state: 'hidden', timeout: 10000 });
}

/**
 * Get the HTML content of the CKEditor.
 */
export async function getEditorHtml(page: Page): Promise<string> {
    const frame = getModuleFrame(page);
    return await frame.locator('.ck-editor__editable').innerHTML();
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
