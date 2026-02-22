<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-02-22 -->

# AGENTS.md -- Documentation

## Overview

TYPO3 extension documentation in RST format for rendering on docs.typo3.org.
Includes architecture decision records (ADRs), API reference, CKEditor plugin docs, and integration examples.

## Structure

```
Documentation/
  Index.rst                      -- Main entry point (required by docs.typo3.org)
  Settings.cfg                   -- Sphinx/guides configuration (required)
  Includes.rst.txt               -- Shared RST includes
  guides.xml                     -- TYPO3 documentation rendering config
  Introduction/Index.rst         -- Extension overview and features
  Integration/                   -- Installation and setup guides
  Examples/                      -- Usage examples and recipes
    Basic-Integration.rst          Basic setup
    Template-Overrides.rst         Custom Fluid template overrides
    Linked-Images.rst              Link handling patterns
    Responsive-Images.rst          Responsive image configuration
    Image-Styles.rst               Alignment and styling
    Advanced-Features.rst          Quality scaling, popups, etc.
    Custom-Extensions.rst          Extending the extension
    Testing.rst                    Testing guide
  CKEditor/                     -- CKEditor 5 plugin documentation
    Plugin-Development.rst         Plugin architecture and development
    Conversions.rst                Upcast/downcast converter docs
    Model-Element.rst              CKEditor model/schema
    Image-Quality-Selector.rst     Quality dropdown feature
    Style-Integration.rst          CKEditor style integration
  API/                           -- PHP API reference
    Controllers.rst                Controller documentation
    Services.rst                   Service layer documentation
    DTOs.rst                       Data transfer objects
    DataHandling.rst               DataHandler hooks
    EventListeners.rst             Event listener documentation
  Architecture/                  -- Architecture Decision Records
    System-Architecture.rst        Overall system design
    ADR-001-Image-Scaling.rst      Image scaling strategy
    ADR-002-CKEditor-Integration.rst  CKEditor plugin integration
    ADR-003-Security-Responsibility-Boundaries.rst  Security boundaries
    Design-Patterns.rst            Patterns used in the codebase
    Migration-Guide-v14.md         TYPO3 v14 migration notes
    Security-Validation-Checklist.md  Security review checklist
    RFC-Fluid-Templates-Refactoring.md  Template refactoring proposal
  Troubleshooting/               -- Common issues and solutions
    Index.rst                      Troubleshooting overview
    Editor-Issues.rst              CKEditor editing issues and resolved fixes
    Frontend-Issues.rst            Frontend rendering problems
    Installation-Issues.rst        Installation and setup issues
    Performance-Issues.rst         Performance optimization
    Image-Reference-Validation.rst CLI validator and upgrade wizard (6 issue types)
  Contributing/Index.rst         -- Contribution guidelines
  Images/                        -- Screenshots and diagrams
```

## Rendering Docs

| Task | Command |
|------|---------|
| Render locally | `make docs` (via ddev) or `docker run --rm -v $(pwd):/project ghcr.io/typo3-documentation/render-guides:latest` |
| Preview | Open `Documentation-GENERATED-temp/Index.html` |
| Clean | `rm -rf Documentation-GENERATED-temp/` |
| Lint docs | `make docs-lint` or `./Build/Scripts/validate-docs.sh` |
| Fix docs | `make docs-fix` or `./Build/Scripts/validate-docs.sh --fix` |

## RST Conventions

- **Format**: RST (reStructuredText), NOT Markdown (except ADRs/RFCs in Architecture/)
- **Headings**: `=` for H1, `-` for H2, `~` for H3, `^` for H4
- **Line length**: ~80 characters for readability
- **One sentence per line** (for better git diffs)
- **Admonitions**: `.. note::`, `.. warning::`, `.. tip::`, `.. important::`
- **Code blocks**: `.. code-block:: php` or `.. literalinclude::`
- **Cross-references**: `:ref:` with proper labels
- **Tables**: use `.. t3-field-list-table::` or grid tables

## TYPO3 Documentation Directives

- `.. confval::` for configuration values
- `.. versionadded::` for new features
- `.. versionchanged::` for changed behavior in a version
- `.. deprecated::` for deprecation notices
- `.. t3-field-list-table::` for TYPO3-style tables
- `.. figure::` with `:alt:` and `:zoom: lightbox` for screenshots

## Screenshots

- Format: PNG only
- Location: `Documentation/Images/`
- Always include `:alt:` text and `:zoom: lightbox`
- Use `:class: with-border with-shadow` for UI screenshots

```rst
.. figure:: /Images/Configuration/ExtensionSettings.png
   :alt: Extension configuration showing image quality options
   :zoom: lightbox
   :class: with-border with-shadow

   Configure the extension in Admin Tools > Settings
```

## PR Checklist

- [ ] RST syntax valid (renders without errors)
- [ ] `make docs-lint` passes
- [ ] All internal links resolve
- [ ] Images have `:alt:` text and `:zoom: lightbox`
- [ ] Code examples are tested and correct
- [ ] Follows docs.typo3.org structure
- [ ] New features have corresponding documentation
