# 11.0.8

# 11.0.8

## TASK

- 20297bb [TASK] Configure .gitignore
- 64e178e [TASK] Extend from AbstractSoftReferenceIndexParser
- de7f013 [TASK] Implement interface instead of deprecated extension of SoftReferenceIndexParser
- a89ebe1 [TASK] Allow installation on TYPO3 v11

## BUGFIX

- ca7d660 [BUGFIX] Clean up ext_emconf to upload manual in TER
- 7addfa7 [BUGFIX] fileadmin doesn't start with slash in 11LTS
- 82a13eb [BUGFIX] Prevent undefined array key
- b111bca [BUGFIX] fix misuse of 11LTS BE processing middleware URLs
- 8f9fc92 [BUGFIX] Catch exception when fetching external image
- a374f43 [BUGFIX] Make fetching of external image configurable
- 904a8c6 [BUGFIX] check if array index exist by wrapping with null coalescing operator and set reasonable default value
- 2118c00 [BUGFIX] Preserve important image attributes on initialization
- 65bd60b [BUGFIX] Fix broken images in RTEs inside flexform elements
- 43c11c6 [BUGFIX] Fix multiple PHP 8.0 warnings
- ef9164e [BUGFIX] #126 Wrong link in image, #142 Wrong backwards compatibility
- 4b3fdd2 [BUGFIX] #112: Remove wrapping p-tag from images via TS
- c21cb85 [BUGFIX] #112: Remove wrapping p-tag from images
- 971ff69 [BUGFIX] #122: Fix jquery requirement to not crash the ckeditor
- a0289a7 [BUGFIX] Fix override detection for title/alt attributes; allow empty values for alt/title
- 42ab413 Revert "[BUGFIX] use parseFunc_RTE for processing"
- faf7060 [BUGFIX] use parseFunc_RTE for processing

## MISC

