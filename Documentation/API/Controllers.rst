.. include:: /Includes.rst.txt

.. _api-controllers:

===============
Controllers API
===============

Controllers handle HTTP requests in the backend and bridge TypoScript to the service architecture.

.. contents:: Table of contents
   :depth: 3
   :local:

.. versionchanged:: 13.1.5

   The legacy :php:`ImageRenderingController` and :php:`ImageLinkRenderingController` were removed
   and replaced with :php:`ImageRenderingAdapter` using the new service architecture.
   See :ref:`api-services` for the new service-based approach.

SelectImageController
=====================

.. _api-selectimagecontroller:

..  php:class:: Netresearch\RteCKEditorImage\Controller\SelectImageController

    Main controller for image selection and processing in the CKEditor context.

    :Backend Route: `rteckeditorimage_wizard_select_image` → `/rte/wizard/selectimage`

Methods
-------

mainAction()
~~~~~~~~~~~~

..  php:method:: mainAction(ServerRequestInterface $request): ResponseInterface

    Entry point for the image browser/selection interface.

    :param ServerRequestInterface $request: PSR-7 server request with query parameters.
    :returns: PSR-7 response with file browser HTML.
    :returntype: ResponseInterface

**Query parameters:**

-  `mode`: Browser mode (default: `file` from route configuration).
-  `bparams`: Browser parameters passed to file browser.

**Usage example:**

..  code-block:: javascript
    :caption: CKEditor plugin integration

    // Called from CKEditor plugin
    const contentUrl = routeUrl + '&contentsLanguage=en&editorId=123&bparams=' + bparams.join('|');

infoAction()
~~~~~~~~~~~~

..  php:method:: infoAction(ServerRequestInterface $request): ResponseInterface

    Returns JSON with image information and processed variants.

    :param ServerRequestInterface $request: Server request with file identification and processing parameters.
    :returns: JSON response with image data.
    :returntype: ResponseInterface

**Query parameters:**

-  `fileId`: FAL file UID.
-  `table`: Database table (usually `sys_file`).
-  `P[width]`: Desired width (optional).
-  `P[height]`: Desired height (optional).
-  `action`: Action type (`info`).

**Response structure:**

..  code-block:: json
    :caption: Image info API response

    {
      "uid": 123,
      "url": "/fileadmin/user_upload/image.jpg",
      "width": 1920,
      "height": 1080,
      "title": "Image title",
      "alt": "Alternative text",
      "processed": {
        "url": "/fileadmin/_processed_/image_hash.jpg",
        "width": 800,
        "height": 450
      },
      "lang": {
        "override": "Override %s",
        "overrideNoDefault": "Override (no default)",
        "zoom": "Zoom",
        "cssClass": "CSS Class"
      }
    }

ImageRenderingAdapter
=====================

.. _api-imagerenderingadapter:

.. versionadded:: 13.1.5

   Replaces the legacy :php:`ImageRenderingController` and :php:`ImageLinkRenderingController`.

..  php:class:: Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter

    TypoScript adapter bridging `preUserFunc` to the modern service architecture.

The adapter serves as a thin layer between TypoScript's `preUserFunc` interface and the
service-based architecture. It delegates actual processing to:

-  :ref:`ImageAttributeParser <api-imageattributeparser>` - HTML parsing.
-  :ref:`ImageResolverService <api-imageresolverservice>` - Business logic and security.
-  :ref:`ImageRenderingService <api-imagerenderingservice>` - Fluid template rendering.

Methods
-------

renderImageAttributes()
~~~~~~~~~~~~~~~~~~~~~~~

..  php:method:: renderImageAttributes($content, $conf)

    Processes :html:`<img>` tags in RTE content using the service pipeline.

    :param string $content: Current HTML content (single :html:`<img>` tag).
    :param array $conf: TypoScript configuration.
    :returns: Processed HTML with updated image URL and attributes.
    :returntype: string

**Processing pipeline:**

#. :php:`ImageAttributeParser` extracts data attributes from HTML.
#. :php:`ImageResolverService` resolves FAL file, applies security checks, processes image.
#. :php:`ImageRenderingService` renders via Fluid template.

**TypoScript integration:**

..  code-block:: typoscript
    :caption: Image tag processing configuration

    lib.parseFunc_RTE {
        tags.img = TEXT
        tags.img {
            current = 1
            preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter->renderImageAttributes
        }
    }

renderLinkedImageAttributes()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

..  php:method:: renderLinkedImageAttributes($content, $conf)

    Processes :html:`<img>` tags within :html:`<a>` tags (linked images).

    :param string $content: HTML content (complete :html:`<a>` tag with nested :html:`<img>`).
    :param array $conf: TypoScript configuration.
    :returns: Processed HTML with both link and image correctly rendered.
    :returntype: string

**TypoScript integration:**

..  code-block:: typoscript
    :caption: Linked image processing configuration

    lib.parseFunc_RTE {
        tags.a = TEXT
        tags.a {
            current = 1
            preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter->renderLinkedImageAttributes
        }
    }

Service configuration
=====================

All controllers are configured in :file:`Configuration/Services.yaml`:

..  code-block:: yaml
    :caption: EXT:rte_ckeditor_image/Configuration/Services.yaml

    Netresearch\RteCKEditorImage\Controller\SelectImageController:
      tags: ['backend.controller']

    Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter:
      public: true

Controllers use constructor injection for dependencies.

Migration from legacy controllers
=================================

.. versionchanged:: 13.1.5

If you were extending the legacy controllers via XCLASS, migrate to:

#. **Template overrides** (recommended): Override Fluid templates in your site package.
   See :ref:`examples-template-overrides`.

#. **Service decoration**: Decorate :php:`ImageResolverService` or :php:`ImageRenderingService`.
   See :ref:`api-services`.

The TypoScript interface remains 100% backward compatible - no changes required for standard usage.

Related documentation
=====================

-  :ref:`Services API <api-services>` - New service architecture.
-  :ref:`DTOs <api-dtos>` - Data transfer objects.
-  :ref:`Template Overrides <examples-template-overrides>` - Customizing output.
