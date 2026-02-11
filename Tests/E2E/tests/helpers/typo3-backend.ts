import { Page, FrameLocator, test } from '@playwright/test';

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
 * Login to TYPO3 backend.
 * Handles both TYPO3 v12 and v13 login form selectors.
 */
export async function loginToBackend(page: Page): Promise<boolean> {
    try {
        await page.goto(`${BASE_URL}/typo3/`, { timeout: 30000 });

        const loginForm = page.locator(
            'form[name="loginform"], #typo3-login-form, input[name="username"], #t3-username'
        );
        const isLoginPage = await loginForm.count() > 0;

        if (!isLoginPage) {
            return true; // Already logged in
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
        return await backendIndicators.count() > 0;
    } catch (error) {
        console.log('Backend login failed:', error);
        return false;
    }
}

/**
 * Navigate directly to edit a content element with RTE.
 * Uses direct URL to avoid complex page tree navigation.
 */
export async function navigateToContentEdit(page: Page, contentId: number = 1): Promise<boolean> {
    try {
        const editUrl = `${BASE_URL}/typo3/record/edit?edit[tt_content][${contentId}]=edit&returnUrl=/typo3/`;
        await page.goto(editUrl, { timeout: 30000 });
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);

        const moduleFrame = page.frameLocator('iframe').first();
        await moduleFrame.locator('.ck-editor__editable, .ck-content').first().waitFor({ timeout: 20000 });
        return true;
    } catch (error) {
        console.log('Failed to navigate to content edit:', error);
        return false;
    }
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
    await page.waitForTimeout(1000);
}

/**
 * Double-click image to open edit dialog (inside module iframe).
 */
export async function openImageEditDialog(page: Page, imageIndex: number = 0): Promise<boolean> {
    const frame = getModuleFrame(page);
    const images = frame.locator('.ck-editor__editable img');
    if (await images.count() > imageIndex) {
        await images.nth(imageIndex).dblclick();
        await page.waitForSelector('.modal-dialog, .t3js-modal', { timeout: 10000 });
        await page.waitForTimeout(500);
        return true;
    }
    return false;
}

/**
 * Close the image dialog by clicking the confirm/save button.
 */
export async function confirmImageDialog(page: Page): Promise<void> {
    const confirmButton = page.locator(
        '.modal-footer button.btn-primary, .modal-footer button.btn-default:has-text("OK"), .modal-footer button:has-text("OK")'
    ).first();

    if (await confirmButton.count() > 0) {
        await confirmButton.evaluate((el: HTMLElement) => el.click());
    }

    await page.waitForTimeout(1000);
}

/**
 * Cancel/close the image dialog.
 */
export async function cancelImageDialog(page: Page): Promise<void> {
    const cancelButton = page.locator(
        '.modal-footer button.btn-default, .modal-footer button[name="cancel"], button:has-text("Cancel"), .modal-header .close, button.close'
    ).first();
    if (await cancelButton.count() > 0) {
        await cancelButton.click();
        await page.waitForTimeout(500);
    }
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

    if (await saveButton.count() > 0) {
        await saveButton.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
    }
}

/**
 * Click on an image in CKEditor to select it (inside module iframe).
 */
export async function selectImageInEditor(page: Page): Promise<boolean> {
    const frame = getModuleFrame(page);
    const image = frame.locator('.ck-editor__editable img, .ck-editor__editable figure.image');
    if (await image.count() > 0) {
        await image.first().click();
        return true;
    }
    return false;
}

/**
 * Skip the current test if backend password is not configured.
 * Use in test.beforeEach() for backend test suites.
 */
export function skipIfNoBackendPassword(): void {
    test.skip(!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD not configured - skipping backend tests');
}
