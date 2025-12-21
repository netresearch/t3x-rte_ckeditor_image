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

.. card-grid::
   :columns: 1
   :columns-md: 2
   :gap: 4
   :card-height: 100

   .. card:: CKEditor 5 Integration

      Native CKEditor 5 plugin with full toolbar integration and TYPO3 file browser support.

      .. card-footer:: :ref:`Plugin Development <ckeditor-plugin-development>`
         :button-style: btn btn-secondary stretched-link

   .. card:: TYPO3 FAL Support

      Full File Abstraction Layer integration with file references, metadata, and browser.

      .. card-footer:: :ref:`RTE Setup <integration-configuration-rte-setup>`
         :button-style: btn btn-secondary stretched-link

   .. card:: Image Processing

      Magic images, cropping, scaling with configurable quality multipliers (1x-6x).

      .. card-footer:: :ref:`Configuration <integration-configuration-image-processing>`
         :button-style: btn btn-secondary stretched-link

   .. card:: Custom Styles

      Define custom image styles (borders, shadows, alignment) via YAML configuration.

      .. card-footer:: :ref:`Image Styles <examples-image-styles>`
         :button-style: btn btn-secondary stretched-link

   .. card:: Responsive Images

      Automatic srcset generation and picture element support for responsive layouts.

      .. card-footer:: :ref:`Responsive Examples <examples-responsive-images>`
         :button-style: btn btn-secondary stretched-link

   .. card:: Performance

      Native lazy loading, intersection observer fallback, and optimized rendering.

      .. card-footer:: :ref:`Advanced Features <examples-advanced-features>`
         :button-style: btn btn-secondary stretched-link

   .. card:: Event Architecture

      PSR-14 event dispatching for custom image processing and rendering hooks.

      .. card-footer:: :ref:`Event Listeners <api-eventlisteners>`
         :button-style: btn btn-secondary stretched-link

   .. card:: Security

      Protocol blocking, XSS prevention, file validation, and FAL-based access control.

      .. card-footer:: :ref:`Security Guide <integration-security>`
         :button-style: btn btn-secondary stretched-link

   .. card:: Quality Multipliers
      :class: border-success

      |new135| Retina (2x), Ultra (3x), and Print (6x) quality settings for high-DPI displays.

      .. card-footer:: :ref:`Quality Settings <integration-configuration-quality>`
         :button-style: btn btn-secondary stretched-link

   .. card:: noScale Mode
      :class: border-success

      |new135| Skip image processing entirely for pre-optimized images (SVG, WebP, optimized PNG).

      .. card-footer:: :ref:`Image Processing <integration-configuration-image-processing>`
         :button-style: btn btn-secondary stretched-link

   .. card:: Service Architecture
      :class: border-success

      |new135| Modern Parser → Resolver → Renderer pipeline with dependency injection.

      .. card-footer:: :ref:`Services API <api-services>`
         :button-style: btn btn-secondary stretched-link

   .. card:: Fluid Templates
      :class: border-success

      |new135| Customizable output via template overrides for complete rendering control.

      .. card-footer:: :ref:`Template Overrides <examples-template-overrides>`
         :button-style: btn btn-secondary stretched-link

   .. card:: TYPO3 v14 & PHP 8.5
      :class: border-success

      |new135| Full compatibility with TYPO3 v14 and tested with PHP 8.5.

      .. card-footer:: :ref:`Requirements <requirements>`
         :button-style: btn btn-secondary stretched-link

.. |new135| replace:: **[New in 13.1.5]**

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
