.. include:: /Includes.rst.txt

.. _troubleshooting-installation-issues:

========================
Installation Issues
========================

Solutions for problems encountered during extension installation, configuration, and setup.

.. contents:: Table of Contents
   :local:
   :depth: 2

Extension Installation Problems
================================

Issue: Extension Not Working After TYPO3 13 Upgrade
----------------------------------------------------

**Symptoms:**

* Extension installed but not functional
* Errors about missing classes

**Solution:** Ensure correct version compatibility:

.. code-block:: json

   {
     "require": {
       "typo3/cms-core": "^13.4",
       "netresearch/rte-ckeditor-image": "^13.0"
     }
   }

.. code-block:: bash

   composer update
   ./vendor/bin/typo3 cache:flush
   ./vendor/bin/typo3 extension:setup

.. warning::
   TYPO3 13 requires extension version 13.0.0 or higher for compatibility.

----

Dependency Conflicts
====================

Issue: Style Drop-Down Dependency Error
----------------------------------------

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

Issue: JavaScript Dependency Errors
------------------------------------

**Symptoms:**

* Browser console shows "GeneralHtmlSupport is not defined"
* Editor doesn't load properly

**Cause:** Extension version < 13.0.0

**Solution:** Update to latest version:

.. code-block:: bash

   composer update netresearch/rte-ckeditor-image

----

Cache-Related Issues
====================

Issue: Configuration Changes Not Applied
-----------------------------------------

**Symptoms:**

* RTE configuration changes don't take effect
* Old behavior persists after updates

**Solution:** Clear all caches:

.. code-block:: bash

   # Clear all TYPO3 caches
   ./vendor/bin/typo3 cache:flush

   # Clear system cache specifically
   ./vendor/bin/typo3 cache:flush --group=system

   # Clear browser cache and reload

.. tip::
   Always clear caches after modifying RTE configuration files.

----

Issue: File References Not Updated
-----------------------------------

**Symptoms:**

* Image selected but error occurs
* Empty image inserted
* "File Not Found" errors

**Solution:** Clear file abstraction layer cache:

.. code-block:: bash

   ./vendor/bin/typo3 cache:flush --group=system

**Additional Steps:**

1. Verify file exists in ``fileadmin/``
2. Check file permissions (readable by web server)
3. Rebuild reference index:

.. code-block:: bash

   ./vendor/bin/typo3 referenceindex:update

----

Permission Problems
===================

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

Issue: Processed Images Directory Not Writable
-----------------------------------------------

**Symptoms:**

* Original large images displayed
* No ``_processed_/`` directory created
* Slow page load due to large images

**Solution:** Check directory permissions:

.. code-block:: bash

   # Ensure _processed_/ is writable
   chmod 775 fileadmin/_processed_/

   # Verify ownership
   chown www-data:www-data fileadmin/_processed_/

----

Static Template Configuration
==============================

Issue: Static Template Not Included
------------------------------------

**Symptoms:**

* Images visible in backend RTE
* Images missing in frontend output

**Solution:**

1. **Include Static Template:**

   * Go to **Template** → **Info/Modify**
   * Edit whole template record
   * Include ``CKEditor Image Support`` before Fluid Styled Content

2. **Verify TypoScript:**

.. code-block:: typoscript

   lib.parseFunc_RTE {
       tags.img = TEXT
       tags.img {
           current = 1
           preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
       }
   }

.. warning::
   Always include the static template BEFORE Fluid Styled Content for proper rendering.

----

Image Processing Configuration
===============================

Issue: ImageMagick/GraphicsMagick Not Configured
-------------------------------------------------

**Symptoms:**

* Original large images displayed instead of processed versions
* Image processing test fails

**Solution:** Verify image processing configuration:

.. code-block:: php

   // LocalConfiguration.php
   $GLOBALS['TYPO3_CONF_VARS']['GFX'] = [
       'processor' => 'ImageMagick',  // or 'GraphicsMagick'
       'processor_path' => '/usr/bin/',
       'processor_enabled' => true,
   ];

**Test Image Processing:**

.. code-block:: bash

   ./vendor/bin/typo3 backend:test:imageprocessing

----

Debugging Installation
======================

Check Extension Installation
-----------------------------

.. code-block:: bash

   # Verify extension is installed
   composer show netresearch/rte-ckeditor-image

   # Check TYPO3 extension list
   ./vendor/bin/typo3 extension:list

Verify Configuration Loading
-----------------------------

.. code-block:: typoscript

   # Page TSConfig - Enable RTE debugging
   RTE.default.showButtons = *
   RTE.default.hideButtons =

Check Browser Console
---------------------

1. Open browser DevTools (F12)
2. Go to Console tab
3. Look for errors related to:

   * Plugin loading
   * Configuration issues
   * Missing dependencies

Monitor Network Requests
------------------------

1. Open browser DevTools
2. Go to Network tab
3. Check for failed requests to:

   * ``/rte/wizard/selectimage``
   * Backend image info API

----

Database Issues
===============

Issue: Large Database Size
---------------------------

**Symptoms:**

* Database growing rapidly
* sys_refindex table very large

**Cause:** Excessive soft reference entries

**Solution:** Rebuild reference index:

.. code-block:: bash

   ./vendor/bin/typo3 referenceindex:update

**Check References:**

.. code-block:: sql

   -- Find images in RTE content
   SELECT uid, bodytext
   FROM tt_content
   WHERE bodytext LIKE '%data-htmlarea-file-uid%';

----

Related Documentation
=====================

**Other Troubleshooting Topics:**

* :ref:`troubleshooting-editor-issues` - Editor and backend problems
* :ref:`troubleshooting-frontend-issues` - Frontend rendering issues
* :ref:`troubleshooting-performance-issues` - Performance optimization

**Additional Resources:**

* :ref:`integration-configuration` - Configuration guide
* :ref:`architecture-overview` - System architecture
* :ref:`getting-started` - Initial setup

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
