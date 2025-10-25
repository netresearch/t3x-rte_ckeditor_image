.. _api-controllers:

===============
Controllers API
===============

Controllers handle HTTP requests in the backend, providing image selection, information retrieval, and processing capabilities.

.. contents:: Table of Contents
   :depth: 3
   :local:

SelectImageController
=====================

.. _api-selectimagecontroller:

:Namespace: ``Netresearch\RteCKEditorImage\Controller``
:Purpose: Main controller for image selection and processing in the CKEditor context
:Backend Route: ``rteckeditorimage_wizard_select_image`` → ``/rte/wizard/selectimage``

Methods
-------

mainAction()
~~~~~~~~~~~~

.. php:method:: mainAction(ServerRequestInterface $request): ResponseInterface

   Entry point for the image browser/selection interface.

   :param ServerRequestInterface $request: PSR-7 server request with query parameters
   :returns: PSR-7 response with file browser HTML
   :returntype: ResponseInterface

   **Query Parameters:**

   :`mode`: Browser mode (default: ``file`` from route configuration)
   :`bparams`: Browser parameters passed to file browser

   **Usage Example:**

   .. code-block:: javascript

      // Called from CKEditor plugin
      const contentUrl = routeUrl + '&contentsLanguage=en&editorId=123&bparams=' + bparams.join('|');

infoAction()
~~~~~~~~~~~~

.. php:method:: infoAction(ServerRequestInterface $request): ResponseInterface

   Returns JSON with image information and processed variants.

   :param ServerRequestInterface $request: Server request with file identification and processing parameters
   :returns: JSON response with image data
   :returntype: ResponseInterface

   **Query Parameters:**

   :`fileId`: FAL file UID
   :`table`: Database table (usually ``sys_file``)
   :`P[width]`: Desired width (optional)
   :`P[height]`: Desired height (optional)
   :`action`: Action type (``info``)

   **Response Structure:**

   .. code-block:: json

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

   **Usage Example:**

   .. code-block:: javascript

      // From CKEditor plugin
      getImageInfo(editor, 'sys_file', 123, {width: '800', height: '450'})
        .then(function(img) {
          // Use image data
        });

getImage()
~~~~~~~~~~

.. php:method:: getImage(int fileUid, string table): File|null

   Retrieves FAL File object.

   :param int fileUid: File UID from FAL
   :param string table: Database table (sys_file)
   :returns: File object or null if not found
   :returntype: TYPO3\\CMS\\Core\\Resource\\File|null
   :throws: Exception if file cannot be loaded

processImage()
~~~~~~~~~~~~~~

.. php:method:: processImage(File file, array processingInstructions): ProcessedFile|null

   Creates processed image variant with specified dimensions.

   :param File file: Original FAL file
   :param array processingInstructions: Array with ``width``, ``height``, ``crop``, etc.
   :returns: Processed file or null
   :returntype: TYPO3\\CMS\\Core\\Resource\\ProcessedFile|null

   **Processing Instructions:**

   .. code-block:: php

      [
          'width' => '800',
          'height' => '600',
          'crop' => null  // Optional crop configuration
      ]

ImageRenderingController
========================

.. _api-imagerenderingcontroller:

:Namespace: ``Netresearch\RteCKEditorImage\Controller``
:Purpose: Frontend rendering controller for ``<img>`` tags in RTE content

**TypoScript Integration:**

.. code-block:: typoscript

   lib.parseFunc_RTE {
       tags.img = TEXT
       tags.img {
           current = 1
           preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
       }
   }

Methods
-------

renderImageAttributes()
~~~~~~~~~~~~~~~~~~~~~~~

.. php:method:: renderImageAttributes(string $content, array $conf, ContentObjectRenderer $cObj): string

   Processes ``<img>`` tags in RTE content, applying magic images and FAL processing.

   :param string $content: Current HTML content (single ``<img>`` tag)
   :param array $conf: TypoScript configuration
   :param ContentObjectRenderer $cObj: Content object renderer
   :returns: Processed HTML with updated image URL and attributes
   :returntype: string

   **Processing Steps:**

   1. Parse ``data-htmlarea-file-uid`` attribute
   2. Load FAL file from UID
   3. Apply magic image processing (resize, crop)
   4. Generate processed image URL
   5. Remove internal data attributes
   6. Return updated HTML

   **Data Attributes Processed:**

   :data-htmlarea-file-uid: FAL file reference
   :data-htmlarea-file-table: Table name
   :data-htmlarea-zoom: Zoom functionality
   :data-title-override: Title override flag
   :data-alt-override: Alt override flag

ImageLinkRenderingController
=============================

.. _api-imagelinkrenderingcontroller:

:Namespace: ``Netresearch\RteCKEditorImage\Controller``
:Purpose: Handles rendering of images within ``<a>`` tags (linked images)

**TypoScript Integration:**

.. code-block:: typoscript

   lib.parseFunc_RTE {
       tags.a = TEXT
       tags.a {
           current = 1
           preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageLinkRenderingController->renderImages
       }
   }

Methods
-------

renderImages()
~~~~~~~~~~~~~~

.. php:method:: renderImages(string $content, array $conf, ContentObjectRenderer $cObj): string

   Processes ``<img>`` tags within ``<a>`` tags, maintaining link functionality while applying image processing.

   :param string $content: HTML content (complete ``<a>`` tag with nested ``<img>``)
   :param array $conf: TypoScript configuration
   :param ContentObjectRenderer $cObj: Content object renderer
   :returns: Processed HTML with both link and image correctly rendered
   :returntype: string

   **Usage Scenario:**

   .. code-block:: html

      <!-- Input -->
      <a href="page-link">
        <img data-htmlarea-file-uid="123" src="..." />
      </a>

      <!-- Output -->
      <a href="page-link">
        <img src="/fileadmin/_processed_/image_hash.jpg" width="800" height="600" />
      </a>

Service Configuration
=====================

All controllers are configured in ``Configuration/Services.yaml``:

.. code-block:: yaml

   Netresearch\RteCKEditorImage\Controller\SelectImageController:
     tags: ['backend.controller']

Controllers use constructor injection for dependencies like ``ResourceFactory``.

Usage Examples
==============

Calling Image Info from JavaScript
-----------------------------------

.. code-block:: javascript

   function getImageInfo(editor, table, uid, params) {
       let url = editor.config.get('style').typo3image.routeUrl
           + '&action=info&fileId=' + encodeURIComponent(uid)
           + '&table=' + encodeURIComponent(table);

       if (params.width) {
           url += '&P[width]=' + params.width;
       }
       if (params.height) {
           url += '&P[height]=' + params.height;
       }

       return $.getJSON(url);
   }

TypoScript Configuration
------------------------

.. code-block:: typoscript

   lib.parseFunc_RTE {
       tags.img = TEXT
       tags.img {
           current = 1
           preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
       }

       nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib {
           # Remove internal attributes from frontend output
           data-htmlarea-file-uid.unset = 1
           data-htmlarea-file-table.unset = 1
           data-htmlarea-zoom.unset = 1
           data-title-override.unset = 1
           data-alt-override.unset = 1
       }
   }

Related Documentation
=====================

- :ref:`Architecture Overview <architecture-overview>`
- :ref:`Data Flow <architecture-design-patterns>`
- :ref:`TypoScript Configuration <typoscript-reference>`
