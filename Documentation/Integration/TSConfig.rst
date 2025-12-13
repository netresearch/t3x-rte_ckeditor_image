.. include:: /Includes.rst.txt

.. _integration-configuration-tsconfig:

==================
Page TSConfig Setup
==================

Configuration of page TSConfig settings for image handling, upload folders, and permissions.

.. important::
   **Zero-Configuration Installation (v13.0.0+)**

   The extension automatically loads basic Page TSConfig via ``ext_tables.php``:

   - ✅ Magic image processing enabled (maxWidth: 1920, maxHeight: 9999)
   - ✅ RTE preset configured globally
   - ✅ Processing modes set automatically

   This section is for **advanced users** who need to customize these defaults or add field-specific configurations.

.. contents:: Table of Contents
   :depth: 3
   :local:

Page TSConfig
=============

Magic Image Configuration
-------------------------

Configure maximum image dimensions for automatic image processing:

.. confval:: RTE.default.buttons.image.options.magic.maxWidth

   :type: integer
   :Default: 300

   Maximum width in pixels for images inserted through the RTE.

   Images larger than this value will be automatically resized during processing.

.. confval:: RTE.default.buttons.image.options.magic.maxHeight

   :type: integer
   :Default: 1000

   Maximum height in pixels for images inserted through the RTE.

   Images taller than this value will be automatically resized during processing.

**Example:**

.. code-block:: typoscript

   RTE.default.buttons.image.options.magic {
       maxWidth = 1920
       maxHeight = 9999
   }

.. warning::
   Due to TYPO3 bug #87068, you may need to add these settings to root page config instead of custom template extensions.

Processing Modes
----------------

.. code-block:: typoscript

   RTE.default.proc.overruleMode := addToList(default)

.. _integration-configuration-upload-folder:

Upload Folder Configuration
---------------------------

.. confval:: RTE.default.buttons.image.options.defaultUploadFolder

   :type: string
   :Default: (empty)

   Default upload folder for images inserted through the RTE.

   Format: ``<storage_uid>:<folder_path>``

   Example: ``1:rte_uploads/`` uses storage 1 and uploads to ``rte_uploads/`` directory.

.. confval:: RTE.default.buttons.image.options.createUploadFolderIfNeeded

   :type: boolean
   :Default: false

   Automatically creates the upload folder if it doesn't exist.

   Recommended to set to ``1`` (true) to avoid upload errors.

**Example:**

.. code-block:: typoscript

   RTE.default.buttons.image.options {
       defaultUploadFolder = 1:rte_uploads/
       createUploadFolderIfNeeded = 1
   }

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

Related Documentation
=====================

Configuration Topics
--------------------

- :ref:`integration-configuration-rte-setup` - RTE configuration and basic setup
- :ref:`integration-configuration-frontend-rendering` - TypoScript and frontend rendering setup
- :ref:`integration-configuration-advanced` - Advanced configuration, styles, and best practices

General Documentation
---------------------

- :ref:`integration-configuration` - Main configuration guide overview
- :ref:`quick-start` - Quick start guide
- :ref:`troubleshooting-index` - Troubleshooting guide
