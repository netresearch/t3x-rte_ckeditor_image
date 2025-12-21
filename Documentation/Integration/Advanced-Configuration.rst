.. include:: /Includes.rst.txt

.. _integration-configuration-advanced:

======================
Advanced Configuration
======================

Advanced configuration options including custom styles, performance optimization, extension settings, and best practices.

.. note::
   **For Advanced Users**

   The extension works out-of-the-box with zero configuration. This section covers:

   -  Custom image styles and style groups.
   -  Performance optimization and image processing.
   -  Extension-specific settings.
   -  Best practices for production environments.

   You only need these configurations if you want to customize beyond the automatic defaults.

.. contents:: Table of Contents
   :depth: 3
   :local:

CKEditor Style Configuration
=============================

.. _integration-configuration-custom-styles:

Adding Image Styles
-------------------

Define custom styles for images:

..  code-block:: yaml
    :caption: EXT:my_ext/Configuration/RTE/Default.yaml

    editor:
      config:
        style:
          definitions:
            - name: 'Image Left'
              element: 'img'
              classes: ['float-left', 'mr-3']
            - name: 'Image Right'
              element: 'img'
              classes: ['float-right', 'ml-3']
            - name: 'Image Center'
              element: 'img'
              classes: ['d-block', 'mx-auto']
            - name: 'Full Width'
              element: 'img'
              classes: ['w-100']

Style Groups
------------

Organize styles in groups:

..  code-block:: yaml
    :caption: Style group definitions

    editor:
      config:
        style:
          definitions:
            # ... style definitions ...

          # Group styles in dropdown
          groupDefinitions:
            - name: 'Image Alignment'
              styles: ['Image Left', 'Image Right', 'Image Center']
            - name: 'Image Size'
              styles: ['Full Width', 'Half Width']

Extension Configuration
=======================

Configure extension behavior in :guilabel:`Admin Tools > Settings > Extension Configuration` or :file:`settings.php`:

..  confval:: fetchExternalImages
    :name: confval-fetchexternalimages
    :type: boolean
    :Default: true
    :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image']['fetchExternalImages']

    Controls whether external image URLs are automatically fetched and uploaded to the backend user's upload folder.

    When enabled, pasting external image URLs into the editor will trigger automatic download and upload to FAL.

    Options:

    -  :php:`true`: External image URLs are fetched and uploaded to BE user's uploads folder.
    -  :php:`false`: External URLs remain as external links (not recommended for security).

    **Example:**

    ..  code-block:: php
        :caption: settings.php or LocalConfiguration.php

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'] = [
            'fetchExternalImages' => true,
        ];

Quality and Image Processing
============================

.. versionadded:: 13.1.5

   Quality multiplier support, noScale mode, and SVG handling.

.. _integration-configuration-quality:

Quality Multipliers
-------------------

The extension supports quality multipliers for high-DPI displays and print output:

..  list-table:: Quality multiplier options
    :header-rows: 1
    :widths: 20 15 65

    *   - Quality
        - Multiplier
        - Use Case

    *   - No Scaling
        - 1.0x
        - Use exact dimensions specified.

    *   - Low
        - 0.9x
        - Performance optimization.

    *   - Standard
        - 1.0x
        - Default web display.

    *   - Retina
        - 2.0x
        - High-DPI displays (Retina).

    *   - Ultra
        - 3.0x
        - Extra sharp display.

    *   - Print
        - 6.0x
        - Print-quality output.

Editors can select quality from a dropdown in the image dialog. The selection
persists with the image and affects frontend processing dimensions.

**TSConfig for quality-based processing:**

..  code-block:: typoscript
    :caption: Maximum dimensions for image processing

    RTE.default.buttons.image.options.magic {
        maxWidth = 1920
        maxHeight = 1080
    }

When quality multipliers are applied, the actual processing dimensions are
calculated as: :samp:`display dimensions × quality multiplier`, capped by maxWidth/maxHeight.

