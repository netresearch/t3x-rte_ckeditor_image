.. include:: /Includes.rst.txt

.. _integration-configuration-frontend-rendering:

==================
Frontend Rendering
==================

TypoScript configuration for frontend image rendering, CSS classes, lazy loading, and lightbox integration.

.. important::
   **Zero-Configuration Installation (v13.0.0+)**

   The extension automatically loads TypoScript for frontend rendering via ``ext_localconf.php``:

   .. code-block:: bash

      composer require netresearch/rte-ckeditor-image

   **No manual static template inclusion needed!** The extension automatically loads:

   - ✅ Image rendering hooks (``lib.parseFunc_RTE.tags.img``)
   - ✅ Link rendering hooks (``lib.parseFunc_RTE.tags.a``)
   - ✅ HTMLparser configuration for data attribute cleanup

   This section is for **advanced users** who need to customize the default frontend rendering behavior.

.. contents:: Table of Contents
   :depth: 3
   :local:

TypoScript Configuration
========================

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

Automatic TypoScript Loading
=============================

.. versionadded:: 13.0.0
   TypoScript is now loaded automatically via ``ext_localconf.php``.
   Manual static template inclusion is no longer required.

The extension automatically loads frontend rendering configuration:

.. code-block:: php

   ExtensionManagementUtility::addTypoScript(
       'rte_ckeditor_image',
       'setup',
       '@import "EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript"'
   );

.. note::
   The static template ``CKEditor Image Support`` is still available for backward compatibility,
   but is optional with automatic loading enabled.

.. _typoscript-reference:

TypoScript Reference
====================

Complete TypoScript Configuration Options
------------------------------------------

Image Rendering
~~~~~~~~~~~~~~~

.. code-block:: typoscript

   lib.parseFunc_RTE {
       tags.img = TEXT
       tags.img {
           current = 1
           preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
       }
   }

Link Rendering
~~~~~~~~~~~~~~

.. code-block:: typoscript

   lib.parseFunc_RTE {
       tags.a = TEXT
       tags.a {
           current = 1
           preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageLinkRenderingController->renderImages
       }
   }

HTML Parser Configuration
~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: typoscript

   lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser.tags.img {
       fixAttrib {
           # Remove internal data attributes
           data-htmlarea-file-uid.unset = 1
           data-htmlarea-file-table.unset = 1
           data-htmlarea-zoom.unset = 1
           data-htmlarea-clickenlarge.unset = 1
           data-title-override.unset = 1
           data-alt-override.unset = 1
       }
   }

Default CSS Classes
~~~~~~~~~~~~~~~~~~~

.. code-block:: typoscript

   lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib.class {
       default = img-fluid
       list = img-fluid,img-thumbnail,rounded
   }

Lazy Loading
~~~~~~~~~~~~

.. code-block:: typoscript

   # Template Constants
   styles.content.image.lazyLoading = lazy
   # Options: lazy, eager, auto

Lightbox Configuration
~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: typoscript

   # Template Constants
   styles.content.textmedia.linkWrap.lightboxEnabled = 1
   styles.content.textmedia.linkWrap.lightboxCssClass = lightbox
   styles.content.textmedia.linkWrap.lightboxRelAttribute = lightbox[{field:uid}]

Image Processing
~~~~~~~~~~~~~~~~

.. code-block:: typoscript

   lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser.tags.img {
       width =
       height =
       # Allows TYPO3 to process dimensions
   }

Encapsulation Configuration
~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: typoscript

   lib.parseFunc_RTE.nonTypoTagStdWrap.encapsLines {
       encapsTagList := addToList(img)
       remapTag.img = p
   }

Related Documentation
=====================

Configuration Topics
--------------------

- :ref:`integration-configuration-rte-setup` - RTE configuration and basic setup
- :ref:`integration-configuration-tsconfig` - Page TSConfig settings and upload configuration
- :ref:`integration-configuration-advanced` - Advanced configuration, styles, and best practices

General Documentation
---------------------

- :ref:`integration-configuration` - Main configuration guide overview
- :ref:`quick-start` - Quick start guide
- :ref:`troubleshooting-index` - Troubleshooting guide
