import { test, expect } from '@playwright/test';
import {
  BACKEND_PASSWORD,
  loginToBackend,
  navigateToContentEdit,
  waitForCKEditor,
  getModuleFrame,
  requireCondition,
} from './helpers/typo3-backend';

/**
 * Regression test for https://github.com/netresearch/t3x-rte_ckeditor_image/issues/821
 *
 * Bug: selectImage() in Resources/Public/JavaScript/Plugins/typo3image.js built
 * a 4-element bparams array of empty strings. Slot [3] is the FileBrowser's
 * allowedExtensions filter — the controller (SelectImageController::mainAction)
 * only honours it when the JS sends a non-empty value, otherwise it overwrites
 * with $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']. The user's
 * documented `editor.externalPlugins.typo3image.allowedExtensions` setting was
 * therefore silently ignored.
 *
 * Root cause: bparams[3] hardcoded to '' (introduced in commit 1cfe7a7).
 * Fix: read editor.config.get('typo3image').allowedExtensions and place it in
 * bparams[3] when set, falling back to '' so the controller's default kicks in.
 *
 * Strategy
 * --------
 * Rather than ship a new YAML preset, the spec injects the configuration into
 * the live CKEditor instance with `editor.config.set('typo3image', ...)`
 * before clicking the image-insert button. This keeps the regression test
 * self-contained inside `Tests/E2E/` and avoids changing any production YAML
 * file that real installations consume. The TYPO3 RteConfig→CKEditor bridge
 * is unchanged: `externalPlugins.typo3image.route` already surfaces as
 * `editor.config.get('typo3image').routeUrl` today, so the same path is what
 * we exercise — just primed with a known value.
 *
 * The most surgical assertion is the iframe URL of the file-selection modal:
 * `Modal.advanced({ type: 'iframe', content: contentUrl })` makes `contentUrl`
 * the iframe's `src`, and `contentUrl = routeUrl + '&bparams=' + bparams.join('|')`.
 * If bparams[3] carries our configured value, the bug is fixed.
 */

/**
 * Read-only spec — only opens the file-browser modal, never saves. Safe to
 * share CE 33 with image-insertion.spec.ts (also read-only on this CE).
 */
const CE_ID = 33;

const TEST_ALLOWED_EXTENSIONS = 'jpg,jpeg,png,gif,svg,webp';

/**
 * Extract the bparams query parameter from a TYPO3 file-browser URL and
 * return it as the 4-element `|`-separated slot array.
 *
 * The URL is built by typo3image.js as:
 *   {routeUrl}&bparams={a}|{b}|{c}|{d}
 * where {d} (slot [3]) carries the allowedExtensions filter. The TYPO3
 * ElementBrowser convention uses a raw `|`-joined value (no URL-encoding),
 * so `URL.searchParams.get('bparams').split('|')` recovers the slots.
 */
function getBparamsSlots(url: string): string[] {
  // `URL` requires an absolute URL, so prefix relative URLs with a placeholder.
  const absolute = /^https?:\/\//i.test(url) ? url : `https://placeholder.invalid${url}`;
  const parsed = new URL(absolute);
  const bparams = parsed.searchParams.get('bparams');
  expect(bparams, `bparams query parameter missing in URL: ${url}`).not.toBeNull();
  return (bparams as string).split('|');
}

test.describe('Regression #821: allowedExtensions reaches FileBrowser', () => {
  test.beforeEach(() => {
    requireCondition(!!BACKEND_PASSWORD, 'TYPO3_BACKEND_PASSWORD must be configured');
  });

  test('iframe URL carries configured allowedExtensions in bparams[3]', async ({ page }) => {
    await loginToBackend(page);
    await navigateToContentEdit(page, CE_ID);
    await waitForCKEditor(page);

    const frame = getModuleFrame(page);

    // Inject the allowedExtensions setting into the CKEditor instance just
    // like a real YAML preset would (`editor.externalPlugins.typo3image.allowedExtensions`
    // is forwarded by TYPO3 RteConfig as `editor.config.typo3image.allowedExtensions`).
    // The CKEditor `Config` class exposes `set()` / `get()` for plugin-namespaced
    // values; setting it after editor init is supported because `typo3image` is
    // a custom (non-builtin) namespace and is read on demand inside selectImage().
    await frame.locator('.ck-editor__editable').first().evaluate((el, ext) => {
      const editor = (el as any).ckeditorInstance;
      if (!editor || typeof editor.config?.get !== 'function' || typeof editor.config?.set !== 'function') {
        throw new Error('CKEditor instance with config.get/set not found on .ck-editor__editable');
      }
      const current = editor.config.get('typo3image') || {};
      editor.config.set('typo3image', { ...current, allowedExtensions: ext });

      // Sanity-check the round-trip — fail loudly here so a CKEditor API
      // change cannot silently neuter the assertion below.
      const verify = editor.config.get('typo3image');
      if (!verify || verify.allowedExtensions !== ext) {
        throw new Error(
          `editor.config.set did not retain allowedExtensions; got: ${JSON.stringify(verify)}`
        );
      }
    }, TEST_ALLOWED_EXTENSIONS);

    // Click the CKEditor image insert button to open the file-selection modal.
    // The modal is created via Modal.advanced({ type: 'iframe', content: contentUrl }),
    // so the contentUrl becomes the iframe's src attribute.
    const imageButton = frame.locator(
      '.ck-toolbar button[data-cke-tooltip-text*="image" i], ' +
      '.ck-toolbar button[data-cke-tooltip-text*="Image" i]'
    ).first();

    requireCondition(
      await imageButton.count() > 0,
      'Image insert button not found in CKEditor toolbar'
    );

    await imageButton.click();

    // Wait for the modal and its iframe.
    const modal = page.locator('.t3js-modal').first();
    await expect(modal).toBeVisible({ timeout: 10000 });

    const iframeLocator = page.locator('.t3js-modal iframe').first();
    await expect(iframeLocator).toBeVisible({ timeout: 10000 });

    // Read the iframe src directly. We don't need to wait for the iframe
    // content to finish loading — the URL is set synchronously when the
    // modal opens.
    const iframeSrc = await iframeLocator.getAttribute('src');
    expect(iframeSrc, 'iframe src must be set by Modal.advanced({ content: contentUrl })').toBeTruthy();
    expect(iframeSrc, 'iframe src must point to the rteckeditorimage_wizard_select_image route')
      .toContain('selectimage');

    const slots = getBparamsSlots(iframeSrc as string);

    // Sanity check: bparams should always have at least 4 segments.
    expect(
      slots.length,
      `bparams should split into >=4 segments, got ${slots.length}: ${slots.join(' | ')}`
    ).toBeGreaterThanOrEqual(4);

    // *** The regression assertion for #821 ***
    // Slot [3] must carry the configured allowedExtensions value.
    // Before the fix, bparams[3] was hardcoded to '' so this was the empty
    // string and the controller fell back to GFX.imagefile_ext (and the
    // documented allowedExtensions setting was silently ignored).
    expect(
      slots[3],
      `bparams[3] must carry the configured allowedExtensions value (#821). ` +
      `Got "${slots[3]}" — expected "${TEST_ALLOWED_EXTENSIONS}". ` +
      `Full bparams: "${slots.join('|')}". Full src: "${iframeSrc}".`
    ).toBe(TEST_ALLOWED_EXTENSIONS);
  });
});
