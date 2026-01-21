.. include:: /Includes.rst.txt

.. _integration-configuration-frontend-rendering:

==================
Frontend Rendering
==================

TypoScript configuration for frontend image rendering, CSS classes, lazy loading, and lightbox integration.

.. important::
   **TypoScript Required for Frontend Rendering**

   After installing the extension, you must include the TypoScript for frontend image rendering.

   **Option 1: Static Template (Recommended)**

   #. Go to :guilabel:`WEB > Template` module.
   #. Select your root page.
   #. Edit the template.
   #. In :guilabel:`Includes` tab, add: :guilabel:`CKEditor Image Support (rte_ckeditor_image)`.

   **Option 2: Direct Import**

   Add to your site package TypoScript:

   ..  code-block:: typoscript
       :caption: Import in your site package

       @import 'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript'

   This loads:

   -  Image rendering hooks (:typoscript:`lib.parseFunc_RTE.tags.img`).
   -  Link rendering hooks (:typoscript:`lib.parseFunc_RTE.tags.a`).
   -  HTMLparser configuration for data attribute cleanup.

   Using direct import gives you full control over the TypoScript load order,
   allowing you to override settings (like lightbox configuration) after the import.

.. contents:: Table of Contents
   :depth: 3
   :local:

TypoScript Configuration
========================

Frontend Rendering Setup
-------------------------

The extension provides default configuration. You can customize it:

.. versionchanged:: 13.1.5

   The legacy :php:`ImageRenderingController` and :php:`ImageLinkRenderingController` were replaced
   with unified :php:`ImageRenderingAdapter` using the new service architecture.
   See :ref:`api-services` for details.

