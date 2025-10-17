.. include:: /Includes.rst.txt

.. _integration-configuration-rte-setup:

=========
RTE Setup
=========

Complete guide for configuring the RTE (Rich Text Editor) with CKEditor image support.

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
