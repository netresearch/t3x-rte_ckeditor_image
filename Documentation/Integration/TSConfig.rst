.. include:: /Includes.rst.txt

.. _integration-configuration-tsconfig:

==================
Page TSConfig Setup
==================

Configuration of page TSConfig settings for image handling, upload folders, and permissions.

.. contents:: Table of Contents
   :depth: 3
   :local:

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
