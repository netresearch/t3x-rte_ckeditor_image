# Image support for CKEditor for TYPO3

This extension adds the TYPO3 image browser to CKEditor.

- Same image handling as rtehtmlarea (magic images)
- Dialog for changing width, height, alt and title
- Usual RTE TSConfig supported

## Installation

1. Install + activate the extension
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
    ```

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

### Allowed extensions

By default the extensions from `$TYPO3_CONF_VARS['GFX']['imagefile_ext']` are allowed. However you can override this for CKEditor by adding the following to your YAML configuration:

    ```yaml
    editor:
      externalPlugins:
          typo3image:
            allowedExtensions: "jpg,jpeg,png"
    ```
