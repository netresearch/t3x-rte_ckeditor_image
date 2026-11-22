.. include:: /Includes.rst.txt

.. Copyright (c) 2025-2026 Netresearch DTT GmbH
   SPDX-License-Identifier: AGPL-3.0-or-later

.. _integration-configuration-tsconfig:

==================
Page TSConfig Setup
==================

Configuration of page TSConfig settings for image handling, upload folders, and permissions.

.. important::
   **Page TSConfig from the Site Set**

   The Site Set ``netresearch/rte-ckeditor-image`` ships page TSconfig
   (``Configuration/Sets/RteCKEditorImage/page.tsconfig``) that sets:

   - ✅ Magic image dimensions (maxWidth: 1920, maxHeight: 9999)
   - ✅ RTE preset ``RTE.default.preset = rteWithImages``
   - ✅ Processing mode ``RTE.default.proc.overruleMode := addToList(default)``

   Without the Site Set, none of this page TSconfig is loaded.
   Add the lines above to your own page TSconfig.

   .. note::
      **Frontend TypoScript** requires manual inclusion. See :ref:`integration-configuration-frontend-rendering`.

   This section is for **advanced users** who need to customize these defaults or add field-specific configurations.

.. contents:: Table of Contents
   :depth: 3
   :local:

Page TSConfig
=============

.. figure:: /Images/backend-tsconfig-rte.png
   :alt: Page TSConfig module showing RTE configuration
   :class: with-shadow
   :width: 600px

   Page TSConfig configuration in the TYPO3 backend

Magic Image Configuration
-------------------------

Configure maximum image dimensions for automatic image processing:

.. confval:: RTE.default.buttons.image.options.magic.maxWidth

   :type: integer
   :Default: 1920

   Maximum width in pixels for images inserted through the RTE.

   When an editor selects an image, the image dialog suggests display dimensions
   that fit within this limit, keeping the aspect ratio.
   If the option is not set, the extension falls back to 1920.

.. confval:: RTE.default.buttons.image.options.magic.maxHeight

   :type: integer
   :Default: 9999

   Maximum height in pixels for images inserted through the RTE.

   When an editor selects an image, the image dialog suggests display dimensions
   that fit within this limit, keeping the aspect ratio.
   If the option is not set, the extension falls back to 9999.

.. note::
   The extension reads these options only from
   ``RTE.<name>.buttons.image.options.magic``, where ``<name>`` is the
   field's TCA ``richtextConfiguration`` (``default`` if none is set).
   Values under ``RTE.config.<table>.<field>`` are not read.

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

The extension has no upload folder option of its own.
The image browser and the external image import use the TYPO3 core
default upload folder, which core resolves from the user TSconfig option
``options.defaultUploadFolder`` (see
:ref:`integration-configuration-permissions`).
The extension passes no page ID to the resolver, so
``options.defaultUploadFolder`` in page TSconfig is not used.

Content Element Configuration
==============================

Enable for Specific Content Types
----------------------------------

.. code-block:: typoscript

   # Use a specific preset for tt_content bodytext
   RTE.config.tt_content.bodytext {
       preset = rteWithImages
   }

.. note::
   ``buttons.image.options.magic`` is not read under ``RTE.config.*``.
   Set the magic image dimensions under ``RTE.<name>``, where ``<name>`` is the
   field's ``richtextConfiguration`` (``RTE.default`` if none is set; see above).

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
2. Open the backend module :guilabel:`Page TSconfig`, select the page and
   check the active ``RTE.`` tree

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
