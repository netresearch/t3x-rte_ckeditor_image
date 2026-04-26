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

1. TypoScript not included (most common)
2. Cached content
3. Custom TypoScript overriding extension configuration

**Solution:**

1. **Include TypoScript (required):**

   The extension requires manual TypoScript inclusion for frontend rendering:

   **Option A: Static Template**

   #. Go to :guilabel:`WEB > Template` module
   #. Select your root page, edit the template
   #. In :guilabel:`Includes` tab, add: :guilabel:`CKEditor Image Support`

   **Option B: Direct Import**

   .. code-block:: typoscript

      @import 'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript'

2. **Clear Caches:**

   .. code-block:: bash

      ./vendor/bin/typo3 cache:flush

3. **Check for TypoScript conflicts:**

   If you have custom ``lib.parseFunc_RTE`` configuration, ensure it doesn't override the image rendering hooks.

.. important::
   TypoScript must be manually included. See :ref:`integration-configuration-frontend-rendering` for details.

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

1. **Run the image reference validator** (recommended):

.. code-block:: bash

   # Dry-run: detect broken references
   ./vendor/bin/typo3 rte_ckeditor_image:validate

   # Apply fixes automatically
   ./vendor/bin/typo3 rte_ckeditor_image:validate --fix

See :ref:`troubleshooting-image-reference-validation` for full details, including
the Upgrade Wizard alternative.

2. **Rebuild Reference Index** (required before validation on fresh installs):

.. code-block:: bash

   ./vendor/bin/typo3 referenceindex:update

3. **Clear File Caches:**

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
       preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter->renderImageAttributes
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
       preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter->renderImageAttributes
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
       # Keep zoom attributes for popup/lightbox rendering
       # data-htmlarea-zoom.unset = 1
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

.. important::
   **This extension provides zoom markup only, not JavaScript.**

   When an editor selects "Enlarge" in the image dialog, the extension outputs an
   ``<a>`` link wrapping the ``<img>`` that points to the full-size image. It does
   **not** include any JavaScript lightbox library. You must provide one yourself
   or enable the built-in TYPO3 popup.

**Symptoms:**

* Clicking an image with zoom enabled does nothing or navigates away
* ``data-htmlarea-zoom`` attribute present in HTML but no lightbox appears
* Image opens in a new browser window instead of a lightbox overlay

**Solution for fluid_styled_content users:**

If you use ``fluid_styled_content``, enable the built-in lightbox via TypoScript constants:

.. code-block:: typoscript

   styles.content.textmedia.linkWrap.lightboxEnabled = 1

This activates TYPO3's built-in popup window for enlarged images.

**Solution for custom setups (minimal lightbox):**

If you do not use ``fluid_styled_content`` or want a modern lightbox overlay,
include a lightweight JavaScript snippet in your site package:

.. code-block:: javascript
   :caption: Minimal lightbox initialization

   document.addEventListener('DOMContentLoaded', function () {
       document.querySelectorAll('a[data-popup="true"]').forEach(function (link) {
           link.addEventListener('click', function (e) {
               e.preventDefault();
               var overlay = document.createElement('div');
               overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.8);'
                   + 'display:flex;align-items:center;justify-content:center;z-index:9999;cursor:pointer;';
               var img = document.createElement('img');
               img.src = link.href;
               img.style.cssText = 'max-width:90vw;max-height:90vh;';
               overlay.appendChild(img);
               overlay.addEventListener('click', function () { overlay.remove(); });
               document.body.appendChild(overlay);
           });
       });
   });

For production use, consider a proper lightbox library such as
`GLightbox <https://github.com/biati-digital/glightbox>`__,
`PhotoSwipe <https://photoswipe.com/>`__, or
`Fancybox <https://fancyapps.com/fancybox/>`__.

.. seealso::
   :ref:`integration-configuration-frontend-rendering` for the full lightbox/popup
   TypoScript configuration reference.

----

Issue: Unexpected `rel="noreferrer"` on External Linked Images
---------------------------------------------------------------

**Symptoms:**

* External linked images (e.g. `<a href="https://example.com" target="_blank">`)
  render with `rel="noreferrer"` even though the editor did not set it
* Internal links (`/page` or `t3://page?uid=42`) do **not** receive the
  attribute

**Cause:** This is intentional behaviour added in `#799 <https://github.com/netresearch/t3x-rte_ckeditor_image/issues/799>`__.
The Fluid `Link.html` partial used for figure-wrapped linked images
mirrors TYPO3 typolink security semantics — it appends `rel="noreferrer"`
when the target opens a new browsing context **and** the URL is external
(absolute `http(s)` or protocol-relative). Pre-existing rel tokens from
the source `<a>` (such as `nofollow` or `noopener`) are preserved.

**Solution:** No action required. To suppress the attribute (not
recommended), remove `target="_blank"` from the link or use an internal
URL. See :ref:`integration-security-rel` for the full rule and rationale.

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
       preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingAdapter->renderImageAttributes
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

* :ref:`troubleshooting-image-reference-validation` - Validate and fix broken image references
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
