.. include:: /Includes.rst.txt

.. _troubleshooting-editor-issues:

==============
Editor Issues
==============

Solutions for problems encountered in the TYPO3 backend editor and CKEditor functionality.

.. important::
   **Backend vs Frontend Issues**

   The **backend RTE** works automatically after ``composer require netresearch/rte-ckeditor-image``.

   For **frontend rendering issues**, ensure you've included the TypoScript.
   See :ref:`integration-configuration-frontend-rendering`.

   If you're experiencing backend issues, check:

   1. Clear caches: ``./vendor/bin/typo3 cache:flush``
   2. Verify extension is activated
   3. Check browser console for JavaScript errors

.. contents:: Table of Contents
   :local:
   :depth: 2

Image Button Problems
=====================

.. _troubleshooting-image-button-missing:

Issue: Image Button Not Visible in Toolbar
-------------------------------------------

**Symptoms:**

* Insert image button missing from CKEditor toolbar
* RTE loads but no image functionality

**Causes:**

1. Plugin not properly imported in RTE configuration
2. ``removePlugins`` includes image plugin
3. Toolbar configuration missing ``insertimage`` item

**Solution:**

.. code-block:: yaml

   # Configuration/RTE/Default.yaml
   imports:
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

   editor:
     config:
       removePlugins: null  # Critical: Don't remove image plugin
       toolbar:
         items:
           - insertimage  # Add to toolbar

----

Style Dropdown Problems
=======================

.. _troubleshooting-style-dropdown:

Issue: Style Drop-Down Not Working with Images
-----------------------------------------------

**Symptoms:**

* Styles disabled when image selected
* Style changes not applied to images

**Cause:** Missing ``GeneralHtmlSupport`` dependency (fixed in v13.0.0+)

**Solution:** Ensure you're using extension version 13.0.0 or higher:

.. code-block:: bash

   composer require netresearch/rte-ckeditor-image:^13.0

The plugin now requires:

.. code-block:: javascript

   static get requires() {
       return ['StyleUtils', 'GeneralHtmlSupport'];  // Both mandatory
   }

.. important::
   The ``GeneralHtmlSupport`` dependency is critical for style functionality in v13.0.0+

----

Issue: Custom Image Styles Lost After Upgrade
----------------------------------------------

**Symptoms:**

* Custom styles no longer available
* Style drop-down empty

**Cause:** RTE configuration changed

**Solution:** Re-apply custom styles in RTE configuration:

.. code-block:: yaml

   editor:
     config:
       style:
         definitions:
           - name: 'Your Custom Style'
             element: 'img'
             classes: ['your-class']

----

File Browser Issues
===================

.. _troubleshooting-file-browser:

Issue: File Browser Empty or Not Loading
-----------------------------------------

**Symptoms:**

* Modal opens but shows no files
* File browser stuck loading

**Causes:**

1. No file mount configured for backend user
2. Missing file permissions
3. Empty fileadmin directory

**Solution:**

.. code-block:: typoscript

   # User TSConfig
   options.defaultUploadFolder = 1:fileadmin/user_upload/

Verify backend user has file mount in:
**Backend** → **User Management** → **Backend Users** → **File Mounts**

----

Issue: "File Not Found" After Selection
----------------------------------------

**Symptoms:**

* Image selected but error occurs
* Empty image inserted

**Causes:**

1. File reference invalid
2. Storage not accessible
3. File deleted from filesystem

**Solution:**

1. Verify file exists in ``fileadmin/``
2. Check file permissions (readable by web server)
3. Clear file abstraction layer cache:

.. code-block:: bash

   ./vendor/bin/typo3 cache:flush --group=system

----

Image Dimension Problems
=========================

Issue: Magic Image Maximum Dimensions Not Working
--------------------------------------------------

**Symptoms:**

* Images not respecting configured maxWidth/maxHeight
* Large images not being resized

**Cause:** TSConfig settings in custom template extension not loaded (TYPO3 bug #87068)

**Solution:** Add settings to root page config instead:

.. code-block:: typoscript

   # In root page TSConfig (not template extension)
   RTE.default.buttons.image.options.magic {
       maxWidth = 1920
       maxHeight = 9999
   }

.. tip::
   Place TSConfig settings in root page configuration for proper loading.

----

JavaScript/CKEditor Errors
===========================

Issue: JavaScript Console Errors
---------------------------------

**Symptoms:**

* Browser console shows errors
* Editor doesn't load properly

Common Errors
^^^^^^^^^^^^^

1. "GeneralHtmlSupport is not defined"
""""""""""""""""""""""""""""""""""""""

**Cause:** Extension version < 13.0.0

**Solution:** Update to latest version:

.. code-block:: bash

   composer update netresearch/rte-ckeditor-image

2. "Cannot read property 'typo3image' of undefined"
""""""""""""""""""""""""""""""""""""""""""""""""""""

**Cause:** Plugin configuration not loaded

**Solution:** Verify ``Configuration/RTE/Plugin.yaml`` imported:

.. code-block:: yaml

   imports:
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

----

Issue: Double-Click on Image Does Nothing
------------------------------------------

**Symptoms:**

* Double-clicking image doesn't open dialog
* Edit functionality not working

**Causes:**

1. DoubleClickObserver not registered
2. JavaScript error blocking execution
3. Image not recognized as typo3image

**Solution:**

1. Check browser console for JavaScript errors
2. Verify image has ``data-htmlarea-file-uid`` attribute
3. Clear browser cache and reload
4. Check CKEditor version compatibility (requires CKEditor 5)

----

Editor Loading Problems
=======================

Issue: Editor Not Initializing
-------------------------------

**Symptoms:**

* CKEditor doesn't load
* Textarea remains plain text field

**Causes:**

1. JavaScript errors preventing initialization
2. RTE configuration not loaded
3. Browser cache issues

**Solution:**

1. Check browser console for errors
2. Verify RTE preset is enabled:

.. code-block:: yaml

   # Configuration/RTE/Default.yaml
   imports:
     - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

3. Clear browser and TYPO3 caches:

.. code-block:: bash

   ./vendor/bin/typo3 cache:flush

4. Force reload in browser (Ctrl+Shift+R)

----

Plugin Configuration Issues
===========================

Issue: Plugin Not Recognized
-----------------------------

**Symptoms:**

* Image plugin functionality missing
* Console error about undefined plugin

**Cause:** Plugin configuration not properly loaded

**Solution:** Ensure proper import order:

.. code-block:: yaml

   # Configuration/RTE/Default.yaml
   imports:
     # Base CKEditor configuration first
     - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
     # Then image plugin
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

   editor:
     config:
       # Ensure plugin is not removed
       removePlugins: null
       toolbar:
         items:
           - insertimage

----

Debugging Editor Issues
=======================

Enable RTE Debugging
--------------------

.. code-block:: typoscript

   # Page TSConfig
   RTE.default.showButtons = *
   RTE.default.hideButtons =

Check Loaded Configuration
---------------------------

Browser console:

.. code-block:: javascript

   // Check if plugin loaded
   console.log(CKEDITOR.instances);

   // Inspect editor config
   const editor = Object.values(CKEDITOR.instances)[0];
   console.log(editor.config);

Monitor Network Requests
-------------------------

1. Open browser DevTools
2. Go to Network tab
3. Trigger image selection
4. Check for failed requests to:

   * ``/rte/wizard/selectimage``
   * Backend image info API

Inspect DOM Elements
--------------------

Check if images have required attributes:

.. code-block:: javascript

   // In browser console
   document.querySelectorAll('img[data-htmlarea-file-uid]');

----

.. _troubleshooting-known-limitations:

Known Limitations
=================

.. _troubleshooting-figcaption-line-breaks:

Figcaption Line Breaks
----------------------

**Symptoms:**

* Pressing Shift+Enter in figcaption does not insert a line break
* Adding ``<br>`` in source mode is removed after saving
* Caption text always appears on a single line

**Cause:** CKEditor 5's ``figcaption`` content model only supports inline
text — it does not allow ``<br>`` tags or block-level elements. The editor
strips unsupported elements during content serialization. This is a CKEditor 5
limitation, not a bug in this extension.

**Workaround:** Captions wrap naturally based on the figure container width.
For multi-line visual appearance, use CSS to control the caption width:

.. code-block:: css

   figure figcaption {
       max-width: 300px; /* Caption wraps at this width */
   }

.. note::
   This limitation applies to all CKEditor 5 figcaptions, not just images
   from this extension. See `CKEditor 5 image captions documentation
   <https://ckeditor.com/docs/ckeditor5/latest/features/images/images-captions.html>`__
   for details.

----

Related Documentation
=====================

**Other Troubleshooting Topics:**

* :ref:`troubleshooting-image-reference-validation` - Validate and fix broken image references
* :ref:`troubleshooting-installation-issues` - Installation and setup problems
* :ref:`troubleshooting-frontend-issues` - Frontend rendering issues
* :ref:`troubleshooting-performance-issues` - Performance optimization

**Additional Resources:**

* :ref:`integration` - Configuration guide
* :ref:`ckeditor-plugin-development` - CKEditor plugin architecture
* :ref:`integration-configuration-rte-setup` - RTE setup and image selection

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
   * PHP version
   * Browser console errors
   * RTE configuration (sanitized)
   * Steps to reproduce
