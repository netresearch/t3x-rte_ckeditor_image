.. include:: /Includes.rst.txt

.. _troubleshooting-index:

============================
Troubleshooting & Support
============================

Solutions to common issues, debugging techniques, and support resources.

.. contents:: Table of Contents
   :local:
   :depth: 2

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
   `#ext-rte_ckeditor_image channel <https://typo3.slack.com/archives/ext-rte_ckeditor_image>`__

**TYPO3 Forum**
   `typo3.org/community/meet <https://typo3.org/community/meet/>`__

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

Troubleshooting Topics
======================

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: 📦 Installation Issues

        Extension installation problems, dependency conflicts, cache issues, and permission problems

        ..  card-footer:: :ref:`Read more <troubleshooting-installation-issues>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ✏️ Editor Issues

        Image button problems, style dropdown issues, file browser problems, and CKEditor errors

        ..  card-footer:: :ref:`Read more <troubleshooting-editor-issues>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🖥️ Frontend Issues

        Image display problems, broken links, dimension issues, and rendering problems

        ..  card-footer:: :ref:`Read more <troubleshooting-frontend-issues>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚡ Performance Issues

        Editor performance, frontend performance, image processing optimization, and database performance

        ..  card-footer:: :ref:`Read more <troubleshooting-performance-issues>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🔍 Image Reference Validation

        Detect and fix stale image references, processed URLs, orphaned file UIDs, and broken src attributes

        ..  card-footer:: :ref:`Read more <troubleshooting-image-reference-validation>`
            :button-style: btn btn-primary stretched-link

Related Documentation
=====================

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: ⚙️ Configuration

        Correct configuration and setup guide

        ..  card-footer:: :ref:`Read more <integration-configuration>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 📚 Examples

        Working implementations and use cases

        ..  card-footer:: :ref:`Read more <examples-common-use-cases>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 🏗️ Architecture

        System design and component interaction

        ..  card-footer:: :ref:`Read more <architecture-overview>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 🔌 API Reference

        Technical API documentation

        ..  card-footer:: :ref:`Read more <api-index>`
            :button-style: btn btn-secondary stretched-link

.. toctree::
   :hidden:
   :maxdepth: 1

   Installation-Issues
   Editor-Issues
   Frontend-Issues
   Performance-Issues
   Image-Reference-Validation
