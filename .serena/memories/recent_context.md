# Recent Context & Bug Fixes

## Current State (v13.0.0)
- TYPO3 13.4 compatibility
- PHP 8.2-8.9
- CKEditor 5 integration complete
- Style system fully functional

## Recent Critical Bug Fixes

### Style System Integration Issues (Commits 37c641c, b477ab5, 43c9303)

#### Problem
Style drop-down not working with typo3image elements. When user selected an image and tried to apply styles from the drop-down, nothing happened.

#### Root Cause
Missing integration between CKEditor's style system and custom `typo3image` model element. The style system only recognized native `<img>` elements, not our custom model.

#### Solution
Added `GeneralHtmlSupport` to required plugins and implemented three style event listeners:
1. `isStyleEnabledForBlock` - Enables img styles when typo3image selected
2. `isStyleActiveForBlock` - Checks if style is currently applied
3. `getAffectedBlocks` - Returns correct model element for operations

Also added `GeneralHtmlSupport` integration:
1. `addModelHtmlClass` - Updates class attribute on model
2. `removeModelHtmlClass` - Removes class attribute from model

#### Commit Details
- 43c9303: `[BUGFIX] Add GeneralHtmlSupport to required plugins`
- b477ab5: `[BUGFIX] Make changing styles work`
- 37c641c: `[BUGFIX] Make style drop-down work with images`

### JavaScript Updates (Commits 565159a, f03165f)
Multiple updates to typo3image.js for style system compatibility and attribute handling improvements.

## Known Patterns & Design Decisions

### Magic Images
TYPO3's automatic image processing system:
- Backend creates processed variants
- Configuration: `RTE.default.buttons.image.options.magic.maxWidth/maxHeight`
- Default project limits: maxWidth=1920, maxHeight=9999

### Data Attributes Strategy
Internal attributes prefixed with `data-htmlarea-*`:
- `data-htmlarea-file-uid` - FAL file reference
- `data-htmlarea-file-table` - Table name
- `data-htmlarea-zoom` - Zoom functionality flag
- `data-title-override`, `data-alt-override` - Override flags

These are removed in frontend via TypoScript HTMLparser.

### Override Checkboxes Pattern
For title/alt attributes:
- If unchecked: Uses image's original metadata
- If checked: Allows custom value (can be empty string)
- Enables per-image customization while preserving metadata defaults

## Current Focus Areas
- CKEditor 5 compatibility refinements
- Style system stability
- TYPO3 13.4 integration
- Code quality (PHPStan level 6, PHP-CS-Fixer compliance)

## Git Status (Session Start)
```
Current branch: main
Status: ?? .beads/

Recent commits:
565159a Update Resources/Public/JavaScript/Plugins/typo3image.js
f03165f Update Resources/Public/JavaScript/Plugins/typo3image.js
43c9303 [BUGFIX] Add GeneralHtmlSupport to required plugins
b477ab5 [BUGFIX] Make changing styles work
37c641c [BUGFIX] Make style drop-down work with images
```

## Testing Strategy
Uses TYPO3 testing framework (typo3/testing-framework ^8.0 || ^9.0):
- Functional tests in `Tests/Functional/`
- Focus on data handling and soft reference parsing
- Test command: Part of `composer ci:test`