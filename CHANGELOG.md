# 12.0.9

## BUGFIX

- [BUGFIX] Remove declare(strict_types=1) from ext_emconf.php (TER cannot parse it)

## MISC

- [TASK] Sync TER publish workflow from main branch (fixes TER publishing)

# 12.0.8

## MISC

- [TASK] Sync TER publish workflow from main branch (fixes TER publishing)

# 12.0.7

## MISC

- [TASK] Fix TER publish workflow: update runner from ubuntu-20.04 to ubuntu-latest

# 12.0.6

## BUGFIX

- [BUGFIX] Fix Image Browser 503 error (#703): Pass ElementBrowserRegistry to parent constructor via dependency injection

## MISC

- [TASK] Add DDEV setup for TYPO3 v12 local development

# 12.0.5

## BUGFIX

- [BUGFIX] Fix image regeneration (#277): Restore getProcessedFile() in RteImagesDbHook to regenerate processed images when the source file is missing
- [BUGFIX] Fix image processing without fluid_styled_content (#287, #291): Add lib.parseFunc fallback in TypoScript for installations that don't use fluid_styled_content

## MISC

- [TASK] Add Build summary job for branch protection compatibility
- [TASK] Add auto-approve workflow for solo maintainer PR merging
- [TASK] Apply Rector rule: str_starts_with() instead of strpos()

# 12.0.4

## BUGFIX

- [BUGFIX] Fix creation of processed files for frontend pages (#285)
- [BUGFIX] Revert the change "fix package name for cms_rte_ckeditor in ext_emconf.php" (#280)

## MISC

- [TASK] Update README.md for v12 (#289)
- [TASK] Upgrade/fix test suite (#294)

# 12.0.2

## TASK

- [TASK] Update author in ext_emconf.php c474bbc
- [TASK] Update Contributors.md 97ac62c
- [TASK] Remove PHP 8.3 in testing.yml 2277e12
- [TASK]  Update ext_emconf.php for TYPO3 v12 686083f

## BUGFIX

- [BUGFIX] Fixes #186: Add timestamp to force the javascript change d6b0e6c
- [BUGFIX] Fixes #186: Inline image with link sometimes causes incorrect ordering of double 3efd3d5
- [BUGFIX] Regenerate images in backend view 56e64f8
- [BUGFIX] Fixes #244: RteImagePreviewRenderer throws warning with invalid HTML 9fb0b0e
- [BUGFIX] Fixes #242: Call to a member function count() on null 5aaef60
- [BUGFIX] Add missing property transformationKey to RteImagesDbHook 7074226
- [BUGFIX] Fix onclick event for select image modal 838610a
- [BUGFIX] Loading RTE throws PHP Runtime Deprecation Notice 4d3be6a

## MISC

- Allow inline images 0316b33
- Ignore .cache and composer.json.testing which are created during running the test suites, because they do not need to beeing tracked in the repository. 8978558
- chore: update actions to fix Node.js 16 actions are deprecated. db31a83
- Show more information in functional tests e673dcc
- fix previous test changes 300ba5d
- make test compatible with TYPO3 > v12 3f98798
- improve functional test job name ec3dfc1
- chore: exclude TYPO3 v13 + PHP 8.1 from test matrix due to 'typo3/cms-core v13.0.1 requires php ^8.2' 5cf301d
- chore: migrate composeUpdate step for supporting TYPO3 v12 as default and TYPO3 v13 ae7b4b1
- use Postgres as default DBMS in tests - it's image is smaller and the tests therefore faster 462deec
- chore: update test suite with latest PHP and TYPO3 versions a392215
- chore: give the action job a meaningful name d882e78
- chore: fix package name for cms_rte_ckeditor in ext_emconf.php 5cff197
- chore: migrate phpunit configuration bb2e525
- fix: functional tests adb9c9c
- fix: do not override TYPO3_SITE_URL 2b8c79a
- chore: fix CS 10e2bb2
- fix: validate imageSource only if not NULL 753b3fc
- fix: call to undefined method - GeneralUtility::shortMD5() was removed in TYPO3 v12 e89e844
- fix: remove access to non existant property It was removed long ago and requested configuration option does not exists anymore since TYPO3 v10 f5ce607
- chore: fix function name case a370ff7
- chore: remove dead code a096121
- fix: ensure $imageSource is valid before working with it. 41d67f8
- chore: remove obsolete phpstan ignore error patterns dc80f2e
- set GitHub codespace environment container to PHP (8.2) 3d74578
- fix #270: circumvent PHP < 7.4.4 bug with childNodes being NULL 36201ef
- Apply class to  bacbd45
- chore: coding style 962dd48
- chore: fix coding style 9f8628a
- chore: ignore camelCaps rule for hook method name 7bb3554
- chore: also update test container setup for updated runtests.sh fa54fe0
- chore: update runtests.sh from https://github.com/lolli42/enetcache/blob/main/Build/Scripts/runTests.sh 6577644
- chore: fix coding style 9e40429
- chore: update branch aliases fe9bb80
- Update typo3/testing-framework requirement from ^7.0.2 to ^8.0.7 21567a5
- TYPO3 v12 Support 1cfe7a7
- Update testing.yml 8211115
- Update phpstan.yml 2d971f8
- Update phpcs.yml dde6fdd
- add TYPO3 badges to README 9a578b5
- style: remove superfluous null check 34bd1ae
- style: fix superfluous space before closing parenthesis 000703e
- tests: fix PHP Fatal error:  Type of  testExtensionsToLoad must be array bc30427
- chore: remove superfluous is null check 9dee49b
- GH-247: Unnecessary check for "data-*-override" removed, as these are only used within Javascript to document the checkbox state. 60b55a4
- GH-112: Fix regex to find images 65663ac
- Rework ImageLinkRenderingController to match ImageRenderingController 601e732
- GH-244: Sanitize HTML 908d149
- Fix class member variable name 720933a
- Update typo3/testing-framework requirement from ^6.16.7 to ^7.0.2 f78ae0e
- bugfix: incorrect parse indexes leading to dynamic image URL not to be resolved 7307574
- OPS-461: Update TER release info 913bed4
- NRS-2875: Update var name 5153eb4
- OPS-461: Change trigger and add infos to TER upload comment d12e7fc
- OPS-461: Adapt action to our version syntax 14b96f4
- OPS-461: Create Github action for publishing to TER 8a17e56
- Fix phpstan warnings ca9d0e2
- Optimize code 721f9e4
- Fix CGL 7136225
- Do not prepend site URL to embedded image in RTE 4761fe2
- Do not prepend slash for data:image 4fb6205
- Cleanup: remove commented out code f4136ef
- Fix name of testing.yml 6c9c5c3
- Fix RteImageSoftReferenceParser 67d86b3
- Add functional tests 24321b7

# 11.0.11

## TASK

- [TASK] Configure .gitignore 20297bb
- [TASK] Extend from AbstractSoftReferenceIndexParser 64e178e
- [TASK] Implement interface instead of deprecated extension of SoftReferenceIndexParser de7f013
- [TASK] Allow installation on TYPO3 v11 a89ebe1

## BUGFIX

- [BUGFIX] Set version in github action add-to-pipeline 7bd5aa1
- [BUGFIX] generate publicUrl from originalImageFile storage f4d3741
- [BUGFIX] do not append "0" to getFileObject 1ceccac
- [BUGFIX] Clean up ext_emconf to upload manual in TER ca7d660
- [BUGFIX] fileadmin doesn't start with slash in 11LTS 7addfa7
- [BUGFIX] Prevent undefined array key 82a13eb
- [BUGFIX] fix misuse of 11LTS BE processing middleware URLs b111bca
- [BUGFIX] Catch exception when fetching external image 8f9fc92
- [BUGFIX] Make fetching of external image configurable a374f43
- [BUGFIX] check if array index exist by wrapping with null coalescing operator and set reasonable default value 904a8c6
- [BUGFIX] Preserve important image attributes on initialization 2118c00
- [BUGFIX] Fix broken images in RTEs inside flexform elements 65bd60b
- [BUGFIX] Fix multiple PHP 8.0 warnings 43c11c6
- [BUGFIX] #126 Wrong link in image, #142 Wrong backwards compatibility ef9164e
- [BUGFIX] #112: Remove wrapping p-tag from images via TS 4b3fdd2
- [BUGFIX] #112: Remove wrapping p-tag from images c21cb85
- [BUGFIX] #122: Fix jquery requirement to not crash the ckeditor 971ff69
- [BUGFIX] Fix override detection for title/alt attributes; allow empty values for alt/title a0289a7
- Revert "[BUGFIX] use parseFunc_RTE for processing" 42ab413
- [BUGFIX] use parseFunc_RTE for processing faf7060

## MISC

- OPS-461: Update TER release info 58389ac
- NRS-2875: Update var name 85f9fea
- OPS-461: Change trigger and add infos to TER upload comment 02386b2
- OPS-461: Adapt action to our version syntax f0fe8ee
- OPS-461: Create Github action for publishing to TER 586aaa5
- Fix phpstan warnings 3c54089
- Optimize code 243f1ef
- Fix CGL 6dcf4e9
- Do not prepend site URL to embedded image in RTE edff497
- Do not prepend slash for data:image 0486a61
- Fixes #56: Rework preview in backend 8377ce8
- [FEAT] #56: Override preview for ctype text; show dummy images in preview c5d053e
- Add .attributes 4040155
- Fix output of alt and title attribute when overriden 1b06623
- ensure the file processing is not deferred ed2dbd1
- Update SECURITY.md 1f95028
- Correction of typo + list indentation c4255c5
- Create pull_request_template.md 708d944
- Delete pull_request_template.md e39bad0
- Delete pull_request_template.md b20221e
- Create pull_request_template.md 62a4b88
- Create pull_request_template.md c9115fd
- [DOCS] Change readme 8024855
- Fix invalid typoscript in README, comments not allowed behind values b889d34
- Fix license link in doc blocks to match actual LICENSE file 636dc11
- Fix invalid subclassing of hook, use given object instance instead eb87c47
- #217: Fix invalid return type of method "getLazyLoadingConfiguration" cc50d04
- OPS-406: Add initial security policy ea12133
- #212: Remove deprecated call to GeneralUtility::rmFromList 4eb8d4e
- Added search params to url, if available. Necessary when filestorage is not public 8215830
- Update typo3image.js 2bb5534
- Fix some possible php errors 802f908
- OPS-401: Create contributing guide (initial version) e5a9c6f
- Fix phpcs issues 4ddb62d
- Fix some phpstan issue, raise level to 8 0a0f100
- Replace deprecated TYPO3_MODE 73a603b
- Remove assignment of"mode" as this is already passed by the root to the parent 03033cb
- Prevent undefined array key warning in ImageRenderingController 1851aab
- Check for array key 3d6453f
- Rework SelectImageController e62ef73
- Drop useless method 7892e82
- Clear doc blocks e092ceb
- Remove unused use statement 7d74fc8
- Rework method to extract image width/height from attributes to return single value instead of array 046f985
- Rename soft reference parser in order to match the core structure and logic 6cdcf8b
- Rework RteImagesSoftReferenceIndex to fix phpstan issues 2d1a136
- Fix returning wrong value from array 3706e5b
- Add psr/log to composer.json 66fecda
- Fix return type of getImageAttributes c4f7f18
- Avoid duplicate is_array check 9f394ed
- Remove obsolete code due previous changes a7449eb
- Fix boolean comparison in if condition 49f4fa8
- Fix wrong variable name usage, prevents setting of lazyloading attribute af5a301
- Fix invalid usage of short ternary operator 6060897
- Prevent phpstan warning "call to method of object" 73f8247
- Use only boolean comparison in if conditions 0a1cb07
- Prevent NULL pointer access 5d7594f
- Remove redeclaration of parent class variable db1d464
- Avoid usage of empty, use more strict comparison 70abb5c
- Updated composer.json, add missing TYPO3 dependencies 5de3d5d
- Reorder use statements 9a918d6
- Fix class variable types 71532e8
- Fix parameters of preUserFunc calls 86bf2a5
- Fix call to makeTokenID 3d61050
- Add missing doc blocks dd59ff6
- Remove deprecated call to HttpUtility::setResponseCodeAndExit b2c08f8
- Replace qualifier with an import a35a65b
- Inline variable b85d1ff
- Remove redundant arguments 29a36d1
- Use constant from base class 19a64f3
- Add missing @throws annotation fe15c5c
- Use merge unset calls a07fa64
- Rework not required switch construct e4cd0f3
- Use strpos/stripos instead of substr 6d981dd
- Use short hand ternary operator a9952af
- Add missing parent constructor call 04ce0ee
- Replace qualifier with an import c6e7315
- Add missing method return and parameter types 925ec98
- Use null coalescing operator 774439e
- Fix wording 3e39f1c
- Add strict_types 8a009d3
- Fix repository name in order to display badges 2b141e5
- Update composer.json 3ebb12d
- Update composer.json, add allowed plugins 6034734
- Add badges to README b6cc3f0
- Fix path in phpcs workflow c8d579b
- Add github workflows for phpcs, phpstan and codeql a203fed
- #205: Drop obsolete use statement e5702f9
- #205: Add deprecation notice for hook, will be removed in TYPO3 v12 5dd4ca2
- #205: Fix double slash in URL path 2164d14
- #205: Change order of conditions, starting with least expensive 76b6eb9
- #205: Move URL comparsion to separate variable for easier reading the condition 1435455
- #205: Use strict comparison b7d251b
- #205: Invert if condition to drop empty then part 5151fdc
- #205: Drop obsolete "plainImageMode" handling f38be91
- #205: Fix PHP warning about undefined array key access 8972df3
- OPS-398: Create Github issue templates (initial version) 20f1150
- Create add-to-project.yml 73507eb
- set version for next release 67b5ab6
- Fix undefined array key access 1cf5854
- Update install instructions db6f864
- v11.0.5 a119120
- fix syntax 9395ff3
- Update namelesscoder/typo3-repository-client requirement 4f259b5
- add alternate dev version s 216fb41
- Create dependabot.yml e8acb23
- fix homepage 3a55e95
- 11.0.4 52aaf57
- fix package replacement 3f6c40d
- Version 11.0.3 edf6d18
- Update typo3image.js, Fix Pull request #137 7b3038b
- Make extension error-free on PHPStan level 8 5afa012
- Make extension error-free on PHPStan level 7 9bcda48
- Make extension error-free on PHPStan level 6 eb6aa86
- Make extension error-free on PHPStan level 4 d541a2f
- Make extension error-free on PHPStan level 3 693a8d4
- Make extension error-free on PHPStan level 2 e1401e1
- Make extension error-free on PHPStan level 1 4f928c2
- Make extension error-free on PHPStan level 0 6894d51
- Explicitly require PHP 7.4 or newer eb2fe99
- Check for $pI['scheme'] with isset() instead 3240e37
- Make renderImageAttributes() arguments optional 70655f4
- Required parameter after optional parameter is deprecated edcd3e8
- drop version from install instructions f862655
- fix version in ext_emconf.php f7a246c
- [bugfix/145]Fix disabled button. Solution is parsing of every toolbars, then checking if the command is 'image'. If yes, put the button state at 'off' since button.getState returns undefined 13b5c00
- Update composer.json 415a05f
- [Task] Make installable in 11.5 via Composer 97f4c0a
- TYPO3 11.5LTS support 29947f5
- Add MichelHolz to Contributors list 7fc0013
- implement missing processed file resolve 1a1e87f
- Added extension-key to composer.json 62704d5
- [FEAT] #82: TYPO3 fluid_styled_content lazyload support 733fb9e
- [REFACTOR] Add brackets 58e8b6a
- guard clause if jQuery is not present 4968470
- Update typo3image.js b1ba3fd
- Wrapping innerText to avoid js error 48b1a4a
- [FEAT] #88: Add custom class field for each image e2fe9ff

# 10.1.0

## FEATURE

- [FEATURE] Refactor linked image renderer (#42) 3508ce2
- [FEATURE] Render images within links via regex (#42) 7d72975
- [FEATURE] #35: Remove empty image attributes ada62e2

## TASK

- [TASK] Include lower TYPO3 core versions 3d6306d
- [TASK] Fix extensionscanner and cleanup code b686b70
- [TASK] Add TYPO3 10 compatibility 728ab64
- [TASK] #45: Update image reference index (#62) 8005c8b
- [TASK] Release 9.0.3 - Process image on first selection fd6a165
- [TASK] Release 9.0.2 - Bugfix for image attributes fd9af08
- [TASK] Update dependencies and deployment infos e72714f
- [TASK] Update branch alias ee02bd3
- [TASK] Compatibility with CMS 9.x (thommyhh) 32587ed
- [TASK] Release 8.7.8 - Fix DOM element count e5e651a
- [TASK] Release 8.7.7 - Fix output of link content d0f8fe4
- [TASK] Release 8.7.6 2b1c88a

## BUGFIX

- [BUGFIX] Respect zoom and attrubute checkbox values 0c754f7
- [BUGFIX] #61: Respect max width and height configuration for images when saving element 19e0602
- [BUGFIX] #69: Consider override checkbox for title and alt attributes 412767b
- [BUGFIX] #69: Remove zoom attributes when checkbox is disabled 3161388
- [BUGFIX] Allow single quotes in image attributes (#74) 711e152
- [BUGFIX] Use original files width and height for ratio and max (#70) e1d46d9
- [BUGFIX] Add IE11 support by removing arrow functions (#66) 5cadf04
- [BUGFIX] Use customised width and height on first selection 465116b
- [BUGFIX] Prevent value null for title and alt attributes eaa4c90
- [BUGFIX] #25: Regenerate missing magic image on rendering images within links (#57) 7ad1bdd
- [BUGFIX] Avoid loosing existing attributes when editing image (#54) 1cb1cd2
- [BUGFIX] Support legacy `clickenlarge` attribute for image zoom 782462b
- [BUGFIX] Keep data attributes for zoom images cf21345
- [BUGFIX] #42: Refactor linked image renderer 6e52ddf
- [BUGFIX] Keep data attributes for zoom images 401a1d4
- [BUGFIX] `Image properties` not working inside table cell (#41) fb5ee23
- [BUGFIX] Fix DOM element count 3d70322
- [BUGFIX] Use proper condition for empty DomDocuments b70dbaa
- [BUGFIX] Convert parsed link content to HTML encoding 8de2841
- [BUGFIX] Fix for custom popup attributes aa75060
- [BUGFIX] Replace broken TS variables in popup; enable custom popup configuration 0bbca15
- [BUGFIX] Add custom image link handler to get fallback image values; Remove empty style attr 52d608b
- [BUGFIX] Use $.extend for compatibility with IE11 faae207
- [BUGFIX] Fix TER package replacement 772548d

## MISC

- [DOC] Update Readme a88d4e9
- [REFACTOR] Cleanup & refactor after rebase 966451c
- [REFACTOR] Get TsConfig for image rendering fbac0e1
- [REFACTOR] Update documentation and composer specs b9f49bd
- Update composer.json 911dedc
- Change $eventDispatcher to protected 1c8428b
- Add RteImagesDbHook to service.yaml 585ab6b
- RteImagesDbHook 694857a
- Add old transformation ba50233
- Change _updateMetaDataProperties to updateProperties a328851
- Update README.md a8f7a1b
- Add checkbox filelist, importSelection 9f5332f
- Add use EventDispatcherInterface 58eb14a
- Add service for eventDispatcher 0149ac9
- Add EventDispatcher 190a313
- Remove outdated replace instruction 33e1b3a
- Update requirements and branch-alias 3a94223
- Fix deprecation log about locallang_core.xlf 8984ffd
- [FEAT] #78: Regenerate missing processed images 9193b03
- [REFACTOR] #71: Use underscores in package name 1694d7a
- [FIX] #71: Add vendor name to composer replace to prevent validation warnings 0b8e1f5
- Create LICENSE 98eaca7
- Add another important person to authors 0e8e8eb
- [DOCUMENTATION] #68: Usage of config according to t3 bug 743e5ba
- [REFACTOR] #63: Use correct image path 5ac910c
- suggestion for reformat code (#58) c57651e
- [REFACTOR] #38: Process image on first selection (#55) c0a4474
- [Fix] Update dev branch naming 85cd35b
- [REFACTOR] #29: Process backend image url savely f9c47cb
- Dev typo3 9.x refactor 29 backend image url (#48) 85b4ea9
- Update branch alias version 5b83715
- [REVIEW] #43: Cleanup JS 8a15098
- [DOCS] Add info for RTE config 2ac2146
- Convert indents to spaces 1bd03e8
- #RELEASE - Update to version 8.7.5 95135bd
- Update to version 8.7.5 5f322af
- #BUGFIX - Add fallback for image dimensions; clean code 17056f8
- Update documentation c2ec5d7
- Update URLs 6135741
- Depend on typo3/cms-core instead of typo3/cms 12aaef7
- Set current file before calling imageLinkWrap (refs #10) 1e8d1b8
- Updating version 4fbdf06
- Fixed package replacee leading to wrongly inferred extension key 25fe482
- Implemented click enlarge feature (fixes #2) d1e4274
- Replace CKEditor image dialog with TYPO3 image dialog on image double click (fixes #6) 719eb4c
- Added installation instructions 3b754ab
- Fixed travis build bbed6b8
- Set current version 1d173b8
- Added travis build 71180b4
- Updated docs with demo gif d78feec
- Fixed events on dialog obviously being removed after modal close by rerendering the dialog e46b9ac
- Added docs df87bb3
- Several bug fixes 3f4d028
- Added frontend image rendering 4f0badb
- Implemented image selection fd49121
- Initial import 74071f2

