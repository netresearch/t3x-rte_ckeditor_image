# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- **`rel="noreferrer"` missing on figure-wrapped linked images** ([#799](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/799), [#802](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/802)) — figure-wrapped linked images go through the Fluid `Link.html` partial, which builds the `<a>` tag directly rather than via TYPO3 typolink, so `LinkFactory::addSecurityRelValues()` never ran for them. External `target="_blank"` links lacked the `rel="noreferrer"` browser security policy expects. Mirrored the typolink semantics in a new `SecurityRelComputer` service: `noreferrer` is now appended whenever the target opens a new browsing context AND the URL is absolute `http(s)` or protocol-relative (`//example.com/...`). Existing `rel` tokens from the source `<a>` are preserved (lowercased, deduplicated, normalized). Bug existed on both v13 and v14; surfaced on v14 because v14 dropped the default `config.extTarget = _blank`. New unit + E2E coverage.
- **`<p>` tags entity-encoded in plain RTE bodytext on vanilla installs** ([#790](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/790)) — removed restrictive `lib.parseFunc_RTE.allowTags`/`denyTags` modifications from `setup.typoscript`. They were artifacts from pre-TYPO3-v13.2 (when `fluid_styled_content` provided defaults that have since moved); on current installs they made `parseFunc` `htmlspecialchars`-encode every standard tag including `<p>`. Added an E2E regression spec. Thanks [@timofo](https://github.com/timofo) for pinpointing the exact line.

### Changed

- **Pin TYPO3 v14 requirement to v14.3 LTS** ([released 2026-04-21](https://typo3.org/article/typo3-v143-released)) — Composer-based installs (the default and recommended path) are now pinned to `^13.4.21 || ^14.3` (was `^13.4.21 || ^14.0`). The `ext_emconf` constraint widens its lower bound to `13.4.21` and accepts `14.99.99` as upper (was `13.4.0-14.4.99`). Note that `ext_emconf` syntax does not support disjoint ranges, so TER/non-Composer installs on the unmaintained pre-LTS releases v14.0/v14.1/v14.2 are still technically permitted by `ext_emconf`; this is not a supported configuration and such installs should upgrade to v14.3 LTS. CI matrix and DDEV `install-v14` aligned to v14.3.

## [13.8.3] - 2026-04-10

### Fixed

- **Comprehensive localization review** — fix 5 corrupted translations (Hindi cancel had MFA deactivation text, Swahili ultra was "Number One"), fix 4 inconsistent translations (Russian/Turkish/Polish quality labels), fix Chinese caption and quality indicator translations ([#782](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/782))
- **24 new translation keys** — add translations for previously hardcoded JS strings (form labels, toolbar buttons, quality indicators, accessibility labels) across all 31 languages
- **17 translator context notes** — add XLIFF notes to ambiguous keys to prevent future translation errors
- **Debug leftover** — replace modal title `'test'` with translated "Select image"

## [13.8.2] - 2026-04-10

_No GitHub release was created for this tag. See [git tag v13.8.2](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v13.8.2) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v13.8.2)._

## [13.8.1] - 2026-04-10

### Fixed

- **Image reference validation strips leading slash** ([#778](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/778)) — the validator and file rename/move listener now preserve the leading slash in image `src` attributes
- **Images without file-uid get width=0 height=0** ([#746](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/746)) — plain `<img>` tags without `data-htmlarea-file-uid` now pass through unmodified
- **TYPO3 v14.2 compatibility** — call parent constructor in `RteImagePreviewRenderer`

### Added

- Danish backend localization

## [12.0.11] - 2026-03-26

## Bug Fixes

- **Resolve all PHPStan errors** (#754): PHPStan baseline now only contains deprecated items, all actual errors resolved.
- **Replace removed `getFileStorageRecords()`** (#751): Replaced deprecated method with `checkActionPermission()` for proper file storage access checks. Fixes #749.

## CI/CD

- **Pin GitHub Actions to full-length commit SHAs** (#753): Improved supply chain security by pinning all GitHub Actions to immutable commit hashes.
- **Add unit test support to CI pipeline**: Unit tests are now included in the CI build matrix.

## Contributors

- @hansup — bug report (#749)
- @magicsunday — PHPStan fixes, API migration, unit tests (#751, #754)
- @CybotTM — GitHub Actions security hardening (#753)

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v12.0.11) for the original notes._

## [13.8.0] - 2026-03-14

### Added

- **Figure/figcaption in backend preview** ([#726](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/726))
- Improved documentation based on community feedback

### Fixed

- Only check direct fields for file type in preview registrar ([#727](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/727))
- Remove redundant SLSA provenance job from release workflow

### Changed

- Migrate CI to centralized `typo3-ci-workflows`

## [12.0.10] - 2026-03-13

## Bug Fixes

- **PCRE limits restored after image processing** (#731, #733): Modified PCRE backtrack/recursion limits are now reverted to their original values after image processing completes, preventing side effects on subsequent code.

## Contributors

- @joharthun — bug report (#731)
- @vimar — fix (#733)

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v12.0.10) for the original notes._

## [13.7.1] - 2026-03-07

### Added

- **Content Blocks support** — new `RteImagePreview` ViewHelper for rendering RTE images in Content Block backend previews ([#648](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/648), [#696](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/696))

### Fixed

- **Page module preview for textpic/textmedia** — automatic preview renderer registration now skips CTypes with FILE-type columns (e.g. `image`, `assets`), preserving `StandardContentPreviewRenderer`'s file field thumbnails. Detection resolves palette references. ([#720](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/720), [#721](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/721))
- **TYPO3 v14 link handling** — added fallback `tags.a` typolink configuration for setups without `fluid_styled_content`. In TYPO3 v14, `fluid_styled_content` no longer provides parseFunc config ([Breaking-107438](https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/14.0/Breaking-107438-RemovedParseFunc_RTESetup.html)). Default `tags.a` config from `fluid_styled_content` is preserved via safe merging. ([#718](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/718), [#719](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/719))
- **Images in CKEditor tables** — images placed inside CKEditor 5 tables now get proper processing: max-width constraints, zoom/lightbox support, and `t3://` URL resolution ([#698](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/698), [#699](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/699))
- Correct broken README badge URLs (CodeQL workflow renamed, OpenSSF Scorecard redirect) ([#722](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/722))
- Pin Content Blocks version constraints in DDEV install scripts ([#697](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/697))

### Changed

- Migrate to centralized dev-dependencies package ([#717](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/717))
- Migrate CI to centralized `typo3-ci-workflows` ([#701](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/701), [#716](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/716))
- Add SPDX copyright and license headers to all PHP files ([#700](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/700))

## [13.7.0] - 2026-03-06

_No GitHub release was created for this tag. See [git tag v13.7.0](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v13.7.0) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v13.7.0)._

## [12.0.9] - 2026-02-26

## Bug Fixes

- **Image Browser 503 error** (#703): Fixed SelectImageController not calling parent constructor, leaving ElementBrowserRegistry uninitialized. Now uses proper constructor dependency injection.

## CI

- Fixed TER publishing: removed declare(strict_types=1) from ext_emconf.php (TER cannot parse it)
- Synced TER publish workflow from main branch

## Development

- Added DDEV setup for local TYPO3 v12 development and testing

## Closes

- #703

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v12.0.9) for the original notes._

## [12.0.8] - 2026-02-26

_No GitHub release was created for this tag. See [git tag v12.0.8](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v12.0.8) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v12.0.8)._

## [12.0.7] - 2026-02-26

_No GitHub release was created for this tag. See [git tag v12.0.7](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v12.0.7) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v12.0.7)._

## [12.0.6] - 2026-02-26

_No GitHub release was created for this tag. See [git tag v12.0.6](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v12.0.6) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v12.0.6)._

## [13.6.1] - 2026-02-25

### Fixed

- **Figure wrapper XPath** — `hasFigureWrapper()` now checks only direct-child images, preventing false positives with nested images in table cells ([#692](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/692), [#693](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/693))
- **Double link icon in CKEditor** — linked images no longer show duplicate link indicators in the editing view ([#688](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/688))
- **Figcaption width in CKEditor** — figcaption styling constrained to image width inside the editor, matching frontend rendering ([#688](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/688))

### Changed

- Auto-create announcement discussion in GitHub Discussions on each release ([#682](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/682))
- Release workflow verifies GPG tag signatures ([#691](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/691))
- README badge links and icons improved for accuracy ([#674](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/674))

## [13.6.0] - 2026-02-22

### Added

- **True inline images** — new `typo3imageInline` model element for images that flow with text. Cursor can be positioned before/after inline images. Toggle between block and inline via toolbar button. ([#580](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/580), [#583](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/583))
- **Linked images** — TYPO3 link browser integration with target options, URL parameters, and duplicate link prevention ([#575](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/575))
- **Image reference validation** — CLI command (`bin/typo3 rte_ckeditor_image:validate`) and upgrade wizard to detect and fix broken image references and nested link wrappers ([#635](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/635), [#670](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/670))
- **Dynamic preview renderer** with broken image warnings in page module ([#647](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/647), [#636](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/636))
- **Link browser restrictions** read from RTE preset configuration ([#603](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/603))
- Internationalization — click behavior strings for 31 languages ([#591](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/591)), link dialog translations ([#575](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/575))

### Fixed

- **Figcaption width constraint** — `<figure>` constrained to image width via `max-width`, preventing caption overflow ([#671](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/671))
- **Double `<a>` tags** in inline linked images — fix nested link wrappers from `tags.a` + `externalBlocks.a` overlap ([#669](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/669))
- **UTF-8 characters** in figcaptions — fix `DOMDocument::loadHTML()` ISO-8859-1 default corrupting umlauts ([#664](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/664))
- **Inline linked images** with unresolved `t3://` URLs — `externalBlocks.a` was dead code, replaced with `tags.a` handler ([#661](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/661))
- **Alignment class** without caption no longer triggers unnecessary figure wrapper ([#599](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/599))
- **t3:// link resolution** in linked images ([#598](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/598))
- **Image src update** when FAL files are moved or renamed ([#630](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/630))

### Changed

- **jQuery removal** — complete removal of jQuery dependency, modernized to native DOM, fetch(), Promise, template literals ([#641](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/641))
- **OpenSSF Scorecard** improved from 6.8 to ~9.0 ([#628](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/628))
- E2E test suite with priority 1 coverage for all critical user paths, sharded across 11 parallel CI runners ([#621](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/621))
- TYPO3 v14 E2E testing support, now blocking ([#611](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/611), [#626](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/626))

## [12.0.5] - 2026-02-14

## Bug Fixes

- **Image regeneration** (#277): Restored `getProcessedFile()` in `RteImagesDbHook` to regenerate processed images when the source file is missing (e.g., after TYPO3 upgrades or cache clearing)
- **Image processing without fluid_styled_content** (#287, #291): Added `lib.parseFunc` fallback in TypoScript for installations that don't use `fluid_styled_content`, fixing broken image processing and linking

## CI Improvements

- Added `Build ✓` summary job for branch protection compatibility
- Added auto-approve workflow for solo maintainer PR merging
- Applied Rector rule: `str_starts_with()` instead of `strpos()`

## Closes

- #277 — Regression for #199 (image regeneration)
- #287 — Linking an image not working
- #291 — Images not processed, unwanted attributes

Cherry-picked from [PR #372](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/372) and [PR #375](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/375).
Merged via [PR #631](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/631).

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v12.0.5) for the original notes._

## [11.0.17] - 2026-02-14

## Bug Fixes

- **Image scaling in Page module** (#301): Added `backend.css` with `max-width: 100%` to prevent images from overflowing in the TYPO3 v11 Page module

## CI Improvements

- Disabled Composer `block-insecure` for EOL TYPO3 v11 packages
- Added `Build ✓` summary job for branch protection compatibility
- Added auto-approve workflow for solo maintainer PR merging

## Closes

- #301 — Images not scaled in Page module

Cherry-picked from [PR #376](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/376).
Merged via [PR #632](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/632).

> **Note:** TYPO3 v11 reached end-of-life in October 2024. This is a final maintenance release. Users are encouraged to upgrade to v13+ for continued support.

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.17) for the original notes._

## [13.5.0] - 2026-01-29

### Added

- **Configurable popup link class** - The CSS class for popup/lightbox links is now configurable via TypoScript `lib.contentElement.settings.media.popup.linkClass` ([#562](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/562), [#569](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/569))

### Fixed

- **Captioned image template selection** - Images with `data-caption` inside `<figure>` elements now correctly use the `WithCaption` template. The `renderImageAttributes()` handler skips processing captioned images to preserve `data-htmlarea-file-uid` for the `renderFigure()` handler ([#566](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566), [#572](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/572))
- **Duplicate link prevention** - Removed `a` from `encapsTagList` to prevent double-wrapped links when images are inside anchor tags ([#565](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/565), [#570](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/570))
- **PageTSconfig duplicate loading** - Removed global `page.tsconfig` include to prevent duplicate loading when using TYPO3 Site Sets ([#563](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/563), [#568](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/568))

### Changed

- Documentation now mentions Site Set dependency requirement for installation

## [13.4.2] - 2026-01-28

### Fixed

- **Template selection for linked figure images** - `renderFigure()` now correctly extracts link attributes from `<a>` wrapper inside `<figure>` elements, selecting the correct template (`LinkWithCaption` instead of `WithCaption`) ([#555](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555), [#556](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/556))

### Changed

- PHPUnit 11/12 compatibility - added polyfill for `AllowMockObjectsWithoutExpectations` attribute ([#558](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/558))
- Updated documentation for accuracy (TYPO3 13.4.21+ requirement, TypoScript inclusion notice)

## [13.4.1] - 2026-01-26

### Fixed

- **Caption persistence** - Caption values from the image properties dialog are now correctly persisted ([#549](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/549))
- **Linked image processing** - Images wrapped in links (`<a>`) are now properly processed via `tags.a` handler ([#551](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/551))
- **Nested figure prevention** - Strip `data-caption` in img handler to prevent nested figures ([#550](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/550))

## [13.4.0] - 2026-01-25

### Added

- **TypoScript template path configuration** - Override Fluid templates via TypoScript `templateRootPaths`, `partialRootPaths`, `layoutRootPaths` ([#434](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/434), [#542](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/542))
- **Figure tag handler** for caption extraction from CKEditor 5 `<figure>`/`<figcaption>` structure ([#538](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/538), [#540](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/540))
- **SVG data URI sanitization** to prevent XSS attacks via malicious SVG content ([#535](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/535), [#536](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/536))
- **URL allowlist validation** - Switched from blocklist to allowlist approach for external URL validation ([#541](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/541))

### Changed

- **BREAKING:** TypoScript is no longer auto-injected for frontend rendering ([#532](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/532))
  - Removed `ExtensionManagementUtility::addTypoScript()` from `ext_localconf.php`
  - Removed `AddTypoScriptAfterTemplatesListener` event listener
  - **Migration:** Include TypoScript manually via static template "CKEditor Image Support" or `@import 'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript'`
  - This gives integrators full control over TypoScript load order, enabling proper override of settings like lightbox configuration
- Updated Code of Conduct to Contributor Covenant v3.0 ([#543](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/543))
- Documentation uses inclusive language (allowlist, primary toggle)

## [13.3.2] - 2026-01-09

> **Note:** Versions 13.3.0 and 13.3.1 were blocked by GitHub's immutable releases feature during release troubleshooting. This is the first published release of the 13.3.x series.

### Added

- `#[AsAllowedCallable]` attribute on `renderImageAttributes()` and `renderImages()` methods for TYPO3 v14 compatibility ([#518](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/518))
- Functional tests for TypoScript callable integration

### Changed

- Minimum TYPO3 version raised to 13.4.21 (backport of `AsAllowedCallable` attribute)

## [13.2.0] - 2026-01-08

### Added

- TYPO3 v14.0 support - full compatibility with TYPO3 14.0+ while maintaining TYPO3 13.4 support ([#495](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/495))
- PHP 8.5 support - extends PHP compatibility to include the latest stable PHP version ([#495](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/495))
- DDEV environment for TYPO3 v14 local testing (`ddev install-v14`)
- E2E tests for image style/alignment functionality ([#505](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/505))

### Changed

- CI test matrix expanded to 8 combinations (TYPO3 13.4/14.0 x PHP 8.2/8.3/8.4/8.5)
- Updated branch alias to 13.x-dev
- Clarified documentation for two image styling approaches (built-in balloon toolbar vs native Style dropdown) ([#506](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/506))

### Fixed

- Image `alt` and `title` attributes rendering as literal `"true"` instead of empty string when `data-alt-override="true"` or `data-title-override="true"` was set ([#502](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/502), [#503](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/503))

## [13.1.5] - 2025-12-20

> **Note:** Versions 13.1.0-13.1.4 were blocked by GitHub's immutable releases feature during release troubleshooting. This is the first published release of the 13.1.x series.

### Added

- Modern service-based architecture using TYPO3 v13 ViewFactoryInterface and Fluid templates ([#399](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/399))
  - **New DTOs**: ImageRenderingDto and LinkDto for type-safe data contracts
  - **Three-Service Architecture**: ImageAttributeParser (HTML parsing), ImageResolverService (business logic + security), ImageRenderingService (Fluid rendering)
  - **6 Fluid Templates**: Standalone, WithCaption, Link, LinkWithCaption, Popup, PopupWithCaption
  - **Template Override Support**: Integrators can now override templates instead of PHP classes
  - **DOMDocument Parsing**: Replaced regex-based parsing with robust DOMDocument for better HTML5 support
  - **Performance**: Minimal overhead (+0.05-0.15ms per image) with ViewFactory singleton and template caching
- Comprehensive test suite for new architecture ([#399](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/399))
  - **Unit Tests**: 50+ test methods covering DTOs, services, and business logic (964 lines)
  - **Integration Tests**: 25+ test methods validating full pipeline and security (389 lines)
  - **Test Coverage**: >90% for new architecture components
- Complete documentation for modernization ([#399](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/399))
  - **RFC**: Architecture proposal with expert validation (751 lines)
  - **Migration Guide**: Step-by-step upgrade path for service architecture (300+ lines)
  - **Security Checklist**: Pre-release validation requirements
  - **Performance Benchmarking**: Guide with benchmarking scripts and acceptance criteria
- SVG dimension support and quality-based image processing ([#331](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/331), [#388](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/388))
  - SVG dimension extraction from viewBox and width/height attributes
  - Quality multiplier support (No Scaling, Standard 1.0x, Retina 2.0x, Ultra 3.0x, Print 6.0x)
  - Quality selector dropdown in image dialog with visual feedback and persistence
  - TSConfig maxWidth/maxHeight support for quality-based processing
- noScale support to skip image processing and use original files ([#77](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/77))
  - Auto-optimization when dimensions match original
  - SVG auto-detection for vector graphics
  - File size threshold control
- CKEditor 5 Widget UI with block toolbar for images ([#393](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/393))
- Automatic RTE softref enforcement via global PSR-14 event listener ([#371](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/371))
- Translation support for all hardcoded strings in image dialog ([#391](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/391))
- TypoScript bridge for lazyLoading configuration ([#373](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/373))
- Zero-configuration installation via TYPO3 v13 site sets ([#429](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/429))
  - Automatic TypoScript loading
  - Default RTE configuration with insertimage button enabled
  - Global Page TSConfig loading
- DDEV development environment with TYPO3 v13 ([#394](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/394))
- E2E tests with Playwright for click-to-enlarge functionality ([#455](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/455))
- Mutation testing with Infection for code quality validation ([#452](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/452))
- Fuzz testing for HTML parsing security ([#451](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/451))
- 18+ language translations via Crowdin integration ([#405](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/405))

### Changed

- **BREAKING (Internal Only)**: Legacy controllers removed and replaced with ImageRenderingAdapter ([#471](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/471))
  - Removed: `ImageRenderingController`, `ImageLinkRenderingController`, `AbstractImageRenderingController`
  - New: `ImageRenderingAdapter` bridges TypoScript to modern service architecture
  - TypoScript interface remains 100% compatible - no user-facing changes
  - ~1,300 lines of legacy controller code removed
  - See `Documentation/Architecture/Migration-Guide-v14.md` for architecture details
- Migrated from deprecated @typo3/ckeditor5-bundle.js to direct CKEditor imports ([#380](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/380))
- Replaced non-inclusive terminology with inclusive language ([#371](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/371))
- Updated company name to Netresearch DTT GmbH ([#442](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/442))

### Fixed

- TER publishing compatibility: ext_emconf.php no longer includes strict_types declaration ([#489](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/489))
- CI workflow now validates ext_emconf.php for TER compatibility ([#489](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/489))
- Prevent parseFunc whitespace artifacts in image rendering ([#482](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/482))
- Folder navigation and file permission check ([#480](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/480), [#290](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/290))
- Resolve attribute order mismatch in linked image replacement ([#477](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/477))
- Remove dead code referencing undefined $checkboxNoResize ([#470](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/470))
- Remove TCA `richtextConfiguration` override that blocked TSconfig preset overrides ([#464](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/464), [#467](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/467))
- Prevent empty link wrappers and ensure Bootstrap Package compatibility ([#392](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/392))
- Preserve link attributes on TYPO3 images ([#385](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/385), [#387](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/387))
- Namespace DoubleClickObserver to prevent conflicts with other plugins ([#383](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/383))
- Add link toolbar configuration to prevent linkProperties error ([#382](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/382))
- Add missing DefaultUploadFolderResolver to SelectImageController DI ([#381](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/381))
- Replace invalid env syntax with ExtensionConfiguration service injection ([#371](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/371))
- Apply Rector FunctionFirstClassCallableRector modernization ([#371](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/371))
- Use translated label for Insert Image button ([#391](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/391))

### Security

- Add file: protocol blocking to prevent local file access ([#478](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/478))
- Remove allowSvgImages option (security risk without proper sanitization) ([#478](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/478))
- Graceful frontend context handling to prevent information disclosure ([#479](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/479))
- Fix GitHub Actions workflow permissions (Scorecard alert) ([#484](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/484))
- **Preserved Security Measures**: All existing security protections maintained in new architecture
  - File visibility validation (prevents privilege escalation)
  - XSS prevention via htmlspecialchars (ENT_QUOTES | ENT_HTML5)
  - ReDoS protection (DOMDocument eliminates catastrophic backtracking)
  - Type safety via readonly DTO properties

### Technical Details

- **Architecture Benefits**:
  - Separation of Concerns: Parser → Resolver → Renderer pipeline
  - TYPO3 v13 Best Practices: Official ViewFactoryInterface standard
  - Maintainability: 876 lines of controller logic replaced by clean service architecture
  - Extensibility: Template overrides >> PHP overrides for integrators
- **Breaking Changes**: Internal only - legacy controllers removed without deprecation period (no XCLASS usage found in ecosystem)
- **Risk Assessment**: LOW - Zero evidence of XCLASS usage found in ecosystem
- **Code Statistics**: +2,596 lines (implementation + tests + docs), 23 new files

## [13.1.0-rc2] - 2025-12-16

## Release Candidate 2

### Bug Fixes

- **fix: prevent parseFunc whitespace artifacts in image rendering** (#482)
  - Fixed `<p>&nbsp;</p>` artifacts appearing between `<img>` and `<figcaption>` elements
  - Corrected invalid Fluid template syntax (`f:if="..."` → `{f:if(condition: ..., then: ...)}`)
  - Added PHP whitespace normalization in `ImageRenderingService::render()`
  - Added `figure,figcaption,a` to `encapsTagList` in TypoScript

### Testing

- All unit tests pass (131 tests)
- All functional tests pass (32 tests)
- E2E tests pass
- CodeQL analysis pass

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v13.1.0-rc2) for the original notes._

## [13.0.0] - 2025-01-08

### Added

- TYPO3 v13.4 LTS support

### Changed

- **BREAKING**: Requires TYPO3 v13.4+ and PHP 8.2+
- Upgraded dependencies and APIs for TYPO3 v13 compatibility
- Removed MagicImageService (replaced by TYPO3 core functionality)
- Updated GitHub Actions for Node.js compatibility

### Fixed

- Inline image with link sometimes causes incorrect ordering ([#186](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/186))
- RteImagePreviewRenderer throws warning with invalid HTML ([#244](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/244))
- Call to a member function count() on null ([#242](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/242))
- Add missing property transformationKey to RteImagesDbHook
- Fix onclick event for select image modal
- Loading RTE throws PHP Runtime Deprecation Notice
- Regenerate images in backend view
- Fix missing TSFE method for v13 compatibility
- Fix missing TextPreviewRenderer for v13 compatibility
- Support for TYPO3 13.4

## [13.1.0-rc1] - 2025-12-03

## 🎉 Release Candidate 1 for v13.1.0

Major feature release with significant improvements to architecture, developer experience, and internationalization.

### ✨ New Features

#### Zero-Configuration Installation
- **Zero-Config TypoScript Injection** ([#429](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/429)) - Automatic TypoScript loading via `AfterTemplatesHaveBeenDeterminedEvent`, works seamlessly with TYPO3 v13 Site Sets
- **Automatic RTE Softref Enforcement** ([#371](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/371)) - Global PSR-14 event listener for automatic TCA configuration
- **Zero-Config Installation** ([#362](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/362)) - Complete zero-configuration for TYPO3 13.4 LTS

#### Image Handling Enhancements
- **Quality Selector with Visual Feedback** ([#388](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/388)) - Frontend quality selector with persistence for image processing, fixes [#331](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/331)
- **SVG Image Dimension Handling** ([#388](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/388)) - Proper SVG support with TSConfig maxWidth/maxHeight
- **noScale Support** ([#358](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/358)) - Skip image processing when not needed, resolves [#77](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/77)
- **Remote Storage URL Preservation** ([#368](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/368)) - Support for S3, Azure, CDN URLs in RTE

#### CKEditor 5 Improvements
- **Widget UI with Block Toolbar** ([#393](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/393)) - Modern CKEditor 5 widget interface for images
- **WYSIWYG Caption Features** ([#398](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/398)) - Balloon toolbar with caption editing and toggle button
- **Improved Image Dialog** ([#369](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/369)) - Buttons matching TYPO3 backend standards

#### Architecture Refactoring
- **Fluid Templates Refactoring** ([#418](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/418)) - Complete rewrite with service-based approach and DTOs
- **Controller Refactoring** ([#395](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/395)) - Extracted shared logic to AbstractImageRenderingController, resolves [#378](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/378)
- **TYPO3 13.4 Modernization** ([#356](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/356)) - Full constructor injection in controllers and utilities

### 🐛 Bug Fixes

- **Link Attribute Preservation** ([#387](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/387)) - Preserve link attributes on TYPO3 images, fixes [#385](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/385)
- **Click-to-Enlarge** ([#369](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/369)) - Enable lightbox/popup rendering by preserving zoom attributes
- **Default Upload Folder** ([#374](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/374), [#381](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/381)) - Resolve for non-admin users in image selector
- **Empty Link Wrappers** ([#392](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/392)) - Prevent empty wrappers, ensure Bootstrap Package compatibility
- **Width/Height Dimensions** ([#347](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/347)) - Fix dimensions getting dropped and set to 1920
- **DoubleClickObserver Namespace** ([#383](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/383)) - Prevent conflicts with other plugins
- **Link Toolbar Configuration** ([#382](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/382)) - Prevent linkProperties error
- **Empty Width/Height Input** ([#367](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/367)) - Allow empty input during typing
- **ElementBrowserRegistry Injection** ([#379](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/379)) - Fix DI in SelectImageController
- **lazyLoading Configuration** ([#373](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/373)) - Add TypoScript bridge
- **CKEditor Migration** ([#380](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/380)) - Migrate from deprecated @typo3/ckeditor5-bundle.js to direct CKEditor imports
- **Image Dialog Translations** ([#391](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/391)) - Fix hardcoded strings not translated

### 🌍 Internationalization

- **Crowdin Integration** ([#400](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/400), [#403](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/403)) - Native TYPO3 Crowdin integration for translations
- **18 Language Translations** ([#402](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/402), [#405](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/405)) - Complete translations for all supported languages
- **Quality Selector Translations** ([#400](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/400)) - i18n support for quality selector strings
- **XLIFF 1.2 Upgrade** ([#407](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/407)) - All XLIFF files upgraded from 1.0 to 1.2
- **Translation Contribution Guide** ([#411](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/411)) - Added to CONTRIBUTING.md
- **Translation Error Fixes** ([#420](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/420)) - Correct translation errors in 5 language files

### 🔒 Security

- **9 Critical Vulnerability Fixes** - Fixed vulnerabilities in image handling including:
  - Array handling risk in attribute parsing
  - Information disclosure in error logging
  - Public file check to prevent privilege escalation
  - DNS rebinding and PSR-7 compliance issues

### 🧪 Testing Infrastructure

- **E2E Tests with Playwright** ([#429](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/429)) - Comprehensive test suite for click-to-enlarge
- **Unit Test Suite** ([#356](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/356)) - 60 tests, 170 assertions
- **Functional Tests** ([#356](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/356)) - Integration tests for image rendering pipeline
- **PHPUnit 12 Compatibility** ([#356](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/356)) - Modern test infrastructure with #[CoversClass] attributes

### 🔧 Developer Experience

- **DDEV Development Environment** ([#359](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/359)) - Complete local dev setup for TYPO3 v13
- **FAL Entries for E2E Tests** ([#439](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/439)) - Proper test content with RTE attributes
- **Playwright v1.57.0** ([#440](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/440)) - Updated E2E testing infrastructure
- **Netresearch-Branded Landing Page** ([#364](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/364)) - DDEV development dashboard
- **Dynamic Git Info** ([#394](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/394)) - Branch/PR info in landing page header
- **Hierarchical Command Structure** ([#365](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/365)) - Improved .envrc commands

### 📚 Documentation

- **RST Format Restructure** ([#348](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/348)) - Following TYPO3 documentation standards
- **TYPO3 Official Integration** - Webhook configured for [docs.typo3.org](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/)
- **API Documentation** ([#355](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/355)) - Enhanced cards with TYPO3 directives
- **README Refresh** ([#344](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/344)) - Comprehensive v13.0.0+ updates
- **TYPO3 Core Removal Guidance** ([#412](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/412)) - Decision guidance documentation

### 🔨 Code Quality

- **PHPStan Level 10** - Strict type checking with [phpstan-typo3](https://github.com/saschaegerer/phpstan-typo3)
- **Rector Modernizations** ([#356](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/356)) - Automated code improvements
- **PHP-CS-Fixer** - Modern coding standards
- **CodeQL Analysis** - Security scanning in CI
- **TER Compatibility** ([#427](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/427)) - Branding updates for TYPO3 Extension Repository
- **CI Modernization** ([#428](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/428)) - Modernized TER publish workflow

### 📦 Dependencies

| Package | Version |
|---------|---------|
| `actions/checkout` | v6 ([#430](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/430)) |
| `actions/upload-artifact` | v5 ([#438](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/438)) |
| `@playwright/test` | v1.57.0 ([#436](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/436)) |
| `bk2k/bootstrap-package` | v15/v16 ([#435](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/435)) |
| `commitlint` | v20 ([#341](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/341)) |
| `lint-staged` | v16 ([#342](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/342)) |

### 📋 Requirements

- **TYPO3**: 13.4.0 - 13.4.99
- **PHP**: 8.2 - 8.4

### 📖 Resources

- **Documentation**: [docs.typo3.org](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/)
- **Packagist**: [netresearch/rte-ckeditor-image](https://packagist.org/packages/netresearch/rte-ckeditor-image)
- **TER**: [rte_ckeditor_image](https://extensions.typo3.org/extension/rte_ckeditor_image)

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.0.0...v13.1.0-rc1

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v13.1.0-rc1) for the original notes._

## [13.0.1] - 2025-11-26

### Changed

- Change extension icon to Netresearch logo ([#419](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/419))
- TER compatibility and branding updates ([#427](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/427))
  - Updated descriptions in `composer.json` and `ext_emconf.php` to mention Netresearch

## [11.0.16] - 2025-01-16

_No GitHub release was created for this tag. See [git tag v11.0.16](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.16) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v11.0.16)._

## [12.0.4] - 2024-11-21

### Fixed

- Fix creation of processed files for frontend pages ([#285](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/285))

### Changed

- Update README.md for v12 ([#289](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/289))
- Revert the change "fix package name for cms_rte_ckeditor in ext_emconf.php" ([#280](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/280))
- Upgrade/fix test suite ([#294](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/294))

## [12.0.2] - 2023-11-22

### Added

- Allow inline images

### Fixed

- Make tests compatible with TYPO3 > v12
- Exclude TYPO3 v13 + PHP 8.1 from test matrix

### Changed

- Update GitHub Actions to fix Node.js 16 deprecation
- Migrate composeUpdate step for TYPO3 v12 as default and v13 support

## [12.0.1] - 2023-09-18

### Fixed

- Apply class to `<img>` element
- Update typo3/testing-framework requirement from ^7.0.2 to ^8.0.7

### Changed

- Update runtests.sh script
- Update branch aliases for v12

## [11.0.15] - 2024-02-01

## What's Changed
* chore: Fix current build/tests in pipeline/actions. by @CybotTM in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/253
* add TYPO3 badges to README by @CybotTM in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/252
* [BUGFIX] Fixes #254: handle deleted files by @hannesbochmann in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/255
* [BUGFIX] prevent open_basedir warnings by @linawolf in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/264

## New Contributors
* @hannesbochmann made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/255
* @linawolf made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/264

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.14...v11.0.15

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.15) for the original notes._

## [12.0.0] - 2023-08-25

### Added

- TYPO3 v12 LTS support

### Changed

- **BREAKING**: Requires TYPO3 v12+ and PHP 8.1+
- Update ext_emconf.php for TYPO3 v12
- Add TYPO3 badges to README

### Fixed

- Fix PHP Fatal error: Type of testExtensionsToLoad must be array
- Remove superfluous null checks and code style improvements

## [11.0.14] - 2023-07-15

### Fixed

- Add timestamp to force javascript change ([#186](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/186))
- Inline image with link sometimes causes incorrect ordering ([#186](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/186))
- Regenerate images in backend view
- Fix regex to find images ([#112](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/112))
- Remove unnecessary check for "data-*-override" attributes ([#247](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/247))
- Rework ImageLinkRenderingController to match ImageRenderingController

## [11.0.13] - 2023-06-20

### Fixed

- Sanitize HTML to prevent warnings ([#244](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/244))

## [11.0.12] - 2023-06-15

### Fixed

- RteImagePreviewRenderer throws warning with invalid HTML ([#244](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/244))
- Call to a member function count() on null ([#242](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/242))
- Add missing property transformationKey to RteImagesDbHook
- Fix onclick event for select image modal
- Loading RTE throws PHP Runtime Deprecation Notice
- Fix incorrect parse indexes leading to dynamic image URL not being resolved

### Changed

- Update typo3/testing-framework requirement from ^6.16.7 to ^7.0.2

## [11.0.11] - 2023-04-10

### Added

- TYPO3 v11 LTS support
- Configuration option for SVG images

### Fixed

- Generate publicUrl from originalImageFile storage
- Fix fileadmin doesn't start with slash in 11LTS
- Fix misuse of 11LTS BE processing middleware URLs
- Catch exception when fetching external image
- Make fetching of external image configurable
- Fix broken images in RTEs inside flexform elements
- Fix multiple PHP 8.0 warnings
- Wrong link in image ([#126](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/126))
- Wrong backwards compatibility ([#142](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/142))
- Remove wrapping p-tag from images ([#112](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/112))
- Fix jquery requirement to not crash the ckeditor ([#122](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/122))
- Fix override detection for title/alt attributes; allow empty values
- Rework preview in backend ([#56](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/56))
- PHP Warning: Undefined array key "plainImageMode" when inserting SVG image ([#205](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/205))

### Changed

- Extend from AbstractSoftReferenceIndexParser
- Implement interface instead of deprecated extension of SoftReferenceIndexParser

## [11.0.10] - 2023-06-26

## What's Changed
* [BUGFIX] generate publicUrl from originalImageFile storage by @lukasniestroj in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/219
* Fix output of alt and title attribute when overriden by @sk-foresite in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/225
* Add .attributes by @sypets in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/231
* [FEAT] #56: Show images in preview by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/106
* Correctly handle embedded images (data:image) by @sypets in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/227

## New Contributors
* @sk-foresite made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/225

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.9...v11.0.10

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.10) for the original notes._

## [11.0.9] - 2023-02-13

## What's Changed
* [BUGFIX] do not append "0" to getFileObject by @lukasniestroj in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/218

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.8...v11.0.9

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.9) for the original notes._

## [11.0.8] - 2023-02-03

## What's Changed
* Add initial security policy by @ngolatka in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/216
* [BUGFIX] Fix invalid typoscript in README, comments not allowed behind values
* [BUGFIX] Fix invalid return type of method "getLazyLoadingConfiguration" #217


**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.7...v11.0.8

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.8) for the original notes._

## [11.0.7] - 2023-02-02

## What's Changed
* Create Github issue templates (initial version) by @ngolatka in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/211
* [BUGFIX] Fix PHP warning about undefined array key access by @magicsunday in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/206 #205
* [BUGFIX] Fix a couple of common PHP issues by @magicsunday in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/207
*  Create contributing guide (initial version) by @ngolatka in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/213
* [BUGFIX] Load Image when inserted to editor by @stephanederer in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/204
* [BUGFIX] Remove deprecated call to GeneralUtility::rmFromList by @magicsunday in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/215

## New Contributors
* @ngolatka made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/211
* @magicsunday made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/206
* @stephanederer made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/204

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.6...v11.0.7

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.7) for the original notes._

## [11.0.6] - 2022-12-09

## What's Changed
* [BUGFIX] Make fetching of external image configurable by @sypets in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/192
* [BUGFIX] Catch exception when fetching external image by @sypets in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/191
* [BUGFIX] fix misuse of 11LTS BE processing middleware URLs by @jpmschuler in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/194
* [BUGFIX] Prevent undefined array key by @cngJo in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/198
* [BUGFIX] fileadmin path without slash won't output in 11LTS by @jpmschuler in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/197
* Fix undefined array key access by @fsuter in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/196

## New Contributors
* @sypets made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/192
* @cngJo made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/198
* @fsuter made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/196

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.5...v11.0.6

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.6) for the original notes._

## [11.0.5] - 2022-12-05

### Changed

- Update dependencies
- Add dependabot configuration

## [10.2.5] - 2022-06-21

## What's Changed
* [BUGFIX] Preserve important image attributes on initialization by @thommyhh in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/178


**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v10.2.4...v10.2.5

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v10.2.5) for the original notes._

## [10.2.4] - 2022-06-09

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v10.2.3...v10.2.4

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v10.2.4) for the original notes._

## [10.2.3] - 2022-06-09

Fix: composer.json

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v10.2.2...v10.2.3

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v10.2.3) for the original notes._

## [10.2.2] - 2022-06-09

- Version fixes 7b5fcb239144b37fd7b57e3eddfcda4ef0d83fbf

  - 10.x does not support TYPO3 v11
  - add 10.x-dev version alias
- fix version in ext_emconf.php 2e4b61930f639c6dfcbba0cf5ca6e9b8d218c0cc
- Version 10.2.2 376f273ec045d21b6e2accc5fadc3f8d50eda652

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v10.2.1...v10.2.2

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v10.2.2) for the original notes._

## [11.0.4] - 2022-11-28

### Fixed

- Fix package replacement

## [11.0.3] - 2022-11-15

### Added

- TYPO3 11.5 LTS support
- Custom class field for each image ([#88](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/88))
- Lazyload support ([#82](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/82))

### Fixed

- Disabled button issue in CKEditor toolbar ([#145](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/145))

### Changed

- Make extension error-free on PHPStan levels 0-8
- Require PHP 7.4 or newer

## [10.2.1] - 2021-12-31

## What's Changed
* [bugfix/145]Fix disabled button. by @lucmuller in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/149


**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v10.2.0...v10.2.1

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v10.2.1) for the original notes._

## [11.0.2] - 2021-12-30

## What's Changed
* [bugfix/145]Fix disabled button. Fixes netresearch/t3x-rte_ckeditor_image #145 by @lucmuller in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/146

## New Contributors
* @lucmuller made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/146

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.1...v11.0.2

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.2) for the original notes._

## [11.0.1] - 2021-12-11

## What's Changed
* [BUGFIX] #126 Wrong link in image, #142 Wrong backwards compatibility by @cnmarco in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/143

## New Contributors
* @cnmarco made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/143

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.0...v11.0.1

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.1) for the original notes._

## [11.0.0] - 2021-12-06

## What's Changed
* [BUGFIX] use parseFunc_RTE for processing by @vellip in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/116
* [FEAT] #88: Add custom class field for each image by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/108
* [TASK] Wrapping innerText to avoid js error by @eliasfernandez in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/114
* [TASK] guard clause if jQuery is not present by @eliasfernandez in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/115
* Revert "[BUGFIX] use parseFunc_RTE for processing" by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/117
* [FEAT] #82: TYPO3 lazyload support by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/107
* [BUGFIX] Fix override detection for title/alt attributes; allow empt… by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/118
* Added extension-key to composer.json by @rvock in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/121
* [TASK] Allow installation on TYPO3 v11 by @susannemoog in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/123
* [BUGFIX] #122: Fix jquery requirement to not crash the ckeditor by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/127
* [BUGFIX] #112: Remove wrapping p-tag from images by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/130
* Resolve retrieved file correctly if its a processed file by @tgaertner in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/134
* TYPO3 11.5.x by @MIchelHolz in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/138
* [Task] Make installable in 11.5 via Composer by @typoniels in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/139
* WIP: [BUGFIX] #112: Remove wrapping p-tag from images via TS by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/129

## New Contributors
* @vellip made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/116
* @eliasfernandez made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/114
* @rvock made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/121
* @susannemoog made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/123
* @tgaertner made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/134
* @MIchelHolz made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/138
* @typoniels made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/139

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/10.1.0...v11.0.0

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v11.0.0) for the original notes._

## [10.2.0] - 2021-11-06

## What's Changed
* [BUGFIX] use parseFunc_RTE for processing by @vellip in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/116
* [FEAT] #88: Add custom class field for each image by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/108
* [TASK] Wrapping innerText to avoid js error by @eliasfernandez in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/114
* [TASK] guard clause if jQuery is not present by @eliasfernandez in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/115
* Revert "[BUGFIX] use parseFunc_RTE for processing" by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/117
* [FEAT] #82: TYPO3 lazyload support by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/107
* [BUGFIX] Fix override detection for title/alt attributes; allow empt… by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/118
* Added extension-key to composer.json by @rvock in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/121
* [TASK] Allow installation on TYPO3 v11 by @susannemoog in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/123
* [BUGFIX] #122: Fix jquery requirement to not crash the ckeditor by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/127
* [BUGFIX] #112: Remove wrapping p-tag from images by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/130
* Resolve retrieved file correctly if its a processed file by @tgaertner in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/134

## New Contributors
* @vellip made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/116
* @eliasfernandez made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/114
* @rvock made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/121
* @susannemoog made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/123
* @tgaertner made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/134

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/10.1.0...v10.2.0

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v10.2.0) for the original notes._

## [10.1.0] - 2021-05-20

### Added

- TYPO3 10 LTS support
- Linked image renderer ([#42](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/42))
- Remove empty image attributes ([#35](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/35))
- Regenerate missing processed images ([#78](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/78))
- TYPO3 fluid_styled_content lazyload support ([#82](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/82))

### Fixed

- Respect max width and height configuration for images when saving element ([#61](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/61))
- Consider override checkbox for title and alt attributes ([#69](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/69))
- Allow single quotes in image attributes ([#74](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/74))
- Use original files width and height for ratio and max ([#70](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/70))
- Add IE11 support by removing arrow functions ([#66](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/66))
- Regenerate missing magic image on rendering images within links ([#57](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/57), [#25](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/25))
- Avoid losing existing attributes when editing image ([#54](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/54))
- Image properties not working inside table cell ([#41](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/41))
- Fix DOM element count
- Fix TER package replacement
- Support legacy `clickenlarge` attribute for image zoom

### Changed

- Update image reference index ([#45](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/45), [#62](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/62))
- Compatibility with TYPO3 CMS 9.x

[Unreleased]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.7.1...HEAD
[13.7.1]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.6.1...v13.7.1
[13.6.1]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.6.0...v13.6.1
[13.6.0]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.5.0...v13.6.0
[13.5.0]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.4.2...v13.5.0
[13.4.2]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.4.1...v13.4.2
[13.4.1]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.4.0...v13.4.1
[13.4.0]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.3.2...v13.4.0
[13.3.2]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.2.0...v13.3.2
[13.2.0]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.1.5...v13.2.0
[13.1.5]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.0.1...v13.1.5
[13.0.1]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.0.0...v13.0.1
[13.0.0]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v12.0.4...v13.0.0
[12.0.4]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v12.0.2...v12.0.4
[12.0.2]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v12.0.1...v12.0.2
[12.0.1]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v12.0.0...v12.0.1
[12.0.0]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.14...v12.0.0
[11.0.14]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.13...v11.0.14
[11.0.13]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.12...v11.0.13
[11.0.12]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.11...v11.0.12
[11.0.11]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.5...v11.0.11
[11.0.5]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.4...v11.0.5
[11.0.4]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v11.0.3...v11.0.4
[11.0.3]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v10.1.0...v11.0.3
[10.1.0]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v10.0.0...v10.1.0

## [10.0.0] - 2020-12-23

_No GitHub release was created for this tag. See [git tag v10.0.0](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v10.0.0) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v10.0.0)._

## [9.0.5] - 2020-10-10

## What's Changed
* [Classes] Fix attrSearchPattern ImageLinkRendering  by @UNI49 in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/74
* Create LICENSE by @CybotTM in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/80
* Issue 71 fix composer by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/93
* [BUGFIX] #61: Respect max width and height configuration for images w… by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/96
* [FEAT] #78: Regenerate missing processed images by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/95
* [BUGFIX] #69: Remove zoom attributes when checkbox is disabled by @mcmulman in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/94
* Fix deprecation log about locallang_core.xlf by @tmotyl in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/81

## New Contributors
* @UNI49 made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/74
* @CybotTM made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/80
* @tmotyl made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/81

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v9.0.4...v9.0.5

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v9.0.5) for the original notes._

## [9.0.4] - 2019-10-26

## What's Changed
* [BUGFIX] #25: Regenerate missing processed image for linked images by @muh-nr in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/57
* suggestion for reformat code by @sascha307050 in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/58
* [TASK] #45: Update image reference index by @muh-nr in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/62
* added ie11 support by removing arrow functions by @christophmellauner in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/66
* [BUGFIX] Use original files width and height for ratio and max by @thommyhh in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/70

## New Contributors
* @sascha307050 made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/58
* @christophmellauner made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/66

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v9.0.3...v9.0.4

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v9.0.4) for the original notes._

## [9.0.3] - 2019-07-03

## What's Changed
* [REFACTOR] #38: Process image on first selection by @muh-nr in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/55


**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v9.0.2...v9.0.3

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v9.0.3) for the original notes._

## [9.0.2] - 2019-06-21

## What's Changed
* [BUGFIX] Avoid loosing existing attributes when editing image by @thommyhh in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/54


**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v9.0.1...v9.0.2

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v9.0.2) for the original notes._

## [9.0.1] - 2019-03-19

_No GitHub release was created for this tag. See [git tag v9.0.1](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v9.0.1) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v9.0.1)._

## [9.0.0] - 2019-03-19

## What's Changed
* [BUGFIX] `Image properties` not working inside table cell by @thommyhh in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/41
* Dev typo3 9.x refactor 29 backend image url by @muh-nr in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/48
* [FEATURE] Compatibility TYPO3 9.x by @muh-nr in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/43


**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v8.7.8...v9.0.0

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v9.0.0) for the original notes._

## [8.9.0] - 2019-02-05

## What's Changed
* [REFACTOR] #29: Process backend image url savely by @muh-nr in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/47


**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v8.8.0...v8.9.0

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.9.0) for the original notes._

## [8.8.0] - 2018-12-13

## What's Changed
* [BUGFIX] `Image properties` not working inside table cell by @thommyhh in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/41


**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v8.7.8...v8.8.0

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.8.0) for the original notes._

## [8.7.8] - 2018-12-04

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v8.7.7...v8.7.8

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.7.8) for the original notes._

## [8.7.7] - 2018-12-02

## What's Changed
* [BUGFIX] Use proper condition for empty DomDocuments by @flossels in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/37

## New Contributors
* @flossels made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/37

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v8.7.6...v8.7.7

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.7.7) for the original notes._

## [8.7.6] - 2018-11-30

Fixes output of linked images and image popups/lightboxes.

## What's Changed
* [BUGFIX] Add custom image link handler to get fallback image values; … by @muh-nr in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/34


**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v8.7.5...v8.7.6

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.7.6) for the original notes._

## [8.7.5] - 2018-11-24

## What's Changed
* Depend on typo3/cms-core instead of typo3/cms by @bmack in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/24
* Update URLs by @benabbottnz in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/30

## New Contributors
* @bmack made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/24
* @benabbottnz made their first contribution in https://github.com/netresearch/t3x-rte_ckeditor_image/pull/30

**Full Changelog**: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v8.7.4...v8.7.5

_See [GitHub release](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.7.5) for the original notes._

## [8.7.4] - 2017-08-17

_No GitHub release was created for this tag. See [git tag v8.7.4](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.7.4) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v8.7.4)._

## [8.7.3] - 2017-06-26

_No GitHub release was created for this tag. See [git tag v8.7.3](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.7.3) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v8.7.3)._

## [8.7.2] - 2017-06-23

_No GitHub release was created for this tag. See [git tag v8.7.2](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.7.2) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v8.7.2)._

## [8.7.1] - 2017-05-17

_No GitHub release was created for this tag. See [git tag v8.7.1](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/tag/v8.7.1) and [commit log](https://github.com/netresearch/t3x-rte_ckeditor_image/commits/v8.7.1)._

