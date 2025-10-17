.. include:: /Includes.rst.txt

.. _start:

===================
RTE CKEditor Image
===================

:Extension key:
   rte_ckeditor_image

:Package name:
   netresearch/rte-ckeditor-image

:Version:
   13.0.x

:Language:
   en

:Author:
   Netresearch DTT GmbH

:License:
   This document is published under the
   `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
   license.

:Rendered:
   |today|

Image support in CKEditor for the TYPO3 ecosystem.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: 📘 Introduction

        The RTE CKEditor Image extension provides comprehensive image handling
        capabilities for TYPO3's CKEditor Rich Text Editor with full FAL integration.

        ..  card-footer:: :ref:`Read more <introduction>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚡ Quick Start

        Get up and running quickly with installation instructions and
        basic configuration examples.

        ..  card-footer:: :ref:`Read more <quick-start>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚙️ Configuration

        Learn how to configure custom image styles, processing options,
        and frontend rendering setup.

        ..  card-footer:: :ref:`Read more <integration-configuration>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🏗️ Architecture

        Understand the extension's architecture, design patterns,
        and how components interact.

        ..  card-footer:: :ref:`Read more <architecture-index>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🔧 Developer API

        Explore the PHP and JavaScript APIs for extending and
        customizing the extension.

        ..  card-footer:: :ref:`Read more <api-documentation>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🐛 Troubleshooting

        Find solutions to common issues and learn debugging techniques.

        ..  card-footer:: :ref:`Read more <troubleshooting-index>`
            :button-style: btn btn-primary stretched-link


.. _introduction:

Introduction
============

The RTE CKEditor Image extension provides comprehensive image handling capabilities
for TYPO3's CKEditor Rich Text Editor. This extension enables editors to insert,
configure, and style images directly within the CKEditor interface, with full
integration into TYPO3's File Abstraction Layer (FAL).

Key Features
------------

- Native CKEditor 5 plugin integration
- Full TYPO3 FAL support with file browser integration
- Advanced image processing (magic images, cropping, scaling)
- Custom image style configuration
- Responsive image support
- Lazy loading and performance optimization
- Event-driven architecture for extensibility

Version Information
-------------------

:Version: 13.0.x for TYPO3 13.4+
:License: AGPL-3.0-or-later
:Repository: `github.com/netresearch/t3x-rte_ckeditor_image <https://github.com/netresearch/t3x-rte_ckeditor_image>`__


.. _requirements:

Requirements
============

System Requirements
-------------------

- **TYPO3:** 13.4 or later
- **PHP:** 8.2, 8.3, or 8.4
- **Extensions:** cms-rte-ckeditor (included in TYPO3 core)

Critical Dependencies
---------------------

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

Quick Start
===========

Installation
------------

Install via Composer:

.. code-block:: bash

   composer require netresearch/rte-ckeditor-image

Activate the extension in the TYPO3 Extension Manager or via CLI:

.. code-block:: bash

   vendor/bin/typo3 extension:activate rte_ckeditor_image

Basic Configuration
-------------------

Add the image button to your RTE configuration:

.. code-block:: yaml

   editor:
     config:
       toolbar:
         items:
           - heading
           - '|'
           - typo3image
           - link
           - '|'
           - bold
           - italic

For complete configuration options, see :ref:`Configuration Guide <integration-configuration>`.


.. _navigation-by-role:

Quick Navigation by Role
========================

.. list-table::
   :header-rows: 1
   :widths: 15 30 30 25

   * - Role
     - Start Here
     - Then Read
     - Advanced

   * - **Integrator**
     - :ref:`Configuration Guide <integration-configuration>`
     - :ref:`Examples <examples-common-use-cases>`
     - :ref:`Troubleshooting <troubleshooting-index>`

   * - **PHP Dev**
     - :ref:`Architecture <architecture-index>`
     - :ref:`API Reference <api-documentation>`
     - :ref:`Data Handling <api-datahandling>`

   * - **JS Dev**
     - :ref:`CKEditor Plugin <ckeditor-plugin-development>`
     - :ref:`Style Integration <ckeditor-style-integration>`
     - :ref:`Conversions <ckeditor-conversions>`

   * - **Contributor**
     - :ref:`Architecture <architecture-index>`
     - :ref:`API Documentation <api-documentation>`
     - :ref:`Examples <examples-common-use-cases>`


