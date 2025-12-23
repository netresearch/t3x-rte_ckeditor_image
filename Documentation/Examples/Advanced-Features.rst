.. include:: /Includes.rst.txt

.. _examples-advanced-features:

=================
Advanced Features
=================

Examples for implementing lightbox functionality and lazy loading for performance optimization.

.. contents:: Table of Contents
   :depth: 3
   :local:

Lightbox Integration
====================

.. versionchanged:: 13.1.0
   Default popup configuration is now provided automatically.
   The basic "Enlarge on Click" feature works out-of-the-box without additional setup.
   See :ref:`integration-configuration-frontend-rendering` for details.

PhotoSwipe Lightbox
-------------------

**Objective**: Integrate PhotoSwipe lightbox library for advanced gallery features

.. note::
   For basic click-to-enlarge functionality, the extension provides default popup configuration.
   PhotoSwipe integration is optional for advanced features like galleries, thumbnails, and touch gestures.

Install PhotoSwipe
~~~~~~~~~~~~~~~~~~

.. code-block:: bash

   npm install photoswipe

TypoScript Setup
~~~~~~~~~~~~~~~~

.. code-block:: typoscript

   page {
       includeJSFooterlibs {
           photoswipe = EXT:my_site/Resources/Public/JavaScript/photoswipe.min.js
           photoswipe_init = EXT:my_site/Resources/Public/JavaScript/lightbox-init.js
       }

       includeCSS {
           photoswipe = EXT:my_site/Resources/Public/Css/photoswipe.css
       }
   }

   lib.parseFunc_RTE {
       tags.img = TEXT
       tags.img {
           current = 1
           preUserFunc = MyVendor\MySite\UserFunc\LightboxImageRenderer->render
       }
   }

PHP Wrapper
~~~~~~~~~~~

.. code-block:: php
   :caption: EXT:my_site/Classes/UserFunc/LightboxImageRenderer.php

   namespace MyVendor\MySite\UserFunc;

   use TYPO3\CMS\Core\Resource\FileRepository;
   use TYPO3\CMS\Core\Utility\GeneralUtility;
   use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

   class LightboxImageRenderer
   {
       public function render(
           string $content,
           array $conf,
           ContentObjectRenderer $cObj
       ): string {
           // Check if zoom enabled
           if (strpos($content, 'data-htmlarea-zoom') === false) {
               return $content;
           }

           // Extract file UID
           if (!preg_match('/data-htmlarea-file-uid="(\d+)"/', $content, $match)) {
               return $content;
           }

           $fileUid = (int)$match[1];
           $fileRepository = GeneralUtility::makeInstance(FileRepository::class);

           try {
               $file = $fileRepository->findByUid($fileUid);
           } catch (\Exception $e) {
               return $content;
           }

           // Remove data attributes for frontend
           $content = preg_replace('/\s*data-htmlarea-[^=]+="[^"]*"/', '', $content);

           // Wrap in lightbox link
           $lightboxLink = sprintf(
               '<a href="%s" data-pswp-width="%d" data-pswp-height="%d" target="_blank">%s</a>',
               $file->getPublicUrl(),
               $file->getProperty('width'),
               $file->getProperty('height'),
               $content
           );

           return $lightboxLink;
       }
   }

JavaScript Initialization
~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: javascript
   :caption: EXT:my_site/Resources/Public/JavaScript/lightbox-init.js

   import PhotoSwipeLightbox from 'photoswipe/lightbox';
   import 'photoswipe/style.css';

   const lightbox = new PhotoSwipeLightbox({
       gallery: '.ce-bodytext',
       children: 'a[data-pswp-width]',
       pswpModule: () => import('photoswipe'),
   });

   lightbox.init();

**Result**: Click-to-enlarge images with lightbox ✅

Lazy Loading
============

Native Lazy Loading
-------------------

**Objective**: Improve page load performance with native lazy loading

TypoScript Setup
~~~~~~~~~~~~~~~~

.. code-block:: typoscript

   lib.parseFunc_RTE {
       nonTypoTagStdWrap.HTMLparser.tags.img {
           fixAttrib {
               loading {
                   set = lazy
               }
               # Remove internal attributes
               data-htmlarea-file-uid.unset = 1
               data-htmlarea-file-table.unset = 1
               # Keep zoom attributes for popup/lightbox rendering
               # data-htmlarea-zoom.unset = 1
           }
       }
   }

Result HTML
~~~~~~~~~~~

.. code-block:: html

   <img src="..." loading="lazy" alt="..." />

Intersection Observer Fallback
-------------------------------

**For older browsers**:

TypoScript
~~~~~~~~~~

.. code-block:: typoscript

   page.includeJSFooterlibs.lazyload = EXT:my_site/Resources/Public/JavaScript/lazyload.js

   lib.parseFunc_RTE {
       nonTypoTagStdWrap.HTMLparser.tags.img {
           fixAttrib {
               class {
                   list = lazyload
               }
               data-src {
                   # Copy src to data-src
                   stdWrap.field = src
               }
               src {
                   # Set placeholder
                   set = data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 3 2'%3E%3C/svg%3E
               }
           }
       }
   }

JavaScript
~~~~~~~~~~

.. code-block:: javascript
   :caption: EXT:my_site/Resources/Public/JavaScript/lazyload.js

   document.addEventListener('DOMContentLoaded', function() {
       const imageObserver = new IntersectionObserver((entries, observer) => {
           entries.forEach(entry => {
               if (entry.isIntersecting) {
                   const img = entry.target;
                   img.src = img.dataset.src;
                   img.classList.remove('lazyload');
                   imageObserver.unobserve(img);
               }
           });
       });

       document.querySelectorAll('img.lazyload').forEach(img => {
           imageObserver.observe(img);
       });
   });

**Result**: Progressive image loading ✅

Related Documentation
=====================

- :ref:`examples-responsive-images` - Responsive image implementation
- :ref:`examples-custom-extensions` - Custom dialog and extensions
- :ref:`integration-configuration` - Configuration guide
