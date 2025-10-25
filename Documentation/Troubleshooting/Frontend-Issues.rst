.. include:: /Includes.rst.txt

.. _troubleshooting-frontend-issues:

=================
Frontend Issues
=================

Solutions for problems with image display and rendering in the frontend.

.. contents:: Table of Contents
   :local:
   :depth: 2

.. _troubleshooting-frontend-rendering:

Image Display Problems
======================

Issue: Images Not Appearing in Frontend
----------------------------------------

**Symptoms:**

* Images visible in backend RTE
* Images missing in frontend output

**Causes:**

1. TypoScript rendering hooks missing (rare with v13.0.0+)
2. Cached content
3. Custom TypoScript overriding automatic configuration

**Solution:**

1. **Verify TypoScript is loaded (v13.0.0+ automatic):**

   The extension automatically loads TypoScript. Verify it's present:

   .. code-block:: typoscript

      lib.parseFunc_RTE {
          tags.img = TEXT
          tags.img {
              current = 1
              preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
          }
      }

2. **Clear Caches:**

   .. code-block:: bash

      ./vendor/bin/typo3 cache:flush

3. **Check for TypoScript conflicts:**

   If you have custom ``lib.parseFunc_RTE`` configuration, ensure it doesn't override the image rendering hooks.

.. note::
   Since v13.0.0, TypoScript is automatically loaded. Manual static template inclusion is optional.

----

Issue: Processed Images Not Generated
--------------------------------------

**Symptoms:**

* Original large images displayed
* No ``_processed_/`` directory created
* Slow page load due to large images

**Causes:**

1. Image processing disabled
2. ImageMagick/GraphicsMagick not configured
3. File permissions issue

**Solution:**

1. **Verify Image Processing Configuration:**

.. code-block:: php

   // LocalConfiguration.php
   $GLOBALS['TYPO3_CONF_VARS']['GFX'] = [
       'processor' => 'ImageMagick',  // or 'GraphicsMagick'
       'processor_path' => '/usr/bin/',
       'processor_enabled' => true,
   ];

2. **Test Image Processing:**

.. code-block:: bash

   # TYPO3 CLI
   ./vendor/bin/typo3 backend:test:imageprocessing

3. **Check Directory Permissions:**

.. code-block:: bash

   # Ensure _processed_/ is writable
   chmod 775 fileadmin/_processed_/

4. **Check Processed Files:**

.. code-block:: bash

   ls -la fileadmin/_processed_/

----

Broken Links and References
============================

Issue: Broken Image Links
--------------------------

**Symptoms:**

* Broken image icon displayed
* 404 errors in browser console
* Missing image files

**Causes:**

1. File moved or deleted
2. Invalid file reference
3. Storage configuration changed

**Solution:**

1. **Verify File Exists:**

.. code-block:: bash

   # Check if file exists in fileadmin
   ls -la fileadmin/path/to/image.jpg

2. **Check File References:**

.. code-block:: sql

   -- Find images in RTE content
   SELECT uid, pid, bodytext
   FROM tt_content
   WHERE bodytext LIKE '%data-htmlarea-file-uid%';

3. **Rebuild Reference Index:**

.. code-block:: bash

   ./vendor/bin/typo3 referenceindex:update

4. **Clear File Caches:**

.. code-block:: bash

   ./vendor/bin/typo3 cache:flush --group=system

----

Issue: Wrong Image Path in Output
----------------------------------

**Symptoms:**

* Image src points to wrong location
* Absolute paths instead of relative
* Missing domain in URLs

**Cause:** Incorrect TypoScript configuration

**Solution:**

.. code-block:: typoscript

   # Ensure proper path generation
   config.absRefPrefix = /
   config.baseURL = https://your-domain.com/

----

Dimension Problems
==================

Issue: Images Display at Wrong Size
------------------------------------

**Symptoms:**

* Images too large or too small
* Dimensions not respected
* Responsive behavior broken

**Causes:**

1. CSS conflicts
2. Missing width/height attributes
3. Responsive image configuration issues

**Solution:**

1. **Check Generated HTML:**

.. code-block:: html

   <!-- Should include width and height -->
   <img src="..." width="800" height="600" />

2. **Verify CSS:**

.. code-block:: css

   /* Ensure images are responsive */
   .rte img {
       max-width: 100%;
       height: auto;
   }

3. **Check TypoScript Configuration:**

.. code-block:: typoscript

   lib.parseFunc_RTE.tags.img {
       current = 1
       preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
   }

----

Issue: Image Dimensions Not Preserved
--------------------------------------

**Symptoms:**

* Aspect ratio distorted
* Images stretched or squashed

**Cause:** Missing or incorrect dimension attributes

**Solution:**

Ensure both width and height are rendered:

.. code-block:: typoscript

   lib.parseFunc_RTE.tags.img {
       current = 1
       preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
       stdWrap {
           wrap = <div class="rte-image">|</div>
       }
   }

----

Style and Class Problems
=========================

Issue: CSS Classes Not Applied
-------------------------------

**Symptoms:**

* Custom classes missing from output
* Styles not visible in frontend
* Classes work in backend but not frontend

**Cause:** HTMLparser configuration stripping classes

**Solution:**

.. code-block:: typoscript

   lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser {
       keepNonMatchedTags = 1
       tags.img {
           allowedAttribs = class,src,alt,title,width,height
           fixAttrib.class.list = your-allowed-classes
       }
   }

----

Issue: Data Attributes Visible in Frontend
-------------------------------------------

**Symptoms:**