..  code-block:: typoscript
    :caption: Frontend rendering configuration

    lib.parseFunc_RTE {
        tags.img = TEXT
        tags.img {
            current = 1
            preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter->renderImageAttributes
        }

        tags.a = TEXT
        tags.a {
            current = 1
            preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter->renderLinkedImageAttributes
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

..  code-block:: typoscript
    :caption: Default CSS class configuration

    lib.parseFunc_RTE {
        nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib.class {
            default = img-fluid responsive-image
        }
    }

Lazyload Configuration
----------------------

Enable native browser lazy loading:

..  code-block:: typoscript
    :caption: Template constants for lazy loading

    styles.content.image.lazyLoading = lazy
    # Options: lazy, eager, auto

Lightbox/Popup Integration
--------------------------

.. versionadded:: 13.1.0
   Default popup configuration is now provided by the extension.

.. note::
   **Optional Site Set Configuration (TYPO3 v13+)**

   The extension provides click-to-enlarge functionality once TypoScript is included.

   **However**, for TYPO3 v13+ best practices, you can optionally include the extension's site set
   to ensure proper loading order with :typoscript:`fluid_styled_content` if you use it:

   **Option 1: Via Backend (Recommended for TYPO3 v13+)**

   #. Go to :guilabel:`Site Management > Sites` module.
   #. Edit your site.
   #. In :guilabel:`Sets` tab, add: :guilabel:`CKEditor Image Support`.
   #. Save.

   **Option 2: Via config.yaml**

   Edit :file:`config/sites/<your-site>/config.yaml`:

   ..  code-block:: yaml
       :caption: Site configuration with extension dependency

       base: 'https://example.com/'
       rootPageId: 1
       dependencies:
         - netresearch/rte-ckeditor-image

   **Option 3: Static Template (Legacy, TYPO3 v12)**

   For TYPO3 v12, you can include the static template:

   #. Go to :guilabel:`WEB > Template` module.
   #. Select your root page.
   #. Edit the template.
   #. In :guilabel:`Includes` tab, add: :guilabel:`CKEditor Image Support (rte_ckeditor_image)`.

   **Why use site sets?** TYPO3 v13 site sets provide proper dependency ordering
   with :typoscript:`fluid_styled_content`, ensuring TypoScript loads in the correct sequence.

The extension provides :typoscript:`lib.contentElement.settings.media.popup` configuration
with sensible defaults for click-to-enlarge functionality. When editors enable
:guilabel:`Enlarge on Click` in the image dialog, images will open in a JavaScript popup window.

**Default Configuration** (included in the static template):

..  code-block:: typoscript
    :caption: Default popup configuration

    lib.contentElement.settings.media.popup {
        # Opens in popup window (800x600 max)
        bodyTag = <body style="margin:0; background:#fff;">
        wrap = <a href="javascript:close();"> | </a>
        width = 800m
        height = 600m
        JSwindow = 1
        JSwindow.newWindow = 1
        directImageLink = 0
    }

**Using with Lightbox Libraries** (PhotoSwipe, GLightbox, etc.):

Override the default configuration to use direct image links with custom CSS classes:

..  code-block:: typoscript
    :caption: Lightbox library configuration

    lib.contentElement.settings.media.popup {
        # Direct link to image for lightbox libraries
        directImageLink = 1

        # Add lightbox-specific classes and attributes
        linkParams.ATagParams.dataWrap = class="lightbox" rel="lightbox-gallery"
    }

**Legacy fluid_styled_content Integration**:

If using :typoscript:`fluid_styled_content` constants, enable lightbox mode:

..  code-block:: typoscript
    :caption: Template constants for lightbox

    styles.content.textmedia.linkWrap.lightboxEnabled = 1

Manual TypoScript Inclusion
===========================

.. versionchanged:: 13.4.0
   TypoScript is no longer automatically loaded. Manual inclusion is required.
   See :ref:`integration-configuration-frontend-rendering` for inclusion options.

The extension requires manual TypoScript inclusion, giving you full control over load order
and the ability to override settings in your site package.

**Include via Static Template:**

#. Go to :guilabel:`WEB > Template` module
#. Select your root page
#. In :guilabel:`Includes` tab, add: :guilabel:`CKEditor Image Support`

**Or import directly in your site package:**

..  code-block:: typoscript
    :caption: Direct import in site package TypoScript

    @import 'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript'

    # Now you can override settings:
    lib.contentElement.settings.media.popup {
        directImageLink = 1
        linkParams.ATagParams.dataWrap = class="lightbox"
    }

.. _typoscript-reference:

TypoScript Reference
====================

Complete TypoScript Configuration Options
------------------------------------------

Image Rendering
~~~~~~~~~~~~~~~

..  code-block:: typoscript
    :caption: Image tag processing

    lib.parseFunc_RTE {
        tags.img = TEXT
        tags.img {
            current = 1
            preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter->renderImageAttributes
        }
    }

Link Rendering
~~~~~~~~~~~~~~

..  code-block:: typoscript
    :caption: Link tag processing for linked images

    lib.parseFunc_RTE {
        tags.a = TEXT
        tags.a {
            current = 1
            preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter->renderLinkedImageAttributes
        }
    }

HTML Parser Configuration
~~~~~~~~~~~~~~~~~~~~~~~~~~

..  code-block:: typoscript
    :caption: HTMLparser attribute cleanup

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

..  code-block:: typoscript
    :caption: Default and allowed CSS classes

    lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib.class {
        default = img-fluid
        list = img-fluid,img-thumbnail,rounded
    }

Lazy Loading
~~~~~~~~~~~~

..  code-block:: typoscript
    :caption: Native browser lazy loading

    # Template Constants
    styles.content.image.lazyLoading = lazy
    # Options: lazy, eager, auto

Lightbox Configuration
~~~~~~~~~~~~~~~~~~~~~~

..  code-block:: typoscript
    :caption: Lightbox template constants

    # Template Constants
    styles.content.textmedia.linkWrap.lightboxEnabled = 1
    styles.content.textmedia.linkWrap.lightboxCssClass = lightbox
    styles.content.textmedia.linkWrap.lightboxRelAttribute = lightbox[{field:uid}]

Image Processing
~~~~~~~~~~~~~~~~

..  code-block:: typoscript
    :caption: Image dimension processing

    lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser.tags.img {
        width =
        height =
        # Allows TYPO3 to process dimensions
    }

Encapsulation Configuration
~~~~~~~~~~~~~~~~~~~~~~~~~~~

..  code-block:: typoscript
    :caption: Image encapsulation in paragraphs

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
