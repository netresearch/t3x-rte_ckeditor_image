# Image support for CKEditor for TYPO3

This extension adds the TYPO3 image browser to CKEditor.\
Add issues or explore the project on [github](https://github.com/netresearch/t3x-rte_ckeditor_image).

<kbd>![](Resources/Public/Images/demo.gif?raw=true)</kbd>

- Same image handling as rtehtmlarea (magic images, usual RTE TSConfig supported)
- Image browser as usual in e.g. FAL file selector
- Dialog for changing width, height, alt and title (aspect ratio automatically maintained)

## Installation

1. Install the extension

    1. with composer from [packagist](https://packagist.org/packages/netresearch/rte-ckeditor-image)
    
        ```shell
        composer req netresearch/rte-ckeditor-image:^10.1
        ```

    2. download from [TYPO3 TER](https://extensions.typo3.org/extension/rte_ckeditor_image/)

2. Activate the extension

    1. in Extension Manager

    2. via command line

        ```shell
        ./typo3/cli_dispatch.phpsh extbase extension:install rte_ckeditor_image
        ```

3. Add a preset for rte_ckeditor or override the default one (as below):

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
    ```

4. Enable RTE config preset (e.g. `default`)

    ```
    # Page TSConfig
    RTE.default.preset = default
    ```

5. Include extension Static Template file

    1. go to Template » Info/Modify » Edit whole template record » Includes
    2. choose `CKEditor Image Support` for `Include static (from extensions)`

## Configuration

### Maximum width/height

The maximum dimensions relate to the configuration for magic images which have to be set in Page TSConfig:

```
# Page TSConfig
RTE.default.buttons.image.options.magic {
    maxWidth = 1020  # Default: 300
    maxHeight = 800  # Default: 1000
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

By default the extensions from `$TYPO3_CONF_VARS['GFX']['imagefile_ext']` are allowed. However you can override this for CKEditor by adding the following to your YAML configuration:

```yaml
editor:
  externalPlugins:
      typo3image:
        allowedExtensions: "jpg,jpeg,png"
```

## Deployment

- developed on [github](https://github.com/netresearch/t3x-rte_ckeditor_image)
- [composer repository](https://packagist.org/packages/netresearch/rte-ckeditor-image)
- automatically tested and released to TER via [travis](https://travis-ci.org/netresearch/t3x-rte_ckeditor_image)