* ``data-htmlarea-file-uid`` visible in HTML
* Internal attributes exposed

**Cause:** HTMLparser configuration missing

**Solution:**

.. code-block:: typoscript

   lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib {
       data-htmlarea-file-uid.unset = 1
       data-htmlarea-file-table.unset = 1
       data-htmlarea-zoom.unset = 1
       data-title-override.unset = 1
       data-alt-override.unset = 1
   }

.. note::
   Internal data attributes should always be removed in frontend rendering.

----

Link Rendering Problems
========================

Issue: Image Links Not Working
-------------------------------

**Symptoms:**

* Clickable images don't work
* Links stripped from output
* JavaScript conflicts

**Cause:** Link processing configuration

**Solution:**

.. code-block:: typoscript

   lib.parseFunc_RTE {
       tags.a = TEXT
       tags.a {
           current = 1
           typolink {
               parameter.data = parameters:href
               title.data = parameters:title
               ATagParams.data = parameters:allParams
           }
       }
   }

----

Issue: Lightbox/Zoom Not Working
---------------------------------

**Symptoms:**

* data-htmlarea-zoom attribute present but zoom doesn't work
* Lightbox not triggering

**Cause:** Missing JavaScript or CSS for lightbox

**Solution:**

1. **Ensure Zoom Attribute Rendered:**

.. code-block:: typoscript

   lib.parseFunc_RTE.tags.img {
       current = 1
       preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
   }

2. **Include Lightbox Library:**

Add your preferred lightbox library (e.g., Fancybox, Lightbox2, PhotoSwipe)

3. **Initialize Lightbox:**

.. code-block:: javascript

   // Example with Fancybox
   document.querySelectorAll('img[data-htmlarea-zoom]').forEach(img => {
       const link = document.createElement('a');
       link.href = img.src.replace(/\/_processed_\/.*/, '/' + img.dataset.htmlareaZoom);
       link.setAttribute('data-fancybox', 'gallery');
       img.parentNode.insertBefore(link, img);
       link.appendChild(img);
   });

----

Responsive Image Issues
=======================

Issue: Images Not Responsive
-----------------------------

**Symptoms:**

* Images overflow container on mobile
* Fixed width prevents scaling
* No srcset generated

**Cause:** Missing responsive configuration

**Solution:**

.. code-block:: css

   /* Basic responsive images */
   .rte img {
       max-width: 100%;
       height: auto;
       display: block;
   }

For advanced responsive images with srcset, configure image processing:

.. code-block:: typoscript

   lib.parseFunc_RTE.tags.img {
       current = 1
       preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
   }

----

Caching Issues
==============

Issue: Old Images Still Displayed
----------------------------------

**Symptoms:**

* Updated images not showing
* Old version cached
* Changes visible in backend but not frontend

**Solution:**

1. **Clear TYPO3 Caches:**

.. code-block:: bash

   ./vendor/bin/typo3 cache:flush

2. **Clear Browser Cache:**

   * Hard reload: Ctrl+Shift+R (Windows/Linux)
   * Hard reload: Cmd+Shift+R (Mac)

3. **Clear Processed Images:**

.. code-block:: bash

   # Remove all processed images
   rm -rf fileadmin/_processed_/*

4. **Verify Cache Configuration:**

.. code-block:: typoscript

   config {
       sendCacheHeaders = 1
       cache_period = 86400
   }

----

Debugging Frontend Issues
=========================

Check Generated HTML
--------------------

View page source and inspect image markup:

.. code-block:: html

   <!-- Expected output -->
   <img src="fileadmin/_processed_/.../image.jpg"
        alt="Description"
        width="800"
        height="600"
        class="your-class" />

Verify TypoScript Processing
-----------------------------

.. code-block:: typoscript

   # Enable TypoScript debugging
   config {
       debug = 1
       admPanel = 1
   }

Check Browser Network Tab
--------------------------

1. Open DevTools (F12)
2. Go to Network tab
3. Filter by images
4. Check for:

   * 404 errors
   * Slow loading
   * Wrong paths

Inspect CSS
-----------

.. code-block:: css

   /* Check for conflicts */
   .rte img {
       /* Ensure no display:none or visibility:hidden */
   }

Monitor Console Errors
----------------------

Look for JavaScript errors that might affect image rendering:

.. code-block:: javascript

   // Common issues
   - Failed to load resource
   - CORS errors
   - JavaScript blocking rendering

----

Related Documentation
=====================

**Other Troubleshooting Topics:**

* :ref:`troubleshooting-installation-issues` - Installation and setup problems
* :ref:`troubleshooting-editor-issues` - Editor and backend problems
* :ref:`troubleshooting-performance-issues` - Performance optimization

**Additional Resources:**

* :ref:`integration-configuration-frontend-rendering` - TypoScript configuration
* :ref:`architecture-system-components` - Rendering pipeline details
* :ref:`integration-configuration-frontend-rendering` - Frontend features

Getting Help
============

If issues persist after troubleshooting:

1. **Check GitHub Issues:** https://github.com/netresearch/t3x-rte_ckeditor_image/issues
2. **Review Changelog:** Look for breaking changes in CHANGELOG.md
3. **TYPO3 Slack:** Join `#typo3-cms <https://typo3.slack.com/archives/typo3-cms>`__
4. **Stack Overflow:** Tag questions with ``typo3`` and ``ckeditor``

.. important::
   When reporting issues, include:

   * TYPO3 version
   * Extension version
   * Browser and version
   * Generated HTML (view source)
   * TypoScript configuration
   * Browser console errors
   * Network tab screenshots
