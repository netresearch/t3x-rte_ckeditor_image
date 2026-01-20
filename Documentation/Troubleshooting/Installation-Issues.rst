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

Issue: Click-to-Enlarge Not Working with sys_template Records (TYPO3 v13)
--------------------------------------------------------------------------

.. versionadded:: 13.0.0
   TYPO3 v13 introduced site sets as a modern alternative to sys_template records.
   When sys_template records exist, site sets are bypassed, which affects extensions
   that rely on site set dependencies.

**Symptoms:**

* Images display correctly in frontend
* Click-to-enlarge functionality doesn't work
* Data attributes still visible in HTML output (``data-htmlarea-zoom``, ``data-htmlarea-file-uid``)
* Image processing hooks not executed

**Cause:**

In TYPO3 v13, **sys_template records prevent site sets from loading**. Legacy installations
like the Introduction Package use sys_template records instead of modern site sets. When
a sys_template exists on a page, TYPO3 ignores site set dependencies, so the extension's
TypoScript configuration is never loaded.

**Detection:**

Check if your site uses sys_template records:

.. code-block:: sql

   SELECT uid, pid, title, include_static_file
   FROM sys_template
   WHERE deleted=0 AND hidden=0;

If records exist and ``data-htmlarea-*`` attributes appear in frontend HTML, the extension's
TypoScript is not being loaded.

**Solution 1: Manual TypoScript Include (Quick Fix)**

Add TypoScript directly to the sys_template record:

1. Go to **WEB > Template** module
2. Select page with sys_template record
3. Click **Edit the whole template record**
4. In **Setup** field, add:

.. code-block:: typoscript

   # Include RTE CKEditor Image TypoScript
   <INCLUDE_TYPOSCRIPT: source="FILE:EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript">

5. Save template
6. Clear all caches:

.. code-block:: bash

   ./vendor/bin/typo3 cache:flush

**Solution 2: Migrate to Site Sets (Recommended for TYPO3 v13)**

Modern TYPO3 v13 approach:

1. **Remove sys_template records** from pages (or set them to deleted/hidden)

2. **Enable site set dependencies** in ``config/sites/<site>/config.yaml``:

.. code-block:: yaml

   base: 'https://example.com/'
   rootPageId: 1
   dependencies:
     - typo3/fluid-styled-content
     - netresearch/rte-ckeditor-image

3. **Clear caches:**

.. code-block:: bash

   ./vendor/bin/typo3 cache:flush

4. **Verify in frontend** - data attributes should be removed and click-to-enlarge should work

**Why This Happens:**

* **TypoScript must be manually included** via static template or import
* **sys_template records** control TypoScript for their page tree
* **Bootstrap Package** in sys_template may clear ``lib.parseFunc_RTE`` hooks
* **Load order matters** - include the extension's TypoScript after theme packages but before Fluid Styled Content

**Verification:**

After applying fix, check frontend HTML:

.. code-block:: html

   <!-- Before (BROKEN): -->
   <img src="..." data-htmlarea-zoom="true" data-htmlarea-file-uid="2" />

   <!-- After (WORKING): -->
   <a href="/index.php?eID=tx_cms_showpic&file=2&...">
       <img src="..." />
   </a>

.. important::
   sys_template records are legacy. TYPO3 v13 prefers site sets for better
   dependency management and proper load order control. Migrating to site sets
   is recommended for long-term maintainability.

----

Issue: Insert Image Button Missing with Bootstrap Package or Other Site Sets
-----------------------------------------------------------------------------

.. versionadded:: 13.1.0
   Site Set dependency ordering ensures proper override behavior.

**Symptoms:**

* Extension is installed and active
* "Insert image" button missing from RTE toolbar
* Page TSConfig shows ``RTE.default.preset = bootstrap`` (or another third-party preset)

**Cause:**

Third-party extensions like ``bootstrap_package`` use **Site Sets** to configure the RTE.
In TYPO3 v13, Site Sets have **higher priority** than extension ``page.tsconfig`` files.

The loading order is:

1. Extension ``Configuration/page.tsconfig`` (our ``RTE.default.preset = rteWithImages``)
2. Site Set configurations (bootstrap's ``RTE.default.preset = bootstrap`` **overrides ours**)

When your site uses a Site Set dependency like ``bootstrap-package/full``, it loads
**after** our extension's page.tsconfig and overrides our RTE preset.

**Detection:**

Check the active RTE preset in Page TSConfig module:

1. Go to **Site Management** → **Page TSconfig**
2. Search for ``RTE.default.preset``
3. If it shows ``bootstrap`` or another preset (not ``rteWithImages``), you have this issue

Or check your site configuration:

.. code-block:: yaml
   :caption: config/sites/<site>/config.yaml

   dependencies:
     - bootstrap-package/full  # This overrides our RTE preset!

**Solution: Add Site Set Dependency**

Add ``netresearch/rte-ckeditor-image`` to your site dependencies **after** the overriding
package so our preset loads last:

.. code-block:: yaml
   :caption: config/sites/<site>/config.yaml

   base: 'https://example.com/'
   rootPageId: 1
   dependencies:
     - bootstrap-package/full
     - netresearch/rte-ckeditor-image  # Must come AFTER bootstrap-package

Clear caches after updating:

.. code-block:: bash

   ./vendor/bin/typo3 cache:flush

**Why This Works:**

Our Site Set (``netresearch/rte-ckeditor-image``) declares ``optionalDependencies`` on
bootstrap-package, so when both are listed, ours loads **after** bootstrap and overrides
their RTE preset with ``rteWithImages``.

.. code-block:: yaml
   :caption: EXT:rte_ckeditor_image/Configuration/Sets/RteCKEditorImage/config.yaml

   name: netresearch/rte-ckeditor-image
   label: 'CKEditor Image Support'
   optionalDependencies:
     - bootstrap-package/content-elements
     - bootstrap-package/full

**Affected Packages:**

This issue affects any extension that sets ``RTE.default.preset`` via Site Sets:

* ``bootstrap_package`` (sets ``RTE.default.preset = bootstrap``)
* Other theme packages with custom RTE configurations
* Any extension using Site Sets for RTE configuration

**Verification:**

After adding the dependency:

1. Clear all caches
2. Go to any content element with an RTE field
3. Verify the **Insert image** button appears in the toolbar
4. Check Page TSConfig shows ``RTE.default.preset = rteWithImages``

.. tip::
   The extension's Site Set is designed to work alongside theme packages.
   Simply adding it to your site dependencies is the correct solution—no
   manual TSConfig overrides needed.

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
