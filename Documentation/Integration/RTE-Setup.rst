.. include:: /Includes.rst.txt

.. _integration-configuration-rte-setup:

=========
RTE Setup
=========

Complete guide for configuring the RTE (Rich Text Editor) with CKEditor image support.

.. important::
   **Automatic Backend Configuration**

   The extension automatically configures the backend RTE after installation:

   .. code-block:: bash

      composer require netresearch/rte-ckeditor-image

   The ``insertimage`` button is automatically available in all RTEs. **No RTE configuration needed!**

   .. note::
      **Frontend rendering requires TypoScript inclusion.** See :ref:`integration-configuration-frontend-rendering`.

   This guide is for **advanced users** who need custom RTE presets or want to override the defaults.

.. contents:: Table of Contents
   :depth: 3
   :local:

Automatic Configuration (Default)
==================================

The extension automatically provides for the **backend**:

- **Preset**: ``rteWithImages`` registered and enabled globally
- **Toolbar**: ``insertimage`` button included in default toolbar
- **Configuration**: ``Configuration/RTE/Default.yaml`` with full toolbar

.. note::
   **Frontend rendering** requires manual TypoScript inclusion.
   See :ref:`integration-configuration-frontend-rendering`.

Custom RTE Configuration
=========================

.. _integration-configuration-basic:

Creating Custom Presets
-----------------------

If you need to customize the toolbar or RTE behavior beyond the defaults, create a custom preset:

.. code-block:: yaml
   :caption: EXT:my_ext/Configuration/RTE/Custom.yaml

   imports:
     # Import default RTE config
     - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
     # Import image plugin configuration
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

   editor:
     config:
       toolbar:
         items:
           - heading
           - '|'
           - insertimage
           - link
           - '|'
           - bold
           - italic

Register Custom Preset
~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php
   :caption: EXT:my_ext/ext_localconf.php

   $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['custom']
       = 'EXT:my_ext/Configuration/RTE/Custom.yaml';

Enable Custom Preset
~~~~~~~~~~~~~~~~~~~~~

.. code-block:: typoscript
   :caption: Page TSConfig

   RTE.default.preset = custom

Advanced RTE Configuration
--------------------------

Custom Allowed Extensions
~~~~~~~~~~~~~~~~~~~~~~~~~

.. confval:: editor.externalPlugins.typo3image.allowedExtensions

   :type: string
   :Default: Value from ``$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']``

   Comma-separated list of allowed image file extensions for the RTE image plugin.

   Restricts which file types can be selected through the image browser.

   **Example:**

   .. code-block:: yaml

      editor:
        externalPlugins:
          typo3image:
            route: "rteckeditorimage_wizard_select_image"
            allowedExtensions: "jpg,jpeg,png,gif,webp"

   If not specified, falls back to the global TYPO3 configuration at ``$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']``

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

Related Documentation
=====================

Configuration Topics
--------------------

- :ref:`integration-configuration-tsconfig` - Page TSConfig settings and upload configuration
- :ref:`integration-configuration-frontend-rendering` - TypoScript and frontend rendering setup
- :ref:`integration-configuration-advanced` - Advanced configuration, styles, and best practices

General Documentation
---------------------

- :ref:`integration-configuration` - Main configuration guide overview
- :ref:`quick-start` - Quick start guide
- :ref:`troubleshooting-index` - Troubleshooting guide
