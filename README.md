[![Latest version](https://img.shields.io/github/v/release/netresearch/t3x-rte_ckeditor_image?sort=semver)](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/latest)
[![License](https://img.shields.io/github/license/netresearch/t3x-rte_ckeditor_image)](https://github.com/netresearch/t3x-rte_ckeditor_image/blob/main/LICENSE)
[![CI](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/ci.yml/badge.svg)](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/ci.yml)
![CodeQL](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/codeql-analysis.yml/badge.svg)

![Total downloads](https://typo3-badges.dev/badge/rte_ckeditor_image/downloads/shields.svg)
![TYPO3 extension](https://typo3-badges.dev/badge/rte_ckeditor_image/extension/shields.svg)
![Stability](https://typo3-badges.dev/badge/rte_ckeditor_image/stability/shields.svg)
![TYPO3 versions](https://typo3-badges.dev/badge/rte_ckeditor_image/typo3/shields.svg)
![Verified state](https://typo3-badges.dev/badge/rte_ckeditor_image/verified/shields.svg)
![Latest version](https://typo3-badges.dev/badge/rte_ckeditor_image/version/shields.svg)
<!-- Generated with 🧡 at typo3-badges.dev -->

# Image support for CKEditor for TYPO3

**Version 13.0.x** for TYPO3 13.4+
**License:** AGPL-3.0-or-later

This extension adds comprehensive image handling capabilities to CKEditor for TYPO3.\
Add issues or explore the project on [GitHub](https://github.com/netresearch/t3x-rte_ckeditor_image).

<kbd>![](Resources/Public/Images/demo.gif?raw=true)</kbd>

## Features

- **TYPO3 FAL Integration**: Native file browser with full File Abstraction Layer support
- **Magic Images**: Same image processing as rtehtmlarea (cropping, scaling, TSConfig supported)
- **Image Dialog**: Configure width, height, alt, and title (aspect ratio automatically maintained)
- **Custom Styles**: Configurable image styles with CKEditor 5 style system
- **Lazy Loading**: TYPO3 native browser lazyload support
- **Event-Driven**: PSR-14 events for extensibility

## Requirements

- **TYPO3:** 13.4 or later
- **PHP:** 8.2, 8.3, or 8.4
- **Extensions:** cms-rte-ckeditor (included in TYPO3 core)

### Critical Dependencies (v13.0.0+)

The CKEditor plugin requires these dependencies for style functionality:

```yaml
# In your RTE YAML configuration
importModules:
  - '@ckeditor/ckeditor5-html-support'
```

**Important:** Missing `GeneralHtmlSupport` will disable the style dropdown for images. See [Documentation](Documentation/Index.md) for details.

## Installation

### Quick Start

1. Install the extension via composer:

    ```shell
    composer req netresearch/rte-ckeditor-image
    ```

2. Include the Static Template file:
    - Go to **Template » Info/Modify » Edit whole template record » Includes**
    - Choose `CKEditor Image Support` for `Include static (from extensions)` before Fluid Styled content

**That's it!** The extension automatically registers the `rteWithImages` preset and configures it globally for all sites.

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

Current versions of TYPO3 won't render TSConfig settings correctly out of custom template extensions (see the corresponding T3 bug: https://forge.typo3.org/issues/87068).
In this case just add the settings to root page config.


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

**AI Agents & Developers:** [claudedocs/INDEX.md](claudedocs/INDEX.md)

Comprehensive technical knowledge base including:
- [Architecture & Design](claudedocs/ARCHITECTURE.md) - System design, components, security
- [API Reference](claudedocs/API_REFERENCE.md) - Complete PHP API documentation
- [Development Guide](claudedocs/DEVELOPMENT_GUIDE.md) - Workflow, standards, testing
- [Security Analysis](claudedocs/SECURITY.md) - Threat model, protections, best practices

**Note:** `claudedocs/` is gitignored - generated per session for AI context only.

### Project Essentials

- **[AGENTS.md](AGENTS.md)** - AI development guide and build commands
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Contribution guidelines
- **[SECURITY.md](SECURITY.md)** - Security policy
- **[Documentation/AGENTS.md](Documentation/AGENTS.md)** - TYPO3 documentation system guide

## Development

### DDEV Environment (Complete Testing Setup)

```bash
# Quick start with DDEV (includes TYPO3 + Introduction package)
git clone https://github.com/netresearch/t3x-rte_ckeditor_image.git
cd t3x-rte_ckeditor_image
make up                      # Start DDEV + complete setup (ONE COMMAND!)

# Access your environment
# - Overview:       https://rte-ckeditor-image.ddev.site/
# - Documentation:  https://docs.rte-ckeditor-image.ddev.site/
# - TYPO3 Frontend: https://v13.rte-ckeditor-image.ddev.site/
# - TYPO3 Backend:  https://v13.rte-ckeditor-image.ddev.site/typo3/
# - Credentials:    admin / Password:joh316

# Individual commands
make start                   # Start DDEV environment
make setup                   # Complete setup (docs + install + configure)
make docs                    # Render extension documentation
ddev configure-rte           # Configure RTE extension in TYPO3
```

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