- b889d34 Fix invalid typoscript in README, comments not allowed behind values
- 636dc11 Fix license link in doc blocks to match actual LICENSE file
- eb87c47 Fix invalid subclassing of hook, use given object instance instead
- cc50d04 #217: Fix invalid return type of method "getLazyLoadingConfiguration"
- ea12133 OPS-406: Add initial security policy
- 4eb8d4e #212: Remove deprecated call to GeneralUtility::rmFromList
- 8215830 Added search params to url, if available. Necessary when filestorage is not public
- 2bb5534 Update typo3image.js
- 802f908 Fix some possible php errors
- e5a9c6f OPS-401: Create contributing guide (initial version)
- 4ddb62d Fix phpcs issues
- 0a0f100 Fix some phpstan issue, raise level to 8
- 73a603b Replace deprecated TYPO3_MODE
- 03033cb Remove assignment of"mode" as this is already passed by the root to the parent
- 1851aab Prevent undefined array key warning in ImageRenderingController
- 3d6453f Check for array key
- e62ef73 Rework SelectImageController
- 7892e82 Drop useless method
- e092ceb Clear doc blocks
- 7d74fc8 Remove unused use statement
- 046f985 Rework method to extract image width/height from attributes to return single value instead of array
- 6cdcf8b Rename soft reference parser in order to match the core structure and logic
- 2d1a136 Rework RteImagesSoftReferenceIndex to fix phpstan issues
- 3706e5b Fix returning wrong value from array
- 66fecda Add psr/log to composer.json
- c4f7f18 Fix return type of getImageAttributes
- 9f394ed Avoid duplicate is_array check
- a7449eb Remove obsolete code due previous changes
- 49f4fa8 Fix boolean comparison in if condition
- af5a301 Fix wrong variable name usage, prevents setting of lazyloading attribute
- 6060897 Fix invalid usage of short ternary operator
- 73f8247 Prevent phpstan warning "call to method of object"
- 0a1cb07 Use only boolean comparison in if conditions
- 5d7594f Prevent NULL pointer access
- db1d464 Remove redeclaration of parent class variable
- 70abb5c Avoid usage of empty, use more strict comparison
- 5de3d5d Updated composer.json, add missing TYPO3 dependencies
- 9a918d6 Reorder use statements
- 71532e8 Fix class variable types
- 86bf2a5 Fix parameters of preUserFunc calls
- 3d61050 Fix call to makeTokenID
- dd59ff6 Add missing doc blocks
- b2c08f8 Remove deprecated call to HttpUtility::setResponseCodeAndExit
- a35a65b Replace qualifier with an import
- b85d1ff Inline variable
- 29a36d1 Remove redundant arguments
- 19a64f3 Use constant from base class
- fe15c5c Add missing @throws annotation
- a07fa64 Use merge unset calls
- e4cd0f3 Rework not required switch construct
- 6d981dd Use strpos/stripos instead of substr
- a9952af Use short hand ternary operator
- 04ce0ee Add missing parent constructor call
- c6e7315 Replace qualifier with an import
- 925ec98 Add missing method return and parameter types
- 774439e Use null coalescing operator
- 3e39f1c Fix wording
- 8a009d3 Add strict_types
- 2b141e5 Fix repository name in order to display badges
- 3ebb12d Update composer.json
- 6034734 Update composer.json, add allowed plugins
- b6cc3f0 Add badges to README
- c8d579b Fix path in phpcs workflow
- a203fed Add github workflows for phpcs, phpstan and codeql
- e5702f9 #205: Drop obsolete use statement
- 5dd4ca2 #205: Add deprecation notice for hook, will be removed in TYPO3 v12
- 2164d14 #205: Fix double slash in URL path
- 76b6eb9 #205: Change order of conditions, starting with least expensive
- 1435455 #205: Move URL comparsion to separate variable for easier reading the condition
- b7d251b #205: Use strict comparison
- 5151fdc #205: Invert if condition to drop empty then part
- f38be91 #205: Drop obsolete "plainImageMode" handling
- 8972df3 #205: Fix PHP warning about undefined array key access
- 20f1150 OPS-398: Create Github issue templates (initial version)
- 73507eb Create add-to-project.yml
- 67b5ab6 set version for next release
- 1cf5854 Fix undefined array key access
- db6f864 Update install instructions
- a119120 v11.0.5
- 9395ff3 fix syntax
- 4f259b5 Update namelesscoder/typo3-repository-client requirement
- 216fb41 add alternate dev version s
- e8acb23 Create dependabot.yml
- 3a55e95 fix homepage
- 52aaf57 11.0.4
- 3f6c40d fix package replacement
- edf6d18 Version 11.0.3
- 7b3038b Update typo3image.js, Fix Pull request #137
- 5afa012 Make extension error-free on PHPStan level 8
- 9bcda48 Make extension error-free on PHPStan level 7
- eb6aa86 Make extension error-free on PHPStan level 6
- d541a2f Make extension error-free on PHPStan level 4
- 693a8d4 Make extension error-free on PHPStan level 3
- e1401e1 Make extension error-free on PHPStan level 2
- 4f928c2 Make extension error-free on PHPStan level 1
- 6894d51 Make extension error-free on PHPStan level 0
- eb2fe99 Explicitly require PHP 7.4 or newer
- 3240e37 Check for $pI['scheme'] with isset() instead
- 70655f4 Make renderImageAttributes() arguments optional
- edcd3e8 Required parameter after optional parameter is deprecated
- f862655 drop version from install instructions
- f7a246c fix version in ext_emconf.php
- 13b5c00 [bugfix/145]Fix disabled button. Solution is parsing of every toolbars, then checking if the command is 'image'. If yes, put the button state at 'off' since button.getState returns undefined
- 415a05f Update composer.json
- 97f4c0a [Task] Make installable in 11.5 via Composer
- 29947f5 TYPO3 11.5LTS support
- 7fc0013 Add MichelHolz to Contributors list
- 1a1e87f implement missing processed file resolve
- 62704d5 Added extension-key to composer.json
- 733fb9e [FEAT] #82: TYPO3 fluid_styled_content lazyload support
- 58e8b6a [REFACTOR] Add brackets
- 4968470 guard clause if jQuery is not present
- b1ba3fd Update typo3image.js
- 48b1a4a Wrapping innerText to avoid js error
- e2fe9ff [FEAT] #88: Add custom class field for each image

