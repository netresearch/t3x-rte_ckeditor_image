.. include:: /Includes.rst.txt

.. _examples-basic-integration:

=================
Basic Integration
=================

Quick start guide for getting basic image functionality working with minimal configuration.

.. contents:: Table of Contents
   :depth: 3
   :local:

Minimal Setup
=============

**Objective**: Get basic image functionality working

Step 1: Install Extension
--------------------------

.. code-block:: bash

   composer require netresearch/rte-ckeditor-image:^13.0

Step 2: Create RTE Configuration
---------------------------------

.. code-block:: yaml
   :caption: EXT:my_site/Configuration/RTE/Default.yaml

   imports:
     - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
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

Step 3: Register Preset
------------------------

.. code-block:: php
   :caption: EXT:my_site/ext_localconf.php

   $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['default']
       = 'EXT:my_site/Configuration/RTE/Default.yaml';

Step 4: Enable in Page TSConfig
--------------------------------

.. code-block:: typoscript
   :caption: Configuration/page.tsconfig

   RTE.default.preset = default

Step 5: Include Static Template
--------------------------------

- Backend → Template → Edit Template
- Include static: "CKEditor Image Support"

**Result**: Basic image insertion working ✅

Related Documentation
=====================

- :ref:`integration-configuration` - Complete configuration guide
- :ref:`examples-image-styles` - Add custom image styles
- :ref:`troubleshooting-index` - Problem solving
