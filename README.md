# Image support for CKEditor for TYPO3

This extension adds the TYPO3 image browser to CKEditor.

<kbd>![](Resources/Public/Images/demo.gif?raw=true)</kbd>

- Same image handling as rtehtmlarea (magic images, usual RTE TSConfig supported)
- Image browser as usual in e.g. FAL file selector
- Dialog for changing width, height, alt and title (aspect ratio automatically maintained)

## Installation

1. Install the extension

    1. with composer from [packagist](https://packagist.org/packages/netresearch/rte-ckeditor-image)
    
        ```shell
        composer require netresearch/rte-ckeditor-image
        ```

    2. with composer from [TYPO3 TER composer repository](https://composer.typo3.org/)

        ```shell
        composer require typo3-ter/rte-ckeditor-image
        ```

    3. download from [TYPO3 TER](https://extensions.typo3.org/extension/rte_ckeditor_image/)

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

## Configuration

### Enable popup

Enable TYPO3 popup function (Enlarge on Click checkbox) via Template TS:

```
# Template TS
lib.contentElement.settings.media.popup.enable = 1
```

### Maximum width/height

The maximum dimensions relate to the configuration for magic images which have to be set in Page TSConfig:

```
# Page TSConfig
RTE.default.buttons.image.options.magic {
    maxWidth = 1020  # Default: 300
    maxHeight = 800  # Default: 1000
}
```

### Allowed extensions

By default the extensions from `$TYPO3_CONF_VARS['GFX']['imagefile_ext']` are allowed. However you can override this for CKEditor by adding the following to your YAML configuration:

```yaml
editor:
  externalPlugins:
      typo3image:
        allowedExtensions: "jpg,jpeg,png"
```
