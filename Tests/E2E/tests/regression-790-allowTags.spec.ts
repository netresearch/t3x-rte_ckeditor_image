import { test, expect } from '@playwright/test';
import { gotoFrontendPage } from './helpers/typo3-backend';

/**
 * Regression test for #790 — "Unwanted <p> Tag around body".
 *
 * Bug: `lib.parseFunc_RTE.allowTags := addToList(a,figure,figcaption)` in
 * `Configuration/TypoScript/ImageRendering/setup.typoscript` was added back
 * when fluid_styled_content's pre-v13.2 `Helper/ParseFunc.typoscript`
 * provided `allowTags = {$styles.content.allowTags}` (a comprehensive list)
 * and `denyTags = *`. In that world, `addToList(figure,figcaption)` was a
 * harmless extension of an existing whitelist.
 *
 * Since TYPO3 v13.2 (Important-103485), `lib.parseFunc_RTE` moved into
 * `ext:frontend/ext_localconf.php` WITHOUT `allowTags`/`denyTags`. The
 * default behavior is "everything allowed". Our `addToList` then creates
 * a restrictive whitelist of just `a, figure, figcaption`, and per
 * `ContentObjectRenderer::parseFuncInternal()` (lines 3149-3162) every
 * other tag — including `<p>` — gets `htmlspecialchars`'d. Then
 * `nonTypoTagStdWrap.encapsLines` wraps the now-unwrapped "line" in `<p>`,
 * producing rendered HTML like `<p>&lt;p&gt;Lorem ipsum.&lt;/p&gt;</p>`.
 *
 * Reporter (#790, TYPO3 14.3.0, Extension 13.8.3) describes the symptom
 * as "<p> tags wrapped around every existing <p>" and notes it happens
 * "regardless of whether I have an image in the text" — hence this spec
 * uses a CE seeded with image-free bodytext.
 *
 * Variant: only meaningful in `core-only` because `fsc` and `bootstrap`
 * load packages that configure `lib.parseFunc_RTE` themselves and mask
 * the bug. That masking is exactly what made the original regression
 * invisible to our existing E2E coverage.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/790
 */
test.describe('Regression: #790 plain RTE bodytext rendering', () => {
  // Run on every variant. The original bug was masked under fsc and
  // bootstrap variants (which load packages that configure
  // `lib.parseFunc_RTE` themselves), so a `test.skip` predicate was
  // tempting — but the assertion ("rendered HTML preserves <p> structure
  // and contains no `&lt;p&gt;` artifacts") is a true invariant of
  // healthy RTE rendering across all variants. Running the spec
  // everywhere catches future regressions that might re-introduce the
  // encoding under setups other than core-only too.

  test('plain <p> bodytext renders without entity-encoded artifacts', async ({ page }) => {
    await gotoFrontendPage(page);

    const html = await page.content();

    // Symptom check 1: <p> tags must not appear entity-encoded in output.
    // Under the bug, parseFunc htmlspecialchars'd them to "&lt;p&gt;" and
    // encapsLines re-wrapped, producing literal text.
    expect(
      html,
      '`&lt;p&gt;` literal text in rendered output indicates ' +
        'parseFunc_RTE is rejecting <p> as an unallowed tag — see #790.',
    ).not.toContain('&lt;p&gt;');

    // Symptom check 2: original <p> structure preserved as actual elements.
    // The CE (CType=text, sorting=11008) seeds bodytext with two distinct
    // paragraphs that should render unchanged.
    expect(html).toContain('<p>Lorem ipsum dolor sit amet.</p>');
    expect(html).toContain('<p>Another paragraph here for the regression check.</p>');
  });
});
