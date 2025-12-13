# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- Remove TCA `richtextConfiguration` override that blocked TSconfig preset overrides (#464)

## [14.0.0] - TBD

### Added

- Modern service-based architecture using TYPO3 v13 ViewFactoryInterface and Fluid templates (#399)
  - **New DTOs**: ImageRenderingDto and LinkDto for type-safe data contracts
  - **Three-Service Architecture**: ImageAttributeParser (HTML parsing), ImageResolverService (business logic + security), ImageRenderingService (Fluid rendering)
  - **6 Fluid Templates**: Standalone, WithCaption, Link, LinkWithCaption, Popup, PopupWithCaption
  - **Template Override Support**: Integrators can now override templates instead of PHP classes
  - **DOMDocument Parsing**: Replaced regex-based parsing with robust DOMDocument for better HTML5 support
  - **Performance**: Minimal overhead (+0.05-0.15ms per image) with ViewFactory singleton and template caching
- Comprehensive test suite for new architecture
  - **Unit Tests**: 50+ test methods covering DTOs, services, and business logic (964 lines)
  - **Integration Tests**: 25+ test methods validating full pipeline and security (389 lines)
  - **Test Coverage**: >90% for new architecture components
- Complete documentation for modernization
  - **RFC**: Architecture proposal with expert validation (751 lines)
  - **Migration Guide**: Step-by-step upgrade path from v13 to v14 (300+ lines)
  - **Security Checklist**: Pre-release validation requirements
  - **Performance Benchmarking**: Guide with benchmarking scripts and acceptance criteria

### Changed

- **DEPRECATION**: ImageRenderingController and ImageLinkRenderingController are deprecated (will be removed in v15.0)
  - Old controllers still work exactly as before (ZERO breaking changes in v14.0)
  - Deprecation warnings logged with `E_USER_DEPRECATED`
  - 1-year migration window before removal
  - See `Documentation/Architecture/Migration-Guide-v14.md` for upgrade path

### Security

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
- **Breaking Changes**: NONE in v14.0 (deprecation only)
- **Risk Assessment**: LOW - Zero evidence of XCLASS usage found in ecosystem
- **Code Statistics**: +2,596 lines (implementation + tests + docs), 23 new files

### Changed

- [TASK] Extract shared controller logic to AbstractImageRenderingController (resolves #378)
  - **Architecture**: Eliminate 66 lines of code duplication between ImageRenderingController and ImageLinkRenderingController
  - **New Abstract Base**: AbstractImageRenderingController with shared methods (getLazyLoadingConfiguration, getLogger, getAttributeValue, shouldSkipProcessing, validateFileVisibility)
  - **Security Enhancement**: Dedicated validateFileVisibility() method for consistent file visibility validation
  - **Type Safety**: Comprehensive type guards for TypoScript array access and File property handling
  - **PHPStan Excellence**: Baseline reduced from 650 → 205 → 188 errors (71.1% total improvement, -462 errors) while maintaining level 10 (maximum strictness)
  - **Quality Metrics**: All 92 unit tests pass, code standards compliant (PSR-12/PER-CS2.0), Rector clean
  - **Impact**: Improved maintainability, centralized shared logic, enhanced security, reduced technical debt, prevents runtime type errors
  - **API Compatibility**: No breaking changes to public API or behavior

### Added

- SVG dimension support and quality-based image processing (#331, #388)
  - SVG dimension extraction from viewBox and width/height attributes
  - Quality multiplier support (No Scaling, Standard 1.0x, Retina 2.0x, Ultra 3.0x, Print 6.0x)
  - Quality selector dropdown in image dialog with visual feedback and persistence
  - TSConfig maxWidth/maxHeight support for quality-based processing
- noScale support to skip image processing and use original files (#77)
  - Auto-optimization when dimensions match original
  - SVG auto-detection for vector graphics
  - File size threshold control
- CKEditor 5 Widget UI with block toolbar for images (#393)
- Automatic RTE softref enforcement via global PSR-14 event listener (#371)
- Global PSR-14 event listener for TCA overrides (replaces manual overrides)
- TCA override for EXT:news support
- Translation support for all hardcoded strings in image dialog (#391)
- TypoScript bridge for lazyLoading configuration (#373)
- TYPO3 v13 site set for zero-configuration installation
- Automatic TypoScript loading for zero-configuration
- Default RTE configuration with insertimage button enabled
- Global Page TSConfig loading for automatic configuration
- DDEV development environment with TYPO3 v13

### Changed

- Migrated from deprecated @typo3/ckeditor5-bundle.js to direct CKEditor imports (#380)
- Replaced non-inclusive terminology with inclusive language
- Updated company name to Netresearch DTT GmbH

### Fixed

- Prevent empty link wrappers and ensure Bootstrap Package compatibility (#392)
- Preserve link attributes on TYPO3 images (#385, #387)
- Namespace DoubleClickObserver to prevent conflicts with other plugins (#383)
- Add link toolbar configuration to prevent linkProperties error (#382)
- Add missing DefaultUploadFolderResolver to SelectImageController DI (#381)
- Replace invalid env syntax with ExtensionConfiguration service injection
- Apply Rector FunctionFirstClassCallableRector modernization
- Use translated label for Insert Image button

## [13.0.0] - 2024-12-13

### Added

- TYPO3 v13.4 LTS support

### Changed

- **BREAKING**: Requires TYPO3 v13.4+ and PHP 8.2+
- Upgraded dependencies and APIs for TYPO3 v13 compatibility
- Removed MagicImageService (replaced by TYPO3 core functionality)
- Updated GitHub Actions for Node.js compatibility

### Fixed

- Fix #186: Inline image with link sometimes causes incorrect ordering
- Fix #244: RteImagePreviewRenderer throws warning with invalid HTML
- Fix #242: Call to a member function count() on null
- Add missing property transformationKey to RteImagesDbHook
- Fix onclick event for select image modal
- Loading RTE throws PHP Runtime Deprecation Notice
- Regenerate images in backend view
- Fix missing TSFE method for v13 compatibility
- Fix missing TextPreviewRenderer for v13 compatibility
- Support for TYPO3 13.4

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

- Fix #186: Add timestamp to force javascript change
- Fix #186: Inline image with link sometimes causes incorrect ordering
- Regenerate images in backend view
- Fix regex to find images (#112)
- Remove unnecessary check for "data-*-override" attributes (#247)
- Rework ImageLinkRenderingController to match ImageRenderingController

## [11.0.13] - 2023-06-20

### Fixed

- Fix #244: Sanitize HTML to prevent warnings

## [11.0.12] - 2023-06-15

### Fixed

- Fix #244: RteImagePreviewRenderer throws warning with invalid HTML
- Fix #242: Call to a member function count() on null
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
- Fix #126: Wrong link in image
- Fix #142: Wrong backwards compatibility
- Fix #112: Remove wrapping p-tag from images
- Fix #122: Fix jquery requirement to not crash the ckeditor
- Fix override detection for title/alt attributes; allow empty values
- Fix #56: Rework preview in backend
- Fix #205: PHP Warning: Undefined array key "plainImageMode" when inserting SVG image

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
- Custom class field for each image (#88)
- Lazyload support (#82)

### Fixed

- Fix #145: Disabled button issue in CKEditor toolbar

### Changed

- Make extension error-free on PHPStan levels 0-8
- Require PHP 7.4 or newer

## [10.1.0] - 2021-05-20

### Added

- TYPO3 10 LTS support
- Linked image renderer (#42)
- Remove empty image attributes (#35)
- Regenerate missing processed images (#78)
- TYPO3 fluid_styled_content lazyload support (#82)

### Fixed

- Fix #61: Respect max width and height configuration for images when saving element
- Fix #69: Consider override checkbox for title and alt attributes
- Fix #74: Allow single quotes in image attributes
- Fix #70: Use original files width and height for ratio and max
- Fix #66: Add IE11 support by removing arrow functions
- Fix #57: Regenerate missing magic image on rendering images within links (#25)
- Fix #54: Avoid losing existing attributes when editing image
- Fix #41: Image properties not working inside table cell
- Fix DOM element count
- Fix TER package replacement
- Support legacy `clickenlarge` attribute for image zoom

### Changed

- Update image reference index (#45, #62)
- Compatibility with TYPO3 CMS 9.x

[Unreleased]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v13.0.0...HEAD
[13.0.0]: https://github.com/netresearch/t3x-rte_ckeditor_image/compare/v12.0.2...v13.0.0
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