noScale Mode
------------

Skip TYPO3 image processing entirely and use original files:

-  **Manual toggle**: Editors can enable :guilabel:`No Scaling` in the image dialog.
-  **SVG auto-detection**: SVG files automatically use noScale mode.
-  **Original file delivery**: The original file URL is used instead of processed variants.

This is useful for:

-  Vector graphics (SVG) that should not be rasterized.
-  Images already optimized for web delivery.
-  Situations where exact original quality is required.

SVG Support
-----------

The extension handles SVG files with special processing:

-  **Dimension extraction**: Reads dimensions from :html:`viewBox` or :html:`width`/:html:`height` attributes.
-  **No rasterization**: SVGs are never processed through ImageMagick/GraphicsMagick.
-  **Automatic noScale**: SVG files automatically skip image processing.

.. note::

   SVG files are served as-is. Ensure your SVGs are sanitized before upload
   to prevent XSS vulnerabilities.

Performance Optimization
========================

.. _integration-configuration-image-processing:

Image Processing Configuration
-------------------------------

..  code-block:: php
    :caption: LocalConfiguration.php

    $GLOBALS['TYPO3_CONF_VARS']['GFX'] = [
        'processor' => 'ImageMagick',
        'processor_path' => '/usr/bin/',
        'processor_enabled' => true,
        'processor_effects' => true,

        // Image quality
        'jpg_quality' => 85,

        // Maximum dimensions
        'imagefile_ext' => 'gif,jpg,jpeg,png,webp',
    ];

Processed File Cache
--------------------

TYPO3 caches processed images in :file:`_processed_/` folder. Clear if needed:

..  code-block:: bash
    :caption: Clear TYPO3 caches

    # TYPO3 CLI
    ./vendor/bin/typo3 cache:flush --group=pages

Performance Best Practices
---------------------------

#. Configure appropriate image processing quality (:php:`jpg_quality: 85`).
#. Enable TYPO3 page caching for content with images.
#. Use WebP format where supported.
#. Implement lazy loading for images below the fold.
#. Set reasonable maximum dimensions in TSConfig.
#. Consider using CDN for image delivery in production.

Example Configurations
======================

.. _integration-configuration-minimal:

Minimal Setup
-------------

..  code-block:: yaml
    :caption: Minimal RTE with just image support

    imports:
      - { resource: "EXT:rte_ckeditor/Configuration/RTE/Minimal.yaml" }
      - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

    editor:
      config:
        removePlugins: null
        toolbar:
          items: [insertimage]

.. _integration-configuration-complete-example:

Full-Featured Setup
-------------------

..  code-block:: yaml
    :caption: Complete RTE with all features

    imports:
      - { resource: "EXT:rte_ckeditor/Configuration/RTE/Full.yaml" }
      - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

    editor:
      config:
        removePlugins: null
        toolbar:
          items:
            - heading
            - '|'
            - bold
            - italic
            - '|'
            - insertimage
            - link
            - '|'
            - bulletedList
            - numberedList
            - '|'
            - style

        style:
          definitions:
            - name: 'Image Left'
              element: 'img'
              classes: ['float-left']
            - name: 'Image Right'
              element: 'img'
              classes: ['float-right']

      externalPlugins:
        typo3image:
          allowedExtensions: "jpg,jpeg,png,gif,webp"

..  code-block:: typoscript
    :caption: Page TSConfig

    RTE.default {
        preset = full

        buttons.image.options.magic {
            maxWidth = 1920
            maxHeight = 1080
        }
    }

..  code-block:: typoscript
    :caption: TypoScript Setup

    lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib.class {
        default = img-fluid
    }

.. _best-practices:

Best Practices
==============

Configuration Best Practices
-----------------------------

