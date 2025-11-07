## [Unreleased]

### Added

- Add SVG dimension support and quality-based image processing (#331, NEXT-89)
  - SVG dimension extraction from viewBox and width/height attributes
  - Quality multiplier support (No Scaling, Standard 1.0x, Retina 2.0x, Ultra 3.0x, Print 6.0x)
  - Automatic aspect ratio preservation during dimension calculations
  - Quality selector dropdown in image dialog with visual color-coded indicators
  - Improved dialog layout - Display width/height/quality in single row
  - Quality selection persists via data-quality HTML attribute
  - Backward compatibility with data-noscale attribute
  - User dimensions preserved (not overwritten by backend suggestions)
  - Comprehensive unit tests for SVG dimensions, quality multipliers, and dimension calculations
  - Use cases: High-DPI displays (Retina), print-quality images (Print), responsive scaling (Standard)

- Add noScale support to skip image processing and use original files (#77)
  - Implements TYPO3's standard `noScale` parameter for RTE images
  - Enables use of original files without creating processed variants in typo3temp/
  - Use cases: newsletters, PDFs, retina displays, performance optimization
  - Configuration via TypoScript: `lib.parseFunc_RTE.tags.img.noScale = 1`
  - Auto-optimization: Automatically skips processing when dimensions match original
  - SVG auto-detection: Vector graphics always use original file (no rasterization)
  - File size threshold: Control auto-optimization for large files (`noScale.maxFileSizeForAuto`)
  - Applies to both regular images and linked images
  - Maintains backward compatibility (default: `noScale = 0`)
  - Comprehensive unit test coverage (14 tests) for shouldSkipProcessing() logic

## [13.0.0] - 2024-12-XX

### Changed

- Update author in ext_emconf.php
- Update Contributors.md
- Remove PHP 8.3 in testing.yml
- Update ext_emconf.php for TYPO3 v12
- Update GitHub actions dependencies
- Update dev/test packages and tools for TYPO3 v13 only
- Update main branch alias
- Remove MagicImageService
- Remove PHP 8.1 from workflows
- Migrate phpunit configuration
- Update test suite with latest PHP and TYPO3 versions
- Update runtests.sh script
- Replace obsolete recordlist extension (#282)
- Replace obsolete friendsoftypo3/phpstan-typo3
- Remove deprecated usage of ExtensionManagementUtility::addPageTSConfig()
- Replace deprecated TYPO3_MODE
- Remove access to non-existent property (removed in TYPO3 v10)

### Fixed

- Fix incorrect toolbar button name in README and DDEV setup (insertimage not typo3image)
- Fix #186: Add timestamp to force the javascript change
- Fix #186: Inline image with link sometimes causes incorrect ordering of double
- Regenerate images in backend view
- Fix #244: RteImagePreviewRenderer throws warning with invalid HTML
- Fix #242: Call to a member function count() on null
- Add missing property transformationKey to RteImagesDbHook
- Fix onclick event for select image modal
- Fix loading RTE throws PHP Runtime Deprecation Notice
- Fix missing TSFE method
- Fix missing TextPreviewRenderer
- Fix visibility
- Fix support for 13.4
- Fix incorrect parse indexes leading to dynamic image URL not to be resolved
- Fix #270: Circumvent PHP < 7.4.4 bug with childNodes being NULL
- Validate imageSource only if not NULL
- Call to undefined method - GeneralUtility::shortMD5() was removed in TYPO3 v12
- Ensure $imageSource is valid before working with it
- Fix #286: Issue with image processing
- Fix createProcessedFile method description
- Fix functional tests

### Maintenance

- PHPStan findings and code quality improvements
- PHP CodeSniffer findings and fixes
- Rector findings and updates
- Coding style improvements
- Configure devcontainer with vscode extensions, PHP, composer, and gh cli
- Add docker and compose to devcontainer
- Add rector configuration
- Configure GitHub codespace environment container to PHP 8.2
- Update pipeline/action for updated test suite
- Update phpstan configuration
- Ignore rector folder created in devcontainer
- Allow inline images
- TYPO3 v12 Support
- Add TYPO3 badges to README
- Update README.md for TYPO3 v12

## [11.0.11] - 2023-XX-XX

### Changed

- Configure .gitignore
- Extend from AbstractSoftReferenceIndexParser
- Implement interface instead of deprecated extension of SoftReferenceIndexParser
- Allow installation on TYPO3 v11

### Fixed

- Set version in github action add-to-pipeline
- Generate publicUrl from originalImageFile storage
- Do not append "0" to getFileObject
- Clean up ext_emconf to upload manual in TER
- Fix fileadmin doesn't start with slash in 11LTS
- Prevent undefined array key
- Fix misuse of 11LTS BE processing middleware URLs
- Catch exception when fetching external image
- Make fetching of external image configurable
- Check if array index exist by wrapping with null coalescing operator and set reasonable default value
- Preserve important image attributes on initialization
- Fix broken images in RTEs inside flexform elements
- Fix multiple PHP 8.0 warnings
- Fix #126: Wrong link in image, #142: Wrong backwards compatibility
- Fix #112: Remove wrapping p-tag from images via TypoScript
- Fix #112: Remove wrapping p-tag from images
- Fix #122: Fix jquery requirement to not crash the ckeditor
- Fix override detection for title/alt attributes; allow empty values for alt/title
- Revert "[BUGFIX] use parseFunc_RTE for processing"
- Use parseFunc_RTE for processing
- Fix #56: Rework preview in backend
- Override preview for ctype text; show dummy images in preview
- Fix output of alt and title attribute when overriden
- Ensure the file processing is not deferred
- Fix #205: PHP Warning: Undefined array key "plainImageMode" when insert a SVG Image

### Documentation

- Update SECURITY.md
- Correction of typo + list indentation
- Change README
- Fix invalid typoscript in README, comments not allowed behind values
- Fix license link in doc blocks to match actual LICENSE file

### Maintenance

- Update TER release info
- Create Github action for publishing to TER
- Add initial security policy
- Create contributing guide (initial version)
- Create pull request template
- Fix phpstan warnings and issues
- Optimize code
- Fix CGL
- Do not prepend site URL to embedded image in RTE
- Do not prepend slash for data:image
- Fix invalid subclassing of hook, use given object instance instead
- Fix invalid return type of method "getLazyLoadingConfiguration" (#217)
- Remove deprecated call to GeneralUtility::rmFromList (#212)
- Add search params to url, if available (necessary when filestorage is not public)
- Update typo3image.js
- Fix some possible php errors
- Fix phpcs issues
- Fix some phpstan issue, raise level to 8
- Prevent undefined array key warning in ImageRenderingController
- Check for array key
- Rework SelectImageController
- Drop useless method
- Clear doc blocks
- Remove unused use statement
- Rework method to extract image width/height from attributes to return single value instead of array
- Rename soft reference parser in order to match the core structure and logic
- Rework RteImagesSoftReferenceIndex to fix phpstan issues
- Fix returning wrong value from array
- Add psr/log to composer.json
- Fix return type of getImageAttributes
- Avoid duplicate is_array check
- Remove obsolete code due previous changes
- Fix boolean comparison in if condition
- Fix wrong variable name usage, prevents setting of lazyloading attribute
- Fix invalid usage of short ternary operator
- Prevent phpstan warning "call to method of object"
- Use only boolean comparison in if conditions
- Prevent NULL pointer access
- Remove redeclaration of parent class variable
- Avoid usage of empty, use more strict comparison
- Updated composer.json, add missing TYPO3 dependencies
- Reorder use statements
- Fix class variable types
- Fix parameters of preUserFunc calls
- Fix call to makeTokenID
- Add missing doc blocks
- Remove deprecated call to HttpUtility::setResponseCodeAndExit
- Replace qualifier with an import
- Inline variable
- Remove redundant arguments
- Use constant from base class
- Add missing @throws annotation
- Use merge unset calls
- Rework not required switch construct
- Use strpos/stripos instead of substr
- Use short hand ternary operator
- Add missing parent constructor call
- Add missing method return and parameter types
- Use null coalescing operator
- Fix wording
- Add strict_types
- Fix repository name in order to display badges
- Update composer.json
- Update composer.json, add allowed plugins
- Add badges to README
- Fix path in phpcs workflow
- Add github workflows for phpcs, phpstan and codeql
- Create Github issue templates (initial version)
- Create add-to-project.yml
- Set version for next release
- Fix undefined array key access
- Update install instructions

## [11.0.5] - 2023-XX-XX

### Changed

- Update namelesscoder/typo3-repository-client requirement
- Add alternate dev versions
- Create dependabot.yml
- Fix homepage
- Fix syntax

## [11.0.4] - 2023-XX-XX

### Fixed

- Fix package replacement

## [11.0.3] - 2023-XX-XX

### Changed

- Update typo3image.js, Fix Pull request #137
- Make extension error-free on PHPStan levels 0-8
- Explicitly require PHP 7.4 or newer
- Drop version from install instructions
- Fix version in ext_emconf.php
- Update composer.json
- Make installable in 11.5 via Composer

### Fixed

- Fix #145: Fix disabled button - solution is parsing of every toolbars, then checking if the command is 'image'
- Check for $pI['scheme'] with isset() instead
- Make renderImageAttributes() arguments optional
- Fix required parameter after optional parameter is deprecated

### Added

- TYPO3 11.5LTS support
- Add MichelHolz to Contributors list
- Implement missing processed file resolve
- Added extension-key to composer.json
- Add #82: TYPO3 fluid_styled_content lazyload support
- Add #88: Add custom class field for each image
- Add guard clause if jQuery is not present
- Wrapping innerText to avoid js error

## [10.1.0] - 2021-XX-XX

### Added

- Refactor linked image renderer (#42)
- Render images within links via regex (#42)
- Add #35: Remove empty image attributes
- Add #78: Regenerate missing processed images
- Add #82: TYPO3 fluid_styled_content lazyload support

### Changed

- Include lower TYPO3 core versions
- Fix extensionscanner and cleanup code
- Add TYPO3 10 compatibility
- Update #45: Update image reference index (#62)
- Update dependencies and deployment infos
- Update branch alias
- Compatibility with CMS 9.x

### Fixed

- Respect zoom and attribute checkbox values
- Fix #61: Respect max width and height configuration for images when saving element
- Fix #69: Consider override checkbox for title and alt attributes
- Fix #69: Remove zoom attributes when checkbox is disabled
- Allow single quotes in image attributes (#74)
- Use original files width and height for ratio and max (#70)
- Add IE11 support by removing arrow functions (#66)
- Use customised width and height on first selection
- Prevent value null for title and alt attributes
- Fix #25: Regenerate missing magic image on rendering images within links (#57)
- Avoid losing existing attributes when editing image (#54)
- Support legacy `clickenlarge` attribute for image zoom
- Keep data attributes for zoom images
- Fix #42: Refactor linked image renderer
- Fix `Image properties` not working inside table cell (#41)
- Fix DOM element count
- Use proper condition for empty DomDocuments
- Convert parsed link content to HTML encoding
- Fix for custom popup attributes
- Replace broken TS variables in popup; enable custom popup configuration
- Add custom image link handler to get fallback image values; Remove empty style attr
- Use $.extend for compatibility with IE11
- Fix TER package replacement

### Documentation

- Update Readme
- Update documentation and composer specs
- Usage of config according to t3 bug (#68)

### Maintenance

- Cleanup & refactor after rebase
- Get TsConfig for image rendering
- Update composer.json
- Change $eventDispatcher to protected
- Add RteImagesDbHook to service.yaml
- Add old transformation
- Change _updateMetaDataProperties to updateProperties
- Add checkbox filelist, importSelection
- Add use EventDispatcherInterface
- Add service for eventDispatcher
- Add EventDispatcher
- Remove outdated replace instruction
- Update requirements and branch-alias
- Fix deprecation log about locallang_core.xlf
- Refactor #71: Use underscores in package name
- Fix #71: Add vendor name to composer replace to prevent validation warnings
- Create LICENSE
- Add another important person to authors
- Refactor #63: Use correct image path
- Suggestion for reformat code (#58)
- Refactor #38: Process image on first selection (#55)
- Fix: Update dev branch naming
- Refactor #29: Process backend image url safely
- Update branch alias version
- Review #43: Cleanup JS
- Add info for RTE config
- Convert indents to spaces
- Release 9.0.3 - Process image on first selection
- Release 9.0.2 - Bugfix for image attributes
- Release 8.7.8 - Fix DOM element count
- Release 8.7.7 - Fix output of link content
- Release 8.7.6
- Release 8.7.5 - Add fallback for image dimensions; clean code
- Update documentation
- Update URLs
- Depend on typo3/cms-core instead of typo3/cms
- Set current file before calling imageLinkWrap (refs #10)
- Updating version
- Fixed package replacee leading to wrongly inferred extension key
- Implemented click enlarge feature (fixes #2)
- Replace CKEditor image dialog with TYPO3 image dialog on image double click (fixes #6)
- Added installation instructions
- Fixed travis build
- Set current version
- Added travis build
- Updated docs with demo gif
- Fixed events on dialog obviously being removed after modal close by rerendering the dialog
- Added docs
- Several bug fixes
- Added frontend image rendering
- Implemented image selection
- Initial import

