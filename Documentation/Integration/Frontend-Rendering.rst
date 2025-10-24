.. include:: /Includes.rst.txt

.. _integration-configuration-frontend-rendering:

==================
Frontend Rendering
==================

TypoScript configuration for frontend image rendering, CSS classes, lazy loading, and lightbox integration.

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
           # Keep zoom attributes for popup/lightbox rendering (ImageRenderingController.php)
           # data-htmlarea-zoom.unset = 1
           # data-htmlarea-clickenlarge.unset = 1
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

Static Template
===============

Include the static template for default TypoScript:

1. Go to **Template** → **Info/Modify**
2. Edit whole template record
3. Go to **Includes** tab
4. Add ``CKEditor Image Support`` in **Include static (from extensions)**
5. ⚠️ Add it **before** Fluid Styled Content

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
           # Keep zoom attributes for popup/lightbox rendering (ImageRenderingController.php)
           # data-htmlarea-zoom.unset = 1
           # data-htmlarea-clickenlarge.unset = 1
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
