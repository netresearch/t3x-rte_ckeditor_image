.. include:: /Includes.rst.txt

.. _examples-basic-integration:

=================
Basic Integration
=================

Quick start guide demonstrating the zero-configuration installation and basic customization options.

.. contents:: Table of Contents
   :depth: 3
   :local:

Zero-Configuration Installation
================================

**Objective**: Get image functionality working with zero manual configuration

Installation
------------

.. code-block:: bash

   composer require netresearch/rte-ckeditor-image:^13.0

**That's it!** The extension automatically:

- ✅ Registers the ``rteWithImages`` preset for backend RTE
- ✅ Configures toolbar with ``insertimage`` button for all sites
- ✅ Loads TypoScript for frontend image rendering
- ✅ No manual configuration required

**Result**: Full image functionality working out-of-the-box ✅

Verification
------------

1. **Backend**: Log into TYPO3 backend → Edit any content element → RTE should show image button
2. **Frontend**: Insert image in RTE → Save → View frontend → Image renders correctly

.. figure:: /Images/demo.gif
   :alt: Image insertion demo
   :class: with-shadow
   :width: 600px

   The ``insertimage`` button is automatically available after installation

Custom Configuration (Optional)
================================

If you need to customize the RTE configuration, you can create your own preset:

Custom Preset
-------------

.. code-block:: yaml
   :caption: EXT:my_site/Configuration/RTE/Custom.yaml

   imports:
     - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

   editor:
     config:
       toolbar:
         items:
           - heading
           - '|'
           - bold
           - italic
           - '|'
           - insertimage
           - link

Register Custom Preset
-----------------------

.. code-block:: php
   :caption: EXT:my_site/ext_localconf.php

   $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['custom']
       = 'EXT:my_site/Configuration/RTE/Custom.yaml';

Enable Custom Preset
---------------------

.. code-block:: typoscript
   :caption: Configuration/page.tsconfig

   RTE.default.preset = custom

.. note::
   The default ``rteWithImages`` preset is automatically configured.
   Custom presets are only needed for specific toolbar or plugin customization.

Related Documentation
=====================

- :ref:`integration-configuration` - Complete configuration guide
- :ref:`examples-image-styles` - Add custom image styles
- :ref:`troubleshooting-index` - Problem solving
