.. include:: /Includes.rst.txt

.. Copyright (c) 2025-2026 Netresearch DTT GmbH
   SPDX-License-Identifier: AGPL-3.0-or-later

.. _examples-basic-integration:

=================
Basic Integration
=================

Quick start guide demonstrating the installation and basic customization options.

.. contents:: Table of Contents
   :depth: 3
   :local:

Installation
============

**Objective**: Get image functionality working in backend and frontend

Step 1: Install Extension
-------------------------

.. code-block:: bash

   composer require netresearch/rte-ckeditor-image:^13.0

The extension registers the ``rteWithImages`` preset with the ``insertimage`` button.
The preset becomes active through the Site Set ``netresearch/rte-ckeditor-image``:

.. code-block:: yaml
   :caption: config/sites/<site>/config.yaml

   dependencies:
     - netresearch/rte-ckeditor-image

The Site Set also includes the frontend TypoScript and the image alignment CSS.
Without the Site Set, set ``RTE.default.preset = rteWithImages`` in page TSconfig
and include the TypoScript as shown in step 2.

Step 2: Include TypoScript
--------------------------

If you do not use the Site Set, include the TypoScript for **frontend rendering**.
It covers rendering only, not the image alignment CSS:

**Option A: Static Template**

#. Go to :guilabel:`WEB > Template` module
#. Select your root page, edit the template
#. In :guilabel:`Includes` tab, add: :guilabel:`CKEditor Image Support`

**Option B: Direct Import**

.. code-block:: typoscript

   @import 'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript'

Verification
------------

1. **Backend**: Log into TYPO3 backend → Edit any content element → RTE should show image button
2. **Frontend**: Insert image in RTE → Save → View frontend → Image renders correctly

.. figure:: /Images/demo.gif
   :alt: Image insertion demo
   :class: with-shadow
   :width: 600px

   The ``insertimage`` button is available once the ``rteWithImages`` preset is active

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
   The Site Set activates the default ``rteWithImages`` preset.
   Custom presets are only needed for specific toolbar or plugin customization.

Related Documentation
=====================

- :ref:`integration-configuration` - Complete configuration guide
- :ref:`examples-image-styles` - Add custom image styles
- :ref:`troubleshooting-index` - Problem solving