#. **Start with minimal configuration** and add features incrementally.
#. **Test in staging environment** before deploying to production.
#. **Use separate RTE presets** for different content types.
#. **Enable caching** for processed images.
#. **Set appropriate maxWidth/maxHeight** to prevent oversized images.
#. **Configure lazy loading** for better performance.
#. **Use meaningful style names** that reflect intent, not appearance.
#. **Document custom configurations** for team members.

Security Considerations
-----------------------

-  Restrict allowed file extensions to safe image formats.
-  Configure appropriate file mounts for backend users.
-  Review and limit upload folder permissions.
-  Validate image dimensions and file sizes.
-  Keep extension and TYPO3 core up to date.

Maintenance
-----------

-  Regularly clear processed image cache.
-  Monitor storage usage in upload folders.
-  Review and clean unused images periodically.
-  Keep documentation of custom configurations.
-  Test after TYPO3 core or extension updates.

.. _integration-configuration-third-party:

Using with Third-Party Extensions
==================================

**Automatic Support for ALL Extensions (v13.x+)**

This extension automatically configures RTE image support for **all tables** with RTE-enabled text fields, including:

-  TYPO3 core tables (:sql:`tt_content`, :sql:`sys_template`).
-  Third-party extensions (:sql:`tx_news_domain_model_news`, etc.).
-  Custom extension tables.

**No manual configuration is required.** The extension uses a PSR-14 event listener that automatically adds the :php:`rtehtmlarea_images` soft reference to all RTE fields during TCA compilation.

.. _integration-configuration-extension-settings:

Extension Configuration
-----------------------

You can customize the automatic behavior via Extension Configuration:

:guilabel:`Admin Tools > Settings > Extension Configuration > rte_ckeditor_image`

..  confval:: enableAutomaticRteSoftref
    :name: confval-enableautomaticrtesoftref
    :type: boolean
    :Default: 1 (enabled)

    Master switch to enable or disable automatic RTE softref processing.

    When enabled, the extension automatically adds :php:`rtehtmlarea_images` soft reference to all RTE-enabled text fields across all tables.

..  confval:: excludedTables
    :name: confval-excludedtables
    :type: string (comma-separated)
    :Default: (empty)

    Comma-separated list of table names to exclude from automatic processing.

    **Example:** :php:`tx_form_formframework,sys_template`

    Use this if specific tables should not have automatic softref processing.

..  confval:: includedTablesOnly
    :name: confval-includedtablesonly
    :type: string (comma-separated)
    :Default: (empty)

    Whitelist mode: If set, ONLY these tables will be processed.

    **Example:** :php:`tt_content,tx_news_domain_model_news,tx_myext_article`

    This overrides the :confval:`excludedTables <confval-excludedtables>` setting. Leave empty to process all tables (recommended).

Configuration Examples
~~~~~~~~~~~~~~~~~~~~~~

**Exclude Specific Tables:**

..  code-block:: php
    :caption: settings.php or LocalConfiguration.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'] = [
        'enableAutomaticRteSoftref' => true,
        'excludedTables' => 'tx_form_formframework,sys_template',
    ];

**Whitelist Mode (Only Specific Tables):**

..  code-block:: php
    :caption: settings.php or LocalConfiguration.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'] = [
        'enableAutomaticRteSoftref' => true,
        'includedTablesOnly' => 'tt_content,tx_news_domain_model_news',
    ];

**Disable Automatic Processing:**

..  code-block:: php
    :caption: settings.php or LocalConfiguration.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'] = [
        'enableAutomaticRteSoftref' => false,
    ];

.. note::
   After changing extension configuration, clear all caches:

   ..  code-block:: bash
       :caption: Clear all caches

       ./vendor/bin/typo3 cache:flush

.. _integration-configuration-troubleshooting-third-party:

Troubleshooting Third-Party Extension Issues
---------------------------------------------

Images Disappear When Saving
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

**Symptom:** Images inserted in RTE fields disappear after saving the record.

**Cause:** Automatic softref processing may be disabled, or the table is excluded.

**Solution:**

