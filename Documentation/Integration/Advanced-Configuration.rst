.. include:: /Includes.rst.txt

.. _integration-configuration-advanced:

======================
Advanced Configuration
======================

Advanced configuration options including custom styles, performance optimization, extension settings, and best practices.

.. note::
   **For Advanced Users**

   The extension works out-of-the-box with zero configuration. This section covers:

   - Custom image styles and style groups
   - Performance optimization and image processing
   - Extension-specific settings
   - Best practices for production environments

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

.. code-block:: yaml
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

.. code-block:: yaml

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

Configure extension behavior in Extension Manager or settings.php:

.. confval:: fetchExternalImages

   :type: boolean
   :Default: true
   :Path: $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image']['fetchExternalImages']

   Controls whether external image URLs are automatically fetched and uploaded to the backend user's upload folder.

   When enabled, pasting external image URLs into the editor will trigger automatic download and upload to FAL.

   Options:

   - ``true``: External image URLs are fetched and uploaded to BE user's uploads folder
   - ``false``: External URLs remain as external links (not recommended for security)

   **Example:**

   .. code-block:: php
      :caption: settings.php or LocalConfiguration.php

      $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'] = [
          'fetchExternalImages' => true,
      ];

Performance Optimization
========================

.. _integration-configuration-image-processing:

Image Processing Configuration
-------------------------------

.. code-block:: php
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

TYPO3 caches processed images in ``_processed_/`` folder. Clear if needed:

.. code-block:: bash

   # TYPO3 CLI
   ./vendor/bin/typo3 cache:flush --group=pages

Performance Best Practices
---------------------------

- Configure appropriate image processing quality (jpg_quality: 85)
- Enable TYPO3 page caching for content with images
- Use WebP format where supported
- Implement lazy loading for images below the fold
- Set reasonable maximum dimensions in TSConfig
- Consider using CDN for image delivery in production

Example Configurations
======================

.. _integration-configuration-minimal:

Minimal Setup
-------------

.. code-block:: yaml
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

.. code-block:: yaml
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

.. code-block:: typoscript
   :caption: Page TSConfig

   RTE.default {
       preset = full

       buttons.image.options.magic {
           maxWidth = 1920
           maxHeight = 1080
       }
   }

.. code-block:: typoscript
   :caption: TypoScript Setup

   lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib.class {
       default = img-fluid
   }

.. _best-practices:

Best Practices
==============

Configuration Best Practices
-----------------------------

1. **Start with minimal configuration** and add features incrementally
2. **Test in staging environment** before deploying to production
3. **Use separate RTE presets** for different content types
4. **Enable caching** for processed images
5. **Set appropriate maxWidth/maxHeight** to prevent oversized images
6. **Configure lazy loading** for better performance
7. **Use meaningful style names** that reflect intent, not appearance
8. **Document custom configurations** for team members

Security Considerations
-----------------------

- Restrict allowed file extensions to safe image formats
- Configure appropriate file mounts for backend users
- Review and limit upload folder permissions
- Validate image dimensions and file sizes
- Keep extension and TYPO3 core up to date

Maintenance
-----------

- Regularly clear processed image cache
- Monitor storage usage in upload folders
- Review and clean unused images periodically
- Keep documentation of custom configurations
- Test after TYPO3 core or extension updates

.. _integration-configuration-third-party:

Using with Third-Party Extensions
==================================

This extension automatically configures RTE image support for TYPO3's built-in ``tt_content`` table. However, third-party extensions like EXT:news require additional TCA configuration.

.. _integration-configuration-news:

EXT:news Support
----------------

**Automatic Configuration (v13.1+)**

Starting with version 13.1.0, this extension automatically configures RTE image support for EXT:news when the extension is installed. No manual configuration is required.

**Manual Configuration (if needed)**

If you're using an older version or need to configure another extension, you can manually add the soft reference configuration:

.. code-block:: php
   :caption: EXT:site_package/Configuration/TCA/Overrides/tx_news_domain_model_news.php

   <?php

   declare(strict_types=1);

   defined('TYPO3') || exit;

   call_user_func(
       static function (): void {
           // Only configure if EXT:news is installed
           if (!isset($GLOBALS['TCA']['tx_news_domain_model_news'])) {
               return;
           }

           // Only configure if bodytext field exists
           if (!isset($GLOBALS['TCA']['tx_news_domain_model_news']['columns']['bodytext'])) {
               return;
           }

           $cleanSoftReferences = explode(
               ',',
               (string) ($GLOBALS['TCA']['tx_news_domain_model_news']['columns']['bodytext']['config']['softref'] ?? ''),
           );

           // Add rtehtmlarea_images soft reference
           $cleanSoftReferences = array_diff($cleanSoftReferences, ['images']);
           $cleanSoftReferences[] = 'rtehtmlarea_images';

           $GLOBALS['TCA']['tx_news_domain_model_news']['columns']['bodytext']['config']['softref'] = implode(
               ',',
               $cleanSoftReferences,
           );
       },
   );

.. _integration-configuration-custom-tables:

Custom Tables and Other Extensions
-----------------------------------

To enable RTE image support for custom tables or other third-party extensions:

**Step 1: Identify the RTE Field**

Find the table and field name in your extension's TCA configuration:

.. code-block:: php

   // Example: tx_myext_domain_model_article with bodytext field
   $GLOBALS['TCA']['tx_myext_domain_model_article']['columns']['bodytext']

**Step 2: Add Soft Reference Configuration**

Create a TCA override file in your extension:

.. code-block:: php
   :caption: EXT:my_extension/Configuration/TCA/Overrides/tx_myext_domain_model_article.php

   <?php

   declare(strict_types=1);

   defined('TYPO3') || exit;

   call_user_func(
       static function (): void {
           $table = 'tx_myext_domain_model_article';
           $field = 'bodytext';

           // Only configure if table and field exist
           if (!isset($GLOBALS['TCA'][$table]['columns'][$field])) {
               return;
           }

           $cleanSoftReferences = explode(
               ',',
               (string) ($GLOBALS['TCA'][$table]['columns'][$field]['config']['softref'] ?? ''),
           );

           // Add rtehtmlarea_images soft reference
           $cleanSoftReferences = array_diff($cleanSoftReferences, ['images']);
           $cleanSoftReferences[] = 'rtehtmlarea_images';

           $GLOBALS['TCA'][$table]['columns'][$field]['config']['softref'] = implode(
               ',',
               $cleanSoftReferences,
           );
       },
   );

**Step 3: Clear Caches**

After adding the TCA override:

.. code-block:: bash

   # Clear all caches
   ./vendor/bin/typo3 cache:flush

   # Or via backend
   Admin Tools > Maintenance > Flush TYPO3 and PHP Cache

.. _integration-configuration-troubleshooting-third-party:

Troubleshooting Third-Party Extension Issues
---------------------------------------------

Images Disappear When Saving
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

**Symptom:** Images inserted in RTE fields disappear after saving the record.

**Cause:** Missing soft reference configuration for the table's RTE field.

**Solution:** Add the soft reference configuration as described above.

**How to Verify:**

1. Check if soft reference is configured:

   .. code-block:: php

      // In TYPO3 backend console or debug output
      debug($GLOBALS['TCA']['your_table']['columns']['your_field']['config']['softref']);
      // Should output: "rtehtmlarea_images" or include it in comma-separated list

2. Check if ``data-htmlarea-file-uid`` attribute is preserved:

   .. code-block:: sql

      -- Check database content
      SELECT bodytext FROM tx_news_domain_model_news WHERE uid = 123;
      -- Should contain: data-htmlarea-file-uid="456"

data-htmlarea-file-uid Attribute Missing
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

**Symptom:** Images render but the ``data-htmlarea-file-uid`` attribute is missing from saved content.

**Cause:** Soft reference parser not registered or not being invoked.

**Solution:**

1. Verify soft reference is registered in Services.yaml (already done by this extension)
2. Add soft reference to TCA configuration (see above)
3. Clear all caches

Images Not Processed in Frontend
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

**Symptom:** Images appear as ``<img>`` tags with ``data-htmlarea-file-uid`` in frontend HTML.

**Cause:** TypoScript configuration missing or incorrect.

**Solution:** Ensure TypoScript static template is included:

.. code-block:: typoscript

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
