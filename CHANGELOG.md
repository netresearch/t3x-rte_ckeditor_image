# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

## [13.0.1] - 2025-11-26

### Changed

- Change extension icon to Netresearch logo ([#419](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/419))
- TER compatibility and branding updates ([#427](https://github.com/netresearch/t3x-rte_ckeditor_image/pull/427))
  - Updated descriptions in `composer.json` and `ext_emconf.php` to mention Netresearch

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

## [11.0.5] - 2022-12-05

### Changed

- Update dependencies
- Add dependabot configuration

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

[Unreleased]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.1.5...HEAD
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
