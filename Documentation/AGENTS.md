# TYPO3 Documentation System Guide for AI Agents

This guide explains how to work with TYPO3 extension documentation for AI agents and developers.

## Documentation Structure

### Documentation/ (RST Format - Official TYPO3 Documentation)

The `Documentation/` directory contains official TYPO3 documentation in **reStructuredText (RST)** format. This documentation is:

- **Published** at https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/
- **Built** automatically by TYPO3 Intercept: https://intercept.typo3.com/admin/docs/deployments
- **Versioned** and rendered using the TYPO3 documentation theme
- **Indexed** and searchable across all TYPO3 documentation

**Key Files:**
```
Documentation/
├── Index.rst           # Main entry point
├── Introduction/       # Getting started content
├── Integration/        # Configuration guides
├── CKEditor/          # CKEditor-specific docs
├── Troubleshooting/   # Problem solving
├── Security/          # Security documentation
├── API/               # PHP API reference
└── Settings.cfg       # Documentation metadata
```

### claudedocs/ (Markdown Format - AI Session Context)

The `claudedocs/` directory contains **temporary session documentation** in Markdown format. This directory:

- **Is gitignored** - never committed to version control
- **Holds AI analysis** - architectural analysis, security audits, API references
- **Provides context** - comprehensive project knowledge for AI agents
- **Is session-specific** - generated per development session, not permanent

**Purpose:** Provide AI agents with comprehensive project context without polluting the official documentation.

### Root-Level Documentation (Project Essentials)

Root-level markdown files provide essential project information:

- `README.md` - Project overview, badges, quick start
- `CONTRIBUTING.md` - Contribution guidelines
- `SECURITY.md` - Security policy and vulnerability reporting
- `AGENTS.md` - AI development guide (you should create this)
- `LICENSE` - License information

## TYPO3 Documentation Standards

### Official Documentation Guide

**Complete Reference:** https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/Index.html

**Key Sections:**
- RST syntax and directives
- TYPO3-specific directives (confval, versionadded, php:method)
- Code examples and admonitions
- Cross-referencing and labels
- Intersphinx linking

### RST Format Basics

**Headings:**
```rst
===========
Page Title
===========

Section
=======

Subsection
----------

Subsubsection
~~~~~~~~~~~~~
```

**Code Blocks:**
```rst
.. code-block:: php

   $code = 'example';

.. code-block:: yaml

   setting: value
```

**Admonitions:**
```rst
.. important::
   Important notice

.. warning::
   Warning message

.. note::
   Additional information
```

**Cross-References:**
```rst
.. _my-label:

Section Title
=============

Link to :ref:`my-label`
```

### TYPO3-Specific Directives

**Configuration Values:**
```rst
.. confval:: settingName

   :type: boolean
   :Default: true
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['ext_key']['setting']

   Description of the configuration value.
```

**Version Information:**
```rst
.. versionadded:: 13.0.0
   Description of what was added.

.. versionchanged:: 13.1.0
   Description of what changed.

.. deprecated:: 13.2.0
   Description of what is deprecated.
```

**PHP Domain:**
```rst
.. php:method:: methodName($param)

   Description

   :param string $param: Parameter description
   :returns: Return value description
   :returntype: string
```

**Card Grids (Visual Layouts):**
```rst
.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: 📘 Title

        Card description

        ..  card-footer:: :ref:`Read more <reference>`
            :button-style: btn btn-primary stretched-link
```

## Rendering Documentation Locally

### Docker Rendering (Recommended)

**Command:**
```bash
docker run --rm -v $(pwd):/project \
  ghcr.io/typo3-documentation/render-guides:latest \
  --config=Documentation
```

**Output:** `Documentation-GENERATED-temp/Index.html`

**View:**
```bash
open Documentation-GENERATED-temp/Index.html
```

**Reference:** https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/Howto/RenderingDocs/Index.html#render-documentation-with-docker

### Validation

**Check RST Syntax:**
```bash
find Documentation -name "*.rst" -exec rst2html.py --strict {} \; > /dev/null
```

**Check Cross-References:**
- Render locally and check for warnings
- Look for "WARNING: undefined label" messages
- Verify all `:ref:` targets exist

