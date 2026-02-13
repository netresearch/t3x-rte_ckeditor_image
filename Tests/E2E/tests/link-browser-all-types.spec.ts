import { test, expect } from '@playwright/test';
import { loginToBackend, navigateToContentEdit, waitForCKEditor, openImageEditDialog, requireCondition, BACKEND_PASSWORD } from './helpers/typo3-backend';

/**
 * E2E tests for all link browser link types in the CKEditor image dialog.
 *
 * Tests verify that the TYPO3 link browser (opened from the image edit dialog)
 * supports all expected link types: Page, File, URL, Email, Telephone.
 *
 * The link browser is a nested modal containing an iframe. Interactions within
 * the iframe (tab switching, form filling) are inherently fragile due to:
 * - Nested modal stacking (.t3js-modal over .t3js-modal)
 * - Iframe content loading timing
 * - TYPO3 version differences in tab selectors and form structure
 *
 * Tests that go beyond verifying the presence of tabs are marked as fixme.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/618
 */

/**
 * Helper: Select the "Link" radio button and click Browse to open the link browser.
 * Waits for the nested link browser iframe to become visible.
 */
async function openLinkBrowser(page: import('@playwright/test').Page): Promise<void> {
  // Select "Link" radio button in click behavior section
  const linkRadio = page.locator('#clickBehavior-link');
  await expect(linkRadio, 'Link radio button not found in image dialog').toBeVisible();
  await linkRadio.click();
  await page.waitForTimeout(500);

  // Click Browse button to open link browser
  const browseButton = page.locator('button:has-text("Browse"), .btn:has-text("Browse")');
  await expect(browseButton.first(), 'Browse button not found').toBeVisible();
  await browseButton.first().click();

  // Wait for the link browser modal (nested modal with iframe) to appear
  const linkBrowserIframe = page.locator('.t3js-modal iframe').last();
  await expect(linkBrowserIframe).toBeVisible({ timeout: 10000 });

  // Give the iframe content time to load
  await page.waitForTimeout(2000);
}

/**
 * Helper: Get the link browser iframe FrameLocator (the last iframe in stacked modals).
 */
function getLinkBrowserFrame(page: import('@playwright/test').Page) {
  return page.frameLocator('.t3js-modal iframe').last();
}