.. _documentation-use-cases:

Documentation Use Cases
========================

For Integrators
---------------

- **Add custom image styles** → :ref:`Configuration Guide <integration-configuration>`
- **Configure image processing** → :ref:`Configuration Guide <integration-configuration>`
- **Set up frontend rendering** → :ref:`Configuration Guide <integration-configuration>`
- **Enable lazy loading** → :ref:`Examples: Lazy Loading <examples-common-use-cases>`

For Developers
--------------

PHP Backend Development
~~~~~~~~~~~~~~~~~~~~~~~

- **Understand the architecture** → :ref:`Architecture Overview <architecture-index>`
- **Controller APIs** → :ref:`Controllers API <api-controllers>`
- **Customize image processing** → :ref:`Data Handling API <api-datahandling>`
- **Listen to extension events** → :ref:`Event Listeners <api-eventlisteners>`

JavaScript/CKEditor Development
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- **Extend the CKEditor plugin** → :ref:`Plugin Development <ckeditor-plugin-development>`
- **Style system integration** → :ref:`Style Integration <ckeditor-style-integration>`
- **Custom model element** → :ref:`Model Element <ckeditor-model-element>`
- **Conversion system** → :ref:`Conversions <ckeditor-conversions>`

For Troubleshooters
-------------------

- **Images not appearing** → :ref:`Frontend Rendering Issues <troubleshooting-frontend-rendering>`
- **Style dropdown disabled** → :ref:`Style Drop-down Not Working <troubleshooting-style-dropdown>`
- **File browser not opening** → :ref:`File Browser Issues <troubleshooting-file-browser>`
- **Performance problems** → :ref:`Common Issues <troubleshooting-index>`


.. _support-contributing:

Support & Contributing
======================

Get Help
--------

- `GitHub Issues <https://github.com/netresearch/t3x-rte_ckeditor_image/issues>`__
- `GitHub Discussions <https://github.com/netresearch/t3x-rte_ckeditor_image/discussions>`__
- `TYPO3 Slack #ext-rte_ckeditor_image <https://typo3.slack.com/archives/ext-rte_ckeditor_image>`__

Contribute
----------

- Report bugs or request features via `GitHub Issues <https://github.com/netresearch/t3x-rte_ckeditor_image/issues>`__
- Submit pull requests for code improvements
- Improve documentation via pull requests
- Follow `TYPO3 Contribution Guidelines <https://docs.typo3.org/m/typo3/guide-contributionworkflow/main/en-us/>`__


.. _license:

License
=======

This extension is licensed under `AGPL-3.0-or-later <https://www.gnu.org/licenses/agpl-3.0.html>`__.


.. _credits:

Credits
=======

Development & Maintenance
--------------------------

- `Netresearch DTT GmbH <https://www.netresearch.de/>`__
- Sebastian Koschel
- Sebastian Mendel
- Rico Sonntag

Community Contributors
----------------------

See `GitHub Contributors <https://github.com/netresearch/t3x-rte_ckeditor_image/graphs/contributors>`__


.. _additional-resources:

Additional Resources
====================

- **TYPO3 Extension Repository:** `extensions.typo3.org/extension/rte_ckeditor_image <https://extensions.typo3.org/extension/rte_ckeditor_image>`__
- **Packagist:** `packagist.org/packages/netresearch/rte-ckeditor-image <https://packagist.org/packages/netresearch/rte-ckeditor-image>`__
- **TYPO3 CKEditor Documentation:** `docs.typo3.org - RTE CKEditor <https://docs.typo3.org/c/typo3/cms-rte-ckeditor/13.4/en-us/>`__
- **CKEditor 5 Documentation:** `ckeditor.com/docs/ckeditor5 <https://ckeditor.com/docs/ckeditor5/latest/>`__


.. toctree::
   :hidden:
   :maxdepth: 2
   :caption: User Documentation

   Integration/Index
   Examples/Index
   Troubleshooting/Index

.. toctree::
   :hidden:
   :maxdepth: 2
   :caption: Architecture & Design

   Architecture/Index

.. toctree::
   :hidden:
   :maxdepth: 2
   :caption: Developer Documentation

   API/Index
   CKEditor/Index