## TYPO3 Intercept Deployment

**Intercept Dashboard:** https://intercept.typo3.com/admin/docs/deployments

**Automatic Triggers:**
- Git push to main/master branch
- New version tags (e.g., v13.0.0)
- Manual trigger via Intercept interface

**Build Process:**
1. Intercept detects commit/tag
2. Documentation is rendered using render-guides
3. Output is published to docs.typo3.org
4. Indexed for search across TYPO3 docs

**Published Manual:** https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/

## Working with Documentation/*.rst Files

### AI Agent Guidelines

**✅ DO:**
- Edit existing RST files to update content
- Add new RST files following existing structure
- Use TYPO3-specific directives (confval, versionadded, php:method)
- Include UTF-8 emoji icons in card titles for visual appeal
- Use card-grid layouts with stretched-link for consistent design
- Cross-reference using `:ref:` labels
- Render locally to verify changes before committing
- Follow TYPO3 documentation standards strictly

**❌ DON'T:**
- Create markdown files in Documentation/ (use RST only)
- Commit claudedocs/ to version control (gitignored)
- Break cross-references by renaming labels without updating links
- Use external links for internal documentation (use :ref: instead)
- Skip local rendering (always verify before commit)
- Mix documentation formats (RST in Documentation/, Markdown in claudedocs/)

### Common Tasks

**Add New Page:**
1. Create `Documentation/Section/NewPage.rst`
2. Add to parent `Index.rst` toctree
3. Add label: `.. _section-newpage:`
4. Render locally to verify
5. Commit changes

**Update Configuration:**
1. Find relevant RST file in `Integration/`
2. Add or update `.. confval::` directive
3. Include type, default, path
4. Provide clear description
5. Render and verify

**Document Version Changes:**
1. Add `.. versionadded::` or `.. versionchanged::` directive
2. Include version number
3. Describe what changed and why
4. Place near relevant content
5. Render and verify

**Fix Cross-References:**
1. Find broken reference in render warnings
2. Check if target label exists
3. Update reference or create missing label
4. Re-render to verify fix

## Example Project Reference

**TYPO3 Best Practice Extension:** https://github.com/TYPO3BestPractices/tea

This project demonstrates:
- Proper Documentation/ structure
- Complete RST documentation
- Settings.cfg configuration
- CI/CD integration
- Testing setup
- Code quality standards

Study this project to understand TYPO3 extension best practices.

## Quality Standards

**Documentation Coverage:**
- All public APIs documented
- All configuration options explained
- All features have usage examples
- Troubleshooting sections for common issues
- Security considerations documented

**RST Quality:**
- No rendering warnings
- No broken cross-references
- Valid syntax (verified with rst2html.py)
- Proper heading hierarchy
- Consistent formatting

**Content Quality:**
- Clear, concise writing
- Code examples that work
- Accurate version information
- Up-to-date screenshots/diagrams
- Proper grammar and spelling

## Resources

**Official Documentation:**
- TYPO3 Documentation Guide: https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/Index.html
- Rendering with Docker: https://docs.typo3.org/m/typo3/docs-how-to-document/main/en-us/Howto/RenderingDocs/Index.html#render-documentation-with-docker
- RST Reference: https://www.sphinx-doc.org/en/master/usage/restructuredtext/basics.html
- TYPO3 Theme: https://github.com/TYPO3-Documentation/sphinx_typo3_theme

**Project Resources:**
- Published Manual: https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/
- Intercept Deployments: https://intercept.typo3.com/admin/docs/deployments
- Example Project: https://github.com/TYPO3BestPractices/tea
- Project Repository: https://github.com/netresearch/t3x-rte_ckeditor_image

**Community:**
- TYPO3 Slack: https://typo3.slack.com/
- Extension Channel: https://typo3.slack.com/archives/ext-rte_ckeditor_image
- GitHub Discussions: https://github.com/netresearch/t3x-rte_ckeditor_image/discussions

---

**Version:** 13.0.0
**Last Updated:** 2025-10-18
**Maintained By:** Netresearch DTT GmbH
