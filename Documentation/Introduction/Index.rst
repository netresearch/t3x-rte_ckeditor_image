.. include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============

The RTE CKEditor Image extension provides comprehensive image handling capabilities
for TYPO3's CKEditor Rich Text Editor. This extension enables editors to insert,
configure, and style images directly within the CKEditor interface, with full
integration into TYPO3's File Abstraction Layer (FAL).

Key Features
============

- :ref:`Native CKEditor 5 plugin integration <ckeditor-plugin-development>`
- :ref:`Full TYPO3 FAL support <integration-configuration-rte-setup>` with file browser integration
- :ref:`Advanced image processing <integration-configuration-image-processing>` (magic images, cropping, scaling)
- :ref:`Custom image style configuration <examples-image-styles>`
- :ref:`Responsive image support <examples-responsive-images>`
- :ref:`Lazy loading and performance optimization <examples-advanced-features>`
- :ref:`Event-driven architecture <api-eventlisteners>` for extensibility

.. versionadded:: 13.1.5

   - **Quality multipliers**: Retina (2x), Ultra (3x), Print (6x) quality settings.
   - **noScale mode**: Skip image processing for pre-optimized images.
   - **SVG support**: Native SVG dimension extraction and handling.
   - **Service architecture**: Modern Parser → Resolver → Renderer pipeline.
   - **Fluid templates**: Customizable output via template overrides.
   - **Enhanced security**: Protocol blocking, XSS prevention, file validation.
   - **TYPO3 v14 support**: Full compatibility with TYPO3 v14.
   - **PHP 8.5 support**: Tested with PHP 8.5.

Visual Preview
==============

.. figure:: /Images/demo.gif
   :alt: RTE CKEditor Image extension demo
   :class: with-shadow

   Image insertion and configuration in CKEditor with TYPO3 file browser integration

Version Information
===================

:Supported TYPO3: 13.4 LTS, 14.0+
:License: AGPL-3.0-or-later
:Repository: `github.com/netresearch/t3x-rte_ckeditor_image <https://github.com/netresearch/t3x-rte_ckeditor_image>`__
:Maintainer: `Netresearch DTT GmbH <https://www.netresearch.de/>`__


.. _requirements:

============
Requirements
============

System Requirements
===================

- **TYPO3:** 13.4 LTS or 14.0+
- **PHP:** 8.2, 8.3, 8.4, or 8.5
- **Extensions:** cms-rte-ckeditor (included in TYPO3 core)

Critical Dependencies
=====================

.. versionadded:: 13.0.0
   The CKEditor plugin now requires ``StyleUtils`` and ``GeneralHtmlSupport``
   dependencies for style functionality. Previous versions did not have this requirement.

The CKEditor plugin requires these dependencies for style functionality:

.. code-block:: javascript

   static get requires() {
       return ['StyleUtils', 'GeneralHtmlSupport'];
   }

.. important::

   Missing either plugin will disable the style dropdown for images.
   See :ref:`Style Integration <ckeditor-style-integration>` for details.


.. _quick-start:

===========
Quick Start
===========

Installation
============

Install via Composer:

.. code-block:: bash

   composer require netresearch/rte-ckeditor-image

**That's it!** The extension works completely out-of-the-box with zero configuration:

- ✅ **Backend RTE**: Automatically registers the ``rteWithImages`` preset and configures the toolbar with ``insertimage`` button for all sites
- ✅ **Frontend Rendering**: Automatically loads TypoScript for proper image rendering via ``lib.parseFunc_RTE``
- ✅ **No Manual Steps**: No template inclusion, no TSConfig setup, no YAML configuration required

.. figure:: /Images/demo.gif
   :alt: Image button in CKEditor toolbar
   :class: with-shadow
   :width: 600px

   The ``insertimage`` button provides full image management capabilities with TYPO3 file browser integration

Custom Configuration (Optional)
================================

If you need to customize the RTE configuration or create your own preset, see the
:ref:`RTE Setup Guide <integration-configuration-rte-setup>` for detailed instructions.

The extension provides a default preset that you can extend or override as needed.


.. important::

   **Before using this extension**, please read :ref:`TYPO3 Core Removal & Design Decision <core-removal>`
   to understand why TYPO3 intentionally removed this functionality and whether this extension
   is the right choice for your project.