## Contributors

- Elías Fernández
- Francois Suter
- François Suter
- Gitsko
- J. Peter M. Schuler
- Johannes Przymusinski
- Luc MULLER
- Lukas Niestroj
- Marco Kuprat
- Mario Lubenka
- MichelHolz
- Niels Langlotz
- Norman Golatka
- Philipp
- Rico Sonntag
- Robert Kärner
- Robert Vock
- Sebastian Koschel
- Sebastian Mendel
- Stephan Ederer
- Susanne Moog
- Sybille Peters
- Thorben Nissen
- Tobias Gaertner
- dependabot[bot]
- mabocke
- mcmulman
- mcmulman
- root

# 10.1.0

## FEATURE

- 3508ce2 [FEATURE] Refactor linked image renderer (#42)
- 7d72975 [FEATURE] Render images within links via regex (#42)
- ada62e2 [FEATURE] #35: Remove empty image attributes

## TASK

- 3d6306d [TASK] Include lower TYPO3 core versions
- b686b70 [TASK] Fix extensionscanner and cleanup code
- 728ab64 [TASK] Add TYPO3 10 compatibility
- 8005c8b [TASK] #45: Update image reference index (#62)
- fd6a165 [TASK] Release 9.0.3 - Process image on first selection
- fd9af08 [TASK] Release 9.0.2 - Bugfix for image attributes
- e72714f [TASK] Update dependencies and deployment infos
- ee02bd3 [TASK] Update branch alias
- 32587ed [TASK] Compatibility with CMS 9.x (thommyhh)
- e5e651a [TASK] Release 8.7.8 - Fix DOM element count
- d0f8fe4 [TASK] Release 8.7.7 - Fix output of link content
- 2b1c88a [TASK] Release 8.7.6

## BUGFIX

- 0c754f7 [BUGFIX] Respect zoom and attrubute checkbox values
- 19e0602 [BUGFIX] #61: Respect max width and height configuration for images when saving element
- 412767b [BUGFIX] #69: Consider override checkbox for title and alt attributes
- 3161388 [BUGFIX] #69: Remove zoom attributes when checkbox is disabled
- 711e152 [BUGFIX] Allow single quotes in image attributes (#74)
- e1d46d9 [BUGFIX] Use original files width and height for ratio and max (#70)
- 5cadf04 [BUGFIX] Add IE11 support by removing arrow functions (#66)
- 465116b [BUGFIX] Use customised width and height on first selection
- eaa4c90 [BUGFIX] Prevent value null for title and alt attributes
- 7ad1bdd [BUGFIX] #25: Regenerate missing magic image on rendering images within links (#57)
- 1cb1cd2 [BUGFIX] Avoid loosing existing attributes when editing image (#54)
- 782462b [BUGFIX] Support legacy `clickenlarge` attribute for image zoom
- cf21345 [BUGFIX] Keep data attributes for zoom images
- 6e52ddf [BUGFIX] #42: Refactor linked image renderer
- 401a1d4 [BUGFIX] Keep data attributes for zoom images
- fb5ee23 [BUGFIX] `Image properties` not working inside table cell (#41)
- 3d70322 [BUGFIX] Fix DOM element count
- b70dbaa [BUGFIX] Use proper condition for empty DomDocuments
- 8de2841 [BUGFIX] Convert parsed link content to HTML encoding
- aa75060 [BUGFIX] Fix for custom popup attributes
- 0bbca15 [BUGFIX] Replace broken TS variables in popup; enable custom popup configuration
- 52d608b [BUGFIX] Add custom image link handler to get fallback image values; Remove empty style attr
- faae207 [BUGFIX] Use $.extend for compatibility with IE11
- 772548d [BUGFIX] Fix TER package replacement

## MISC

- a88d4e9 [DOC] Update Readme
- 966451c [REFACTOR] Cleanup & refactor after rebase
- fbac0e1 [REFACTOR] Get TsConfig for image rendering
- b9f49bd [REFACTOR] Update documentation and composer specs
- 911dedc Update composer.json
- 1c8428b Change $eventDispatcher to protected
- 585ab6b Add RteImagesDbHook to service.yaml
- 694857a RteImagesDbHook
- ba50233 Add old transformation
- a328851 Change _updateMetaDataProperties to updateProperties
- a8f7a1b Update README.md
- 9f5332f Add checkbox filelist, importSelection
- 58eb14a Add use EventDispatcherInterface
- 0149ac9 Add service for eventDispatcher
- 190a313 Add EventDispatcher
- 33e1b3a Remove outdated replace instruction
- 3a94223 Update requirements and branch-alias
- 8984ffd Fix deprecation log about locallang_core.xlf
- 9193b03 [FEAT] #78: Regenerate missing processed images
- 1694d7a [REFACTOR] #71: Use underscores in package name
- 0b8e1f5 [FIX] #71: Add vendor name to composer replace to prevent validation warnings
- 98eaca7 Create LICENSE
- 0e8e8eb Add another important person to authors
- 743e5ba [DOCUMENTATION] #68: Usage of config according to t3 bug
- 5ac910c [REFACTOR] #63: Use correct image path
- c57651e suggestion for reformat code (#58)
- c0a4474 [REFACTOR] #38: Process image on first selection (#55)
- 85cd35b [Fix] Update dev branch naming
- f9c47cb [REFACTOR] #29: Process backend image url savely
- 85b4ea9 Dev typo3 9.x refactor 29 backend image url (#48)
- 5b83715 Update branch alias version
- 8a15098 [REVIEW] #43: Cleanup JS
- 2ac2146 [DOCS] Add info for RTE config
- 1bd03e8 Convert indents to spaces
- 95135bd #RELEASE - Update to version 8.7.5
- 5f322af Update to version 8.7.5
- 17056f8 #BUGFIX - Add fallback for image dimensions; clean code
- c2ec5d7 Update documentation
- 6135741 Update URLs
- 12aaef7 Depend on typo3/cms-core instead of typo3/cms
- 1e8d1b8 Set current file before calling imageLinkWrap (refs #10)
- 4fbdf06 Updating version
- 25fe482 Fixed package replacee leading to wrongly inferred extension key
- d1e4274 Implemented click enlarge feature (fixes #2)
- 719eb4c Replace CKEditor image dialog with TYPO3 image dialog on image double click (fixes #6)
- 3b754ab Added installation instructions
- bbed6b8 Fixed travis build
- 1d173b8 Set current version
- 71180b4 Added travis build
- d78feec Updated docs with demo gif
- e46b9ac Fixed events on dialog obviously being removed after modal close by rerendering the dialog
- df87bb3 Added docs
- 3f4d028 Several bug fixes
- 4f0badb Added frontend image rendering
- fd49121 Implemented image selection
- 74071f2 Initial import

## Contributors

- Axel Seemann
- Ben Abbott
- Benni
- Christian Opitz
- Christian Opitz
- Erich Manser
- Florian Wessels
- MU
- Marcus Förster
- Mathias Brodala
- Mathias Uhlmann
- Nikita Hovratov
- Sebastian Mendel
- Thorben Nissen
- Thorben Nissen
- Tymoteusz Motylewski
- UNI49
- christophmellauner
- mcmulman
- prdt3e
- sascha307050

