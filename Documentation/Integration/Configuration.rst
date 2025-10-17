.. include:: /Includes.rst.txt

.. _integration-configuration:

====================
Configuration Guide
====================

Complete configuration reference for integrating rte_ckeditor_image into your TYPO3 project.

.. contents:: Table of Contents
   :depth: 3
   :local:

RTE Configuration
=================

.. _integration-configuration-basic:

Basic Setup
-----------

Create or update your RTE preset configuration:

.. code-block:: yaml
   :caption: EXT:my_ext/Configuration/RTE/Default.yaml

   imports:
     # Import default RTE config
     - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
     # Import image plugin configuration
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

   editor:
     config:
       # Restore image plugin (default config removes it)
       removePlugins: null

       toolbar:
         items:
           - '|'
           - insertimage

Register Preset
~~~~~~~~~~~~~~~

.. code-block:: php
   :caption: EXT:my_ext/ext_localconf.php

   $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['default']
       = 'EXT:my_ext/Configuration/RTE/Default.yaml';

Enable Preset
~~~~~~~~~~~~~

.. code-block:: typoscript
   :caption: Page TSConfig

   RTE.default.preset = default

Advanced RTE Configuration
--------------------------

Custom Allowed Extensions
~~~~~~~~~~~~~~~~~~~~~~~~~

Override allowed image file extensions:

.. code-block:: yaml

   editor:
     externalPlugins:
       typo3image:
         route: "rteckeditorimage_wizard_select_image"
         allowedExtensions: "jpg,jpeg,png,gif,webp"

Default: Uses ``$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']``

Multiple RTE Presets
~~~~~~~~~~~~~~~~~~~~

Different configurations for different content types:

.. code-block:: yaml
   :caption: EXT:my_ext/Configuration/RTE/Simple.yaml

   imports:
     - { resource: "EXT:rte_ckeditor/Configuration/RTE/Minimal.yaml" }
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

   editor:
     config:
       removePlugins: null
       toolbar:
         items:
           - insertimage

.. code-block:: typoscript
   :caption: Different presets for different fields

   # Different presets for different fields
   RTE.default.preset = default
   RTE.config.tt_content.bodytext.preset = full
   RTE.config.tt_content.header.preset = simple

Page TSConfig
=============

Magic Image Configuration
-------------------------

Configure maximum image dimensions:

.. code-block:: typoscript

   RTE.default.buttons.image.options.magic {
       # Maximum width (default: 300)
       maxWidth = 1920

       # Maximum height (default: 1000)
       maxHeight = 9999
   }

.. warning::
   Due to TYPO3 bug #87068, you may need to add these settings to root page config instead of custom template extensions.

Processing Modes
----------------

.. code-block:: typoscript

   RTE.default.proc.overruleMode := addToList(default)
   RTE.default.proc.overruleMode := addToList(rtehtmlarea_images_db)

.. _integration-configuration-upload-folder:

Upload Folder Configuration
---------------------------

.. code-block:: typoscript

   RTE.default.buttons.image.options {
       # Default upload folder
       defaultUploadFolder = 1:rte_uploads/

       # Create upload folder if missing
       createUploadFolderIfNeeded = 1
   }

TypoScript Configuration
========================

.. _integration-configuration-frontend-rendering:

Frontend Rendering Setup
-------------------------

The extension provides default configuration. You can customize it:

.. code-block:: typoscript

   lib.parseFunc_RTE {
       tags.img = TEXT
       tags.img {
           current = 1
           preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
       }

       tags.a = TEXT
       tags.a {
           current = 1
           preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageLinkRenderingController->renderImages
       }

       nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib {
           # Remove internal data attributes from frontend
           allparams.unset = 1
           data-htmlarea-file-uid.unset = 1
           data-htmlarea-file-table.unset = 1
           data-htmlarea-zoom.unset = 1
           data-htmlarea-clickenlarge.unset = 1
           data-title-override.unset = 1
           data-alt-override.unset = 1
       }
   }

   lib.parseFunc_RTE.nonTypoTagStdWrap.encapsLines.encapsTagList := addToList(img)

Default CSS Class
-----------------

Add default class to all RTE images:

.. code-block:: typoscript

   lib.parseFunc_RTE {
       nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib.class {
           default = img-fluid responsive-image
       }
   }

Lazyload Configuration
----------------------

Enable native browser lazy loading:

.. code-block:: typoscript
   :caption: Template Constants

   styles.content.image.lazyLoading = lazy
   # Options: lazy, eager, auto

Lightbox Integration
--------------------

With fluid_styled_content:

.. code-block:: typoscript
   :caption: Template Constants

   styles.content.textmedia.linkWrap.lightboxEnabled = 1

Extension Configuration
=======================

Configure extension behavior in Extension Manager or settings.php:

Fetch External Images
---------------------

.. code-block:: php
   :caption: settings.php or LocalConfiguration.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'] = [
       'fetchExternalImages' => true,  // Default: true
   ];

**Options**:

- ``true``: External image URLs are fetched and uploaded to BE user's uploads folder
- ``false``: External URLs remain as external links

Static Template
===============

Include the static template for default TypoScript:

1. Go to **Template** → **Info/Modify**
2. Edit whole template record
3. Go to **Includes** tab
4. Add ``CKEditor Image Support`` in **Include static (from extensions)**
5. ⚠️ Add it **before** Fluid Styled Content

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

Content Element Configuration
==============================

Enable for Specific Content Types
----------------------------------

.. code-block:: typoscript

   # Only enable for tt_content bodytext
   RTE.config.tt_content.bodytext {
       preset = default
       buttons.image.options.magic {
           maxWidth = 1200
           maxHeight = 800
       }
   }

Disable for Specific Fields
----------------------------

.. code-block:: typoscript

   # Disable RTE entirely for specific field
   RTE.config.tt_content.header.disabled = 1

Backend User Permissions
========================

.. _integration-configuration-permissions:

File Mounts
-----------

Ensure backend users have appropriate file mounts:

.. code-block:: typoscript
   :caption: User TSConfig

   options.defaultUploadFolder = 1:user_uploads/rte/

Access Restrictions
-------------------

.. code-block:: typoscript
   :caption: User TSConfig

   # Allow only specific file extensions
   options.file_list.validFileExtensions = jpg,jpeg,png,gif,webp

Multi-Language Configuration
=============================

Language-Specific Presets
--------------------------

.. code-block:: typoscript

   [siteLanguage("locale") == "de_DE"]
       RTE.default.preset = german
   [END]

   [siteLanguage("locale") == "en_US"]
       RTE.default.preset = english
   [END]

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

Troubleshooting Configuration
==============================

Debug RTE Configuration
-----------------------

Enable RTE debugging:

.. code-block:: typoscript
   :caption: Page TSConfig

   RTE.default.showButtons = *
   RTE.default.hideButtons =

Verify Configuration Loading
-----------------------------

Check active RTE configuration in backend:

1. Edit content element
2. Open browser console
3. Check ``CKEDITOR.config`` object

Configuration Priority
----------------------

Configuration precedence (highest to lowest):

1. Field-specific config: ``RTE.config.tt_content.bodytext``
2. Type-specific config: ``RTE.config.tt_content``
3. Default config: ``RTE.default``
4. Extension defaults

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

Related Documentation
=====================

- :ref:`installation`
- :ref:`best-practices`
- :ref:`typoscript-reference`
- :ref:`troubleshooting-common-issues`
