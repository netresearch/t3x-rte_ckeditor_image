.. _troubleshooting-index:

============================
Troubleshooting & Support
============================

Solutions to common issues, debugging techniques, and support resources.

.. contents:: Table of Contents
   :local:
   :depth: 2

Overview
========

This section provides comprehensive troubleshooting guidance for common issues encountered when using the RTE CKEditor Image extension.

Available Documentation
=======================

:ref:`troubleshooting-common-issues`
------------------------------------

Complete troubleshooting guide covering:

Installation & Setup Issues
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

* Extension installation problems
* Dependency conflicts
* Cache-related issues
* Permission problems

Editor Issues
^^^^^^^^^^^^^

* Image button not appearing
* File browser not opening
* Style dropdown disabled (critical for v13.0.0+)
* Upload failures
* Preview not working

Frontend Rendering Issues
^^^^^^^^^^^^^^^^^^^^^^^^^^

* Images not displaying
* Broken image links
* Incorrect dimensions
* Missing styles
* Link rendering problems

Performance Issues
^^^^^^^^^^^^^^^^^^

* Slow image loading
* Large file handling
* Processing timeouts
* Memory exhaustion

Configuration Issues
^^^^^^^^^^^^^^^^^^^^

* TSConfig not applying
* TypoScript conflicts
* RTE configuration errors
* Style configuration problems

Quick Fixes
===========

Most Common Issues
------------------

1. Style Dropdown Disabled (v13.0.0+)
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: javascript

   // Ensure these dependencies are present:
   static get requires() {
       return ['StyleUtils', 'GeneralHtmlSupport'];
   }

.. seealso::
   :ref:`troubleshooting-style-dropdown`

2. Images Not Appearing in Frontend
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

* Check TypoScript setup
* Verify file permissions
* Clear all caches

.. seealso::
   :ref:`troubleshooting-frontend-rendering`

3. File Browser Not Opening
^^^^^^^^^^^^^^^^^^^^^^^^^^^^

* Check backend user permissions
* Verify TSConfig
* Check file mount configuration

.. seealso::
   :ref:`troubleshooting-file-browser`

Debugging Techniques
====================

Enable Debug Mode
-----------------

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '*';
   $GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = 1;

Browser Console
---------------

* Check for JavaScript errors
* Monitor network requests
* Inspect CKEditor plugin loading

TYPO3 Logs
----------

* Check ``var/log/typo3_*.log``
* Review deprecation log
* Monitor PHP error log

Database Queries
----------------

* Enable SQL debug mode
* Check soft references
* Verify file relations

Getting Help
============

Self-Help Resources
-------------------

1. Check this troubleshooting guide
2. Review :ref:`integration-configuration`
3. Consult :ref:`examples-common-use-cases`
4. Search `GitHub Issues <https://github.com/netresearch/t3x-rte_ckeditor_image/issues>`__

Community Support
-----------------

.. tip::
   Multiple community channels are available for support:

**GitHub Discussions**
   `github.com/netresearch/t3x-rte_ckeditor_image/discussions <https://github.com/netresearch/t3x-rte_ckeditor_image/discussions>`__

**TYPO3 Slack**
   #ext-rte_ckeditor_image channel

**TYPO3 Forum**
   `https://typo3.org/community/meet/ <https://typo3.org/community/meet/>`__

Reporting Bugs
--------------

.. warning::
   Before reporting, please:

   1. Check if issue already exists
   2. Verify you're using latest version
   3. Test with minimal configuration
   4. Collect debugging information

**Report bugs:** `github.com/netresearch/t3x-rte_ckeditor_image/issues <https://github.com/netresearch/t3x-rte_ckeditor_image/issues>`__

Include:

* TYPO3 version
* PHP version
* Extension version
* Steps to reproduce
* Error messages
* Browser console output

Known Issues & Workarounds
===========================

See :ref:`troubleshooting-common-issues` for detailed information on:

* v13.0.0 style integration changes
* Browser compatibility issues
* Performance considerations
* Edge cases and limitations

Related Documentation
=====================

:ref:`integration-configuration`
   Correct configuration

:ref:`examples-common-use-cases`
   Working implementations

:ref:`architecture-overview`
   System design understanding

:ref:`api-index`
   Technical reference