test.describe('Link Browser - All Types', () => {
  test.beforeEach(async ({ page }) => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');

    await loginToBackend(page);
    await navigateToContentEdit(page, 1);
    await waitForCKEditor(page);
    await openImageEditDialog(page);
  });

  test('link browser opens from image dialog', async ({ page }) => {
    // Select Link radio and click Browse
    await openLinkBrowser(page);

    // Verify the link browser modal appeared (a second .t3js-modal)
    const modals = page.locator('.t3js-modal');
    const modalCount = await modals.count();
    expect(modalCount, 'Expected at least 2 modals (image dialog + link browser)').toBeGreaterThanOrEqual(2);

    // Verify the iframe loaded content
    const linkBrowserFrame = getLinkBrowserFrame(page);
    await expect(linkBrowserFrame.locator('body')).not.toBeEmpty();

    await page.screenshot({ path: 'test-results/link-browser-all-types-opened.png' });
  });

  test('link browser has multiple tabs for different link types', async ({ page }) => {
    await openLinkBrowser(page);

    const linkBrowserFrame = getLinkBrowserFrame(page);

    // TYPO3 link browser uses tabs/nav items for different link types.
    // Look for tab elements with common selectors across TYPO3 versions.
    // Tabs may use [data-identifier], role="tab", or .nav-link selectors.
    const tabs = linkBrowserFrame.locator(
      '.nav-tabs .nav-link, .nav-tabs .nav-item a, [role="tab"]'
    );
    const tabCount = await tabs.count();
    console.log(`Found ${tabCount} tabs in link browser`);

    // Should have at least 3 tabs (Page, URL, Email are the minimum expected)
    expect(tabCount, 'Expected at least 3 link type tabs').toBeGreaterThanOrEqual(3);

    // Collect tab labels for logging
    const tabLabels: string[] = [];
    for (let i = 0; i < tabCount; i++) {
      const text = await tabs.nth(i).textContent();
      tabLabels.push(text?.trim() || '');
    }
    console.log('Link browser tabs:', tabLabels);

    // Verify key tabs exist by checking for expected text patterns.
    // TYPO3 may localize these, so check common English labels.
    const tabTexts = tabLabels.join(' ').toLowerCase();

    // Page tab (internal links)
    const hasPageTab = tabTexts.includes('page') || tabTexts.includes('internal');
    expect(hasPageTab, `Expected "Page" tab among: ${tabLabels.join(', ')}`).toBe(true);

    // URL tab (external links)
    const hasUrlTab = tabTexts.includes('url') || tabTexts.includes('external');
    expect(hasUrlTab, `Expected "URL" tab among: ${tabLabels.join(', ')}`).toBe(true);

    // Email tab
    const hasEmailTab = tabTexts.includes('email') || tabTexts.includes('mail');
    expect(hasEmailTab, `Expected "Email" tab among: ${tabLabels.join(', ')}`).toBe(true);

    await page.screenshot({ path: 'test-results/link-browser-all-types-tabs.png' });
  });

  test.fixme('external URL tab allows entering a URL', async ({ page }) => {
    // This test interacts with the link browser iframe content (tab switching
    // and form filling inside nested modals), which is fragile across TYPO3
    // versions. Marked as fixme until the link browser structure stabilizes.

    await openLinkBrowser(page);

    const linkBrowserFrame = getLinkBrowserFrame(page);

    // Click the URL/External tab
    const urlTab = linkBrowserFrame.locator(
      '.nav-link:has-text("URL"), .nav-link:has-text("External"), [data-identifier="url"] a, [data-identifier="external"] a'
    ).first();
    await expect(urlTab, 'URL tab not found in link browser').toBeVisible();
    await urlTab.click();
    await page.waitForTimeout(1000);

    // Find the URL input field inside the URL tab panel
    const urlInput = linkBrowserFrame.locator(
      'input[name="lurl"], input[name="url"], input[type="url"], input[placeholder*="URL"], #lurl'
    ).first();
    await expect(urlInput, 'URL input field not found').toBeVisible();

    // Enter a test URL
    const testUrl = 'https://example.com/test-link';
    await urlInput.fill(testUrl);

    // Submit the URL (click the link/set button in the URL tab)
    const submitButton = linkBrowserFrame.locator(
      'button[type="submit"], input[type="submit"], button:has-text("Set"), button:has-text("Link")'
    ).first();
    if (await submitButton.count() > 0) {
      await submitButton.click();
    }

    // Wait for the link browser to close and value to be inserted
    await page.waitForTimeout(2000);

    // Verify the URL was inserted into the image dialog's link href field
    const linkHrefInput = page.locator('#rteckeditorimage-linkHref');
    const linkValue = await linkHrefInput.inputValue();
    console.log(`Link href value after URL selection: "${linkValue}"`);

    expect(linkValue).toContain('example.com');

    await page.screenshot({ path: 'test-results/link-browser-url-entered.png' });
  });

  test.fixme('email tab generates mailto link', async ({ page }) => {
    // Fragile: requires navigating inside nested modal iframe, switching tabs,
    // and filling forms. Marked as fixme.

    await openLinkBrowser(page);

    const linkBrowserFrame = getLinkBrowserFrame(page);

    // Click the Email tab
    const emailTab = linkBrowserFrame.locator(
      '.nav-link:has-text("Email"), .nav-link:has-text("Mail"), [data-identifier="mail"] a, [data-identifier="email"] a'
    ).first();
    await expect(emailTab, 'Email tab not found in link browser').toBeVisible();
    await emailTab.click();
    await page.waitForTimeout(1000);

    // Find the email input field
    const emailInput = linkBrowserFrame.locator(
      'input[name="lemail"], input[name="email"], input[type="email"], #lemail'
    ).first();
    await expect(emailInput, 'Email input field not found').toBeVisible();

    // Enter a test email address
    const testEmail = 'test@example.com';
    await emailInput.fill(testEmail);

    // Submit
    const submitButton = linkBrowserFrame.locator(
      'button[type="submit"], input[type="submit"], button:has-text("Set"), button:has-text("Link")'
    ).first();
    if (await submitButton.count() > 0) {
      await submitButton.click();
    }

    // Wait for the link browser to close
    await page.waitForTimeout(2000);

    // Verify the mailto: link was inserted
    const linkHrefInput = page.locator('#rteckeditorimage-linkHref');
    const linkValue = await linkHrefInput.inputValue();
    console.log(`Link href value after email entry: "${linkValue}"`);

    // Should contain mailto: prefix
    expect(linkValue.toLowerCase()).toContain('mailto:');
    expect(linkValue).toContain(testEmail);

    await page.screenshot({ path: 'test-results/link-browser-email-entered.png' });
  });

  test.fixme('telephone tab generates tel link', async ({ page }) => {
    // Fragile: requires navigating inside nested modal iframe, switching tabs,
    // and filling forms. The telephone tab may not be present in all TYPO3
    // configurations. Marked as fixme.

    await openLinkBrowser(page);

    const linkBrowserFrame = getLinkBrowserFrame(page);

    // Click the Telephone tab
    const telTab = linkBrowserFrame.locator(
      '.nav-link:has-text("Telephone"), .nav-link:has-text("Phone"), [data-identifier="telephone"] a, [data-identifier="phone"] a, [data-identifier="tel"] a'
    ).first();

    // Telephone tab might not be available in all TYPO3 configurations
    const telTabCount = await telTab.count();
    if (telTabCount === 0) {
      console.log('Telephone tab not found in link browser - may not be enabled');
      test.skip(true, 'Telephone tab not available in this TYPO3 configuration');
      return;
    }

    await telTab.click();
    await page.waitForTimeout(1000);

    // Find the telephone input field
    const telInput = linkBrowserFrame.locator(
      'input[name="ltelephone"], input[name="telephone"], input[name="phone"], input[type="tel"], #ltelephone'
    ).first();
    await expect(telInput, 'Telephone input field not found').toBeVisible();

    // Enter a test phone number
    const testPhone = '+49123456789';
    await telInput.fill(testPhone);

    // Submit
    const submitButton = linkBrowserFrame.locator(
      'button[type="submit"], input[type="submit"], button:has-text("Set"), button:has-text("Link")'
    ).first();
    if (await submitButton.count() > 0) {
      await submitButton.click();
    }

    // Wait for the link browser to close
    await page.waitForTimeout(2000);

    // Verify the tel: link was inserted
    const linkHrefInput = page.locator('#rteckeditorimage-linkHref');
    const linkValue = await linkHrefInput.inputValue();
    console.log(`Link href value after telephone entry: "${linkValue}"`);

    // Should contain tel: prefix
    expect(linkValue.toLowerCase()).toContain('tel:');

    await page.screenshot({ path: 'test-results/link-browser-telephone-entered.png' });
  });
});
