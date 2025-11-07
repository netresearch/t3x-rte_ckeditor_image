## [Unreleased]

### Added

- SVG dimension support and quality-based image processing (#331, NEXT-89)
  - SVG dimension extraction from viewBox and width/height attributes
  - Quality multiplier support (No Scaling, Standard 1.0x, Retina 2.0x, Ultra 3.0x, Print 6.0x)
  - Automatic aspect ratio preservation during dimension calculations
  - Quality selector dropdown in image dialog with visual color-coded indicators
  - Improved dialog layout - Display width/height/quality in single row
  - Quality selection persists via data-quality HTML attribute
  - Backward compatibility with data-noscale attribute
  - User dimensions preserved (not overwritten by backend suggestions)
  - Use cases: High-DPI displays (Retina), print-quality images (Print), responsive scaling (Standard)

- noScale support to skip image processing and use original files (#77)
  - Implements TYPO3's standard `noScale` parameter for RTE images
  - Enables use of original files without creating processed variants in typo3temp/
  - Use cases: newsletters, PDFs, retina displays, performance optimization
  - Configuration via TypoScript: `lib.parseFunc_RTE.tags.img.noScale = 1`
  - Auto-optimization: Automatically skips processing when dimensions match original
  - SVG auto-detection: Vector graphics always use original file (no rasterization)
  - File size threshold: Control auto-optimization for large files (`noScale.maxFileSizeForAuto`)
  - Applies to both regular images and linked images
  - Maintains backward compatibility (default: `noScale = 0`)

## [13.0.0] - 2024-12-XX

### Added

- TYPO3 v13.4 LTS support

### Changed

- Upgrade to TYPO3 v13.4 and PHP 8.2+ (breaking change)
- Replaced obsolete dependencies and APIs for TYPO3 v13 compatibility

### Fixed

- Fix #186: Inline image with link sometimes causes incorrect ordering
- Fix #244: RteImagePreviewRenderer throws warning with invalid HTML
- Fix #242: Call to a member function count() on null
- Fix #286: Issue with image processing
- Fix #270: Circumvent PHP < 7.4.4 bug with childNodes being NULL
- Fix incorrect toolbar button name in README and DDEV setup (insertimage not typo3image)
- Fix onclick event for select image modal
- Fix loading RTE throws PHP Runtime Deprecation Notice
- Regenerate images in backend view
- Fix incorrect parse indexes leading to dynamic image URL not to be resolved

## [11.0.11] - 2023-XX-XX

### Added

- TYPO3 v11 LTS support

### Fixed

- Generate publicUrl from originalImageFile storage
- Fix fileadmin doesn't start with slash in 11LTS
- Fix misuse of 11LTS BE processing middleware URLs
- Catch exception when fetching external image
- Fix broken images in RTEs inside flexform elements
- Fix multiple PHP 8.0 warnings
- Fix #126: Wrong link in image
- Fix #142: Wrong backwards compatibility
- Fix #112: Remove wrapping p-tag from images
- Fix #122: Fix jquery requirement to not crash the ckeditor
- Fix override detection for title/alt attributes; allow empty values for alt/title
- Fix #56: Rework preview in backend
- Fix #205: PHP Warning: Undefined array key "plainImageMode" when insert a SVG Image

## [11.0.5] - 2023-XX-XX

### Changed

- Update dependencies

## [11.0.4] - 2023-XX-XX

### Fixed

- Fix package replacement

## [11.0.3] - 2023-XX-XX

### Added

- TYPO3 11.5 LTS support
- Custom class field for each image (#88)
- Lazyload support (#82)

### Fixed

- Fix #145: Disabled button issue

## [10.1.0] - 2021-XX-XX

### Added

- TYPO3 10 LTS support
- Linked image renderer (#42)
- Remove empty image attributes (#35)
- Regenerate missing processed images (#78)

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

