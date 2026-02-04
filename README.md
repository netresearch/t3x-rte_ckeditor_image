[![Latest version](https://img.shields.io/github/v/release/netresearch/t3x-rte_ckeditor_image?sort=semver)](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/latest)
[![License](https://img.shields.io/github/license/netresearch/t3x-rte_ckeditor_image)](https://github.com/netresearch/t3x-rte_ckeditor_image/blob/main/LICENSE)
[![CI](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/ci.yml/badge.svg)](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/ci.yml)
[![CodeQL](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/codeql-analysis.yml/badge.svg)](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/codeql-analysis.yml)
[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/netresearch/t3x-rte_ckeditor_image/badge)](https://securityscorecards.dev/viewer/?uri=github.com/netresearch/t3x-rte_ckeditor_image)
[![OpenSSF Best Practices](https://www.bestpractices.dev/projects/11718/badge)](https://www.bestpractices.dev/projects/11718)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-brightgreen.svg)](https://phpstan.org/)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-3.0-4baaaa.svg)](CODE_OF_CONDUCT.md)
[![codecov](https://codecov.io/gh/netresearch/t3x-rte_ckeditor_image/graph/badge.svg)](https://codecov.io/gh/netresearch/t3x-rte_ckeditor_image)
[![SLSA 3](https://slsa.dev/images/gh-badge-level3.svg)](https://slsa.dev)

![Composer](https://typo3-badges.dev/badge/rte_ckeditor_image/composer/shields.svg)
![Total downloads](https://typo3-badges.dev/badge/rte_ckeditor_image/downloads/shields.svg)
![TYPO3 extension](https://typo3-badges.dev/badge/rte_ckeditor_image/extension/shields.svg)
![Stability](https://typo3-badges.dev/badge/rte_ckeditor_image/stability/shields.svg)
![TYPO3 versions](https://typo3-badges.dev/badge/rte_ckeditor_image/typo3/shields.svg)
![Latest version](https://typo3-badges.dev/badge/rte_ckeditor_image/version/shields.svg)
<!-- Generated with 🧡 at typo3-badges.dev -->

# Image support for CKEditor for TYPO3

This extension adds comprehensive image handling capabilities to CKEditor for TYPO3.\
Add issues or explore the project on [GitHub](https://github.com/netresearch/t3x-rte_ckeditor_image).

<kbd>![](Resources/Public/Images/demo.gif?raw=true)</kbd>

## Features

- **TYPO3 FAL Integration**: Native file browser with full File Abstraction Layer support
- **Magic Images**: Same image processing as rtehtmlarea (cropping, scaling, TSConfig supported)
- **Image Dialog**: Configure width, height, alt, and title (aspect ratio automatically maintained)
- **Quality Selector**: Quality multipliers for optimal display (Standard 1.0x, Retina 2.0x, Ultra 3.0x, Print 6.0x)
- **SVG Support**: Intelligent dimension extraction from viewBox and width/height attributes
- **Custom Styles**: Configurable image styles with CKEditor 5 style system
- **Inline Images**: True inline image support with cursor positioning before/after (new in 13.6)
- **Lazy Loading**: TYPO3 native browser lazyload support
- **Event-Driven**: PSR-14 events for extensibility
- **Security**: Protocol blocking, XSS prevention, file visibility validation
- **Fluid Templates**: Customizable output via template overrides

## Requirements

- **TYPO3:** 13.4.21+ or 14.0+
- **PHP:** 8.2 or later
- **Extensions:** cms-rte-ckeditor (included in TYPO3 core)

> **Note:** The plugin automatically integrates with CKEditor's `GeneralHtmlSupport` for style functionality. No additional configuration required.

## Installation

### Quick Start

Install the extension via composer:

```shell
composer req netresearch/rte-ckeditor-image
```

The backend RTE works immediately. For frontend rendering, include the TypoScript:

> **Important (v13.4.0+):** TypoScript is no longer auto-injected. You must include it manually using one of the options below.

**Option 1: Static Template (Recommended)**

1. Go to **WEB > Template** module
2. Select your root page, edit the template
3. In **Includes** tab, add: **CKEditor Image Support (rte_ckeditor_image)**

**Option 2: Direct Import**

Add to your site package TypoScript:

```typoscript
@import 'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript'
```

This gives you full control over TypoScript load order, allowing you to override settings (like lightbox configuration) after the import.

### Custom Configuration (Optional)

If you need to customize the RTE configuration or create your own preset:

1. Create a custom preset in your site extension:

    ```php
    <?php
    // EXT:my_ext/ext_localconf.php
    $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['my_custom_preset']
        = 'EXT:my_ext/Configuration/RTE/Default.yaml';
    ```

2. Import the image plugin configuration:

    ```yaml
    # EXT:my_ext/Configuration/RTE/Default.yaml
    imports:
      - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
      - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

    editor:
      config:
        toolbar:
          items:
            - heading
            - '|'
            - insertimage
            - link
            - '|'
            - bold
            - italic
    ```

3. Enable your custom preset via Page TSConfig:

    ```
    # Page TSConfig
    RTE.default.preset = my_custom_preset
    ``` 

## Configuration

(optional) Configure the Extension Configuration for this extension:

**fetchExternalImages**: By default, if an img source is an external URL, this image will be fetched and uploaded
to the current BE users uploads folder. The default behaviour can be turned off with this option.

### Maximum width/height

The maximum dimensions relate to the configuration for magic images which have to be set in Page TSConfig:

```
# Page TSConfig
RTE.default.buttons.image.options.magic {
    # Default: 300
    maxWidth = 1020
    # Default: 1000
    maxHeight = 800
}
```

If TSConfig settings don't render correctly from custom template extensions, add the settings directly to root page configuration.


### Usage as lightbox with fluid_styled_content

```
# Template Constants
styles.content.textmedia.linkWrap.lightboxEnabled = 1
```

### Configure a default css class for every image

```
# TS Setup

lib.parseFunc_RTE {
    // default class for images in bodytext:
    nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib.class {
      default = my-custom-class
    }
}
```

### Image lazyload support

The extension supports [TYPO3 lazyload handling](https://docs.typo3.org/c/typo3/cms-core/master/en-us/Changelog/10.3/Feature-90426-Browser-nativeLazyLoadingForImages.html) (fluid_styled_content) for native browser lazyload.

```
# Template Constants type=options[lazy,eager,auto]
styles.content.image.lazyLoading = lazy
```

### Image Quality Selector

The image dialog includes a quality selector dropdown for optimal image processing:

**Quality Options:**
- **No Scaling** (1.0x) - Original file, no processing (best for newsletters, PDFs, SVG files)
- **Standard** (1.0x) - Match display dimensions exactly
- **Retina** (2.0x) - High-DPI displays (default, recommended for modern devices)
- **Ultra** (3.0x) - Very sharp images for hero images and key visuals
- **Print** (6.0x) - Print-quality output and professional photography

Quality selection persists via `data-quality` HTML attribute. The selector automatically handles SVG dimension extraction from viewBox or width/height attributes.

**See:** [Image Quality Selector Documentation](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/CKEditor/Image-Quality-Selector.html) for complete technical details, use cases, and migration guide.

### Using original images without processing (noScale)

Configure noScale globally via TypoScript to skip image processing:

```typoscript
# TypoScript Setup - Enable globally for all RTE images
lib.parseFunc_RTE.tags.img.noScale = 1

# Optional: Set file size threshold for auto-optimization
lib.parseFunc_RTE.tags.img.noScale {
    maxFileSizeForAuto = 2000000  # 2MB - prevents serving very large originals
}
```

**Modern Approach:** Use the quality selector dropdown in the image dialog for per-image control. The `data-quality="none"` attribute provides the same functionality as `noScale = 1` with better user experience.

**Auto-Optimization:** The extension automatically skips processing when:
- Requested dimensions match the original image dimensions
- SVG files are detected (vector graphics always use original)
- Quality selector is set to "No Scaling"

### Allowed extensions

By default, the extensions from `$TYPO3_CONF_VARS['GFX']['imagefile_ext']` are allowed. However, you can override this for CKEditor by adding the following to your YAML configuration:

```yaml
editor:
  externalPlugins:
      typo3image:
        allowedExtensions: "jpg,jpeg,png"
```

## Documentation

This project maintains three documentation tiers:

### Official TYPO3 Documentation

**Published Manual:** https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/

For integrators, administrators, and end users. Covers installation, configuration, troubleshooting, and usage.

**Source:** [Documentation/](Documentation/) (RST format, automatically built and published)

### AI Development Context

**AI Agents & Developers:** `claudedocs/` directory (gitignored)

Technical knowledge base for AI-assisted development. Not tracked in git - generate per session if needed.

### Project Essentials

- **[AGENTS.md](AGENTS.md)** - AI development guide and build commands
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Contribution guidelines
- **[SECURITY.md](SECURITY.md)** - Security policy
- **[Documentation/AGENTS.md](Documentation/AGENTS.md)** - TYPO3 documentation system guide

## Development

### DDEV Environment (Complete Testing Setup)

```bash
# Quick start with DDEV (includes TYPO3 + Bootstrap Package)
git clone https://github.com/netresearch/t3x-rte_ckeditor_image.git
cd t3x-rte_ckeditor_image
make up                      # Start DDEV + complete setup (ONE COMMAND!)

# Access your environment
# - Overview:       https://rte-ckeditor-image.ddev.site/
# - Documentation:  https://docs.rte-ckeditor-image.ddev.site/
# - TYPO3 v13:      https://v13.rte-ckeditor-image.ddev.site/
# - TYPO3 v14:      https://v14.rte-ckeditor-image.ddev.site/
# - Backend:        [version].rte-ckeditor-image.ddev.site/typo3/
# - Credentials:    admin / Password:joh316

# Individual commands
make start                   # Start DDEV environment
make setup                   # Complete setup (docs + install)
make docs                    # Render extension documentation
```

**Included Packages:**
- **Bootstrap Package** (v15.0+) - Automatically installed to provide frontend rendering infrastructure
- **TYPO3 Styleguide** - UI pattern reference for testing
- All packages pre-configured for immediate testing of:
  - Image insertion and editing in RTE
  - Click-to-enlarge functionality on frontend
  - Caption editing (WYSIWYG mode)
  - Image alignment and styling

### Local Development (No DDEV)

```bash
# Quick start
composer install
make help                    # See all available targets

# Development workflow
make lint                    # Run all linters
make format                  # Fix code style
make test                    # Run tests
make ci                      # Full CI check (pre-commit)
```

See [AGENTS.md](AGENTS.md) for complete development guide, code standards, and PR checklist.

## Deployment

- Developed on [GitHub](https://github.com/netresearch/t3x-rte_ckeditor_image)
- [Composer repository](https://packagist.org/packages/netresearch/rte-ckeditor-image)
- [TYPO3 Extension Repository](https://extensions.typo3.org/extension/rte_ckeditor_image)
- New versions automatically uploaded to TER via GitHub Action when creating a release

## About

Developed and maintained by [Netresearch DTT GmbH](https://www.netresearch.de/).
