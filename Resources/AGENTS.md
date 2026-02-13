<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-02-13 -->

# AGENTS.md -- Resources

## Overview

Fluid templates for image rendering, XLIFF translation files (32 languages), CKEditor 5 plugin JavaScript, and CSS for editor styling.

## Directory Structure

```
Resources/
  Private/
    Language/                  -- XLIFF translation files
      locallang_be.xlf           Base English translations
      de.locallang_be.xlf        German (+ 30 other language files)
    Templates/Image/           -- Fluid templates for rendering contexts
      Standalone.html             Bare <img> tag (no wrapper)
      WithCaption.html            <figure><img><figcaption> wrapper
      Link.html                   <a><img></a> linked image
      LinkWithCaption.html        <figure><a><img></a><figcaption></figure>
      Popup.html                  Popup/lightbox link with <img>
      PopupWithCaption.html       Popup with <figure> caption wrapper
    Partials/Image/            -- Fluid partials (reusable fragments)
      Tag.html                    Core <img> tag partial
      TagInFigure.html            <img> tag variant for figure context
      Figure.html                 <figure> wrapper partial
      Link.html                   <a> link wrapper partial
  Public/
    JavaScript/Plugins/
      typo3image.js              CKEditor 5 plugin (image dialog, insertion, upcast/downcast)
    Css/
      editor-image-widget.css    CKEditor image widget styling
      image-alignment.css        Image alignment classes (image-left, image-center, image-right)
    Icons/
      Extension.svg              Extension icon (SVG)
      Extension.png              Extension icon (PNG fallback)
    Images/
      demo.gif                   Demo animation for README
```

## Key Files

| File | Purpose |
|------|---------|
| `Private/Templates/Image/*.html` | 6 Fluid templates selected by `ImageRenderingService::selectTemplate()` |
| `Private/Partials/Image/Tag.html` | Core `<img>` tag with all attributes (src, width, height, alt, title, class, loading) |
| `Private/Partials/Image/Figure.html` | `<figure>` wrapper with optional figureClass and `<figcaption>` |
| `Private/Partials/Image/Link.html` | `<a>` wrapper with href, target, class, popup JS config |
| `Public/JavaScript/Plugins/typo3image.js` | CKEditor 5 plugin: image dialog, FAL integration, upcast/downcast converters |
| `Public/Css/image-alignment.css` | CSS classes: `.image-left`, `.image-center`, `.image-right` |
| `Private/Language/locallang_be.xlf` | Base XLIFF translations for backend labels |

## Template Selection Logic

`ImageRenderingService::selectTemplate()` uses `match(true)` with priority order:

1. **PopupWithCaption** -- isPopup AND hasCaption
2. **Popup** -- isPopup (no caption)
3. **LinkWithCaption** -- hasLink AND hasCaption
4. **Link** -- hasLink (no caption)
5. **WithCaption** -- hasCaption only
6. **Standalone** -- default (no link, no caption)

Figure wrappers are only created when there is a caption. Alignment classes without caption go directly on the `<img>` element.

## Template Override Mechanism

Integrators can override templates via TypoScript:

```typoscript
lib.parseFunc_RTE.tags.img {
    settings.templateRootPaths {
        10 = EXT:my_sitepackage/Resources/Private/Templates/
    }
    settings.partialRootPaths {
        10 = EXT:my_sitepackage/Resources/Private/Partials/
    }
}
```

Default paths at priority 0 are always preserved. Custom paths use numeric keys > 0.

## Translation Files

- 32 language files following TYPO3 XLIFF standard
- Base file: `locallang_be.xlf` (English)
- Managed via Crowdin (see `crowdin.yml` in project root)
- Validate with: `bash Build/Scripts/validate-xliff.sh`
- Technical terms like "Retina", "Ultra", "Standard" may be kept as-is in translations

## CKEditor 5 Plugin

`Public/JavaScript/Plugins/typo3image.js` provides:
- Image insertion dialog (FAL file browser integration)
- Image attribute editing (width, height, alt, title, quality, alignment)
- Override checkboxes for alt/title (per-element vs. FAL metadata)
- Click-to-enlarge / zoom toggle
- Upcast/downcast converters for CKEditor model <-> HTML
- Integration with TYPO3 link browser

## Conventions

- Templates use Fluid syntax with `<f:` namespace and `{image.property}` variable access
- All template variables come from `ImageRenderingDto` assigned as `{image}`
- Whitespace in templates is normalized by `ImageRenderingService::render()` to prevent parseFunc artifacts
- XLIFF files must pass `Build/Scripts/validate-xliff.sh` validation
- Images in `Public/Images/` should be optimized before committing
- CSS uses standard TYPO3 class naming conventions

## PR/Commit Checklist

- [ ] Fluid templates render correctly for all 6 template contexts
- [ ] XLIFF files pass validation: `bash Build/Scripts/validate-xliff.sh`
- [ ] Template changes tested with functional tests (`PartialPathResolutionTest`, etc.)
- [ ] CKEditor plugin changes tested with E2E and JavaScript unit tests
- [ ] No sensitive data in resource files
- [ ] Images are optimized (compressed, correct dimensions)