#. Verify automatic processing is enabled:

   ..  code-block:: php
       :caption: Check extension configuration

       // Check extension configuration
       $config = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'];
       // enableAutomaticRteSoftref should be true (default)

#. Check if the table is excluded:

   ..  code-block:: php
       :caption: Check table exclusion settings

       // Check excludedTables and includedTablesOnly settings
       debug($config['excludedTables']);
       debug($config['includedTablesOnly']);

#. Verify soft reference is configured in TCA:

   ..  code-block:: php
       :caption: Verify TCA softref configuration

       // In TYPO3 backend console or debug output
       debug($GLOBALS['TCA']['your_table']['columns']['your_field']['config']['softref']);
       // Should output: "rtehtmlarea_images" or include it in comma-separated list

#. Check if :html:`data-htmlarea-file-uid` attribute is preserved:

   ..  code-block:: sql
       :caption: Check database content

       -- Check database content
       SELECT bodytext FROM tx_news_domain_model_news WHERE uid = 123;
       -- Should contain: data-htmlarea-file-uid="456"

#. Clear all caches and retry:

   ..  code-block:: bash
       :caption: Clear all caches

       ./vendor/bin/typo3 cache:flush

Automatic Processing Not Working
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

**Symptom:** RTE images are not tracked automatically in custom tables.

**Cause:** Event listener not registered, caches not cleared, or configuration issue.

**Solution:**

#. Verify the event listener is registered:

   ..  code-block:: bash
       :caption: Check RteSoftrefEnforcer class

       # Check if RteSoftrefEnforcer class exists
       ls Classes/Listener/TCA/RteSoftrefEnforcer.php

#. Verify :file:`Services.yaml` configuration:

   ..  code-block:: bash
       :caption: Check listener registration

       # Check listener registration
       grep -A 5 "RteSoftrefEnforcer" Configuration/Services.yaml

#. Clear all caches:

   ..  code-block:: bash
       :caption: Clear all caches

       ./vendor/bin/typo3 cache:flush

#. Check if the field is RTE-enabled:

   ..  code-block:: php
       :caption: Verify RTE field configuration

       // Field must have type='text' AND enableRichtext=true
       debug($GLOBALS['TCA']['your_table']['columns']['your_field']['config']);

data-htmlarea-file-uid Attribute Missing
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

**Symptom:** Images render but the :html:`data-htmlarea-file-uid` attribute is missing from saved content.

**Cause:** Soft reference parser not registered or not being invoked.

**Solution:**

#. Verify soft reference parser is registered:

   ..  code-block:: bash
       :caption: Check softreference.parser registration

       # Check Services.yaml for softreference.parser
       grep -A 3 "softreference.parser" Configuration/Services.yaml

#. Verify soft reference is in TCA (see "Images Disappear When Saving" above).

#. Clear all caches:

   ..  code-block:: bash
       :caption: Clear all caches

       ./vendor/bin/typo3 cache:flush

Images Not Processed in Frontend
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

**Symptom:** Images appear as :html:`<img>` tags with :html:`data-htmlarea-file-uid` in frontend HTML.

**Cause:** TypoScript configuration missing or incorrect.

**Solution:** Ensure TypoScript static template is included:

..  code-block:: typoscript
    :caption: Include static template

    # Include static template in your root template
    # Template > Info/Modify > Edit whole template record > Includes
    # Select: "CKEditor Image Support" for "Include static (from extensions)"

Related Documentation
=====================

Configuration Topics
--------------------

- :ref:`integration-configuration-rte-setup` - RTE configuration and basic setup
- :ref:`integration-configuration-tsconfig` - Page TSConfig settings and upload configuration
- :ref:`integration-configuration-frontend-rendering` - TypoScript and frontend rendering setup

General Documentation
---------------------

- :ref:`integration-configuration` - Main configuration guide overview
- :ref:`quick-start` - Quick start guide
- :ref:`troubleshooting-index` - Troubleshooting guide
