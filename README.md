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

This extension adds the TYPO3 image browser to CKEditor.\
Add issues or explore the project on [GitHub](https://github.com/netresearch/t3x-rte_ckeditor_image).

<kbd>![](Resources/Public/Images/demo.gif?raw=true)</kbd>

- Same image handling as rtehtmlarea (magic images, usual RTE TSConfig supported)
- Image browser as usual in e.g. FAL file selector
- Dialog for changing width, height, alt and title (aspect ratio automatically maintained)

## Installation

1. Install the extension

    1. with composer from [packagist](https://packagist.org/packages/netresearch/rte-ckeditor-image)

        ```shell
        composer req netresearch/rte-ckeditor-image
        ```
2. Add a preset for rte_ckeditor or override the default one (as below):

    ```php
    <?php
    // EXT:my_ext/ext_localconf.php`
    $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['default'] = 'EXT:my_ext/Configuration/RTE/Default.yaml';
    ```

    ```yaml
    # EXT:my_ext/Configuration/RTE/Default.yaml
    imports:
      # Import default RTE config (for example)
      - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
      # Import the image plugin configuration
      - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

    editor:
      config:
        # RTE default config removes image plugin - restore it:
        removePlugins: null
        toolbar:
          items:
            - '|'
            - insertImage
    ```

4. Enable RTE config preset (e.g. `default`)

    ```
    # Page TSConfig
    RTE.default.preset = default
    ```

5. Include extension Static Template file

    1. go to Template » Info/Modify » Edit whole template record » Includes
    2. choose `CKEditor Image Support` for `Include static (from extensions)` before the Fluid Styled content 

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

## Deployment

- developed on [GitHub](https://github.com/netresearch/t3x-rte_ckeditor_image)
- [composer repository](https://packagist.org/packages/netresearch/rte-ckeditor-image)
- new version will automatically be uploaded to TER via Github Action when creating a new Github release
