.. include:: /Includes.rst.txt

.. _integration:

============================
Integration & Configuration
============================

Complete configuration reference and integration guide for the RTE CKEditor Image extension.

.. important::
   **Zero-Configuration Installation (v13.0.0+)**

   The extension works out-of-the-box after installation:

   .. code-block:: bash

      composer require netresearch/rte-ckeditor-image

   **No manual configuration needed!** The extension automatically:

   - ✅ Registers ``rteWithImages`` preset for backend RTE
   - ✅ Configures toolbar with ``insertimage`` button
   - ✅ Loads TypoScript for frontend rendering
   - ✅ Applies configuration globally to all sites

   This section is for **advanced users** who need custom RTE configurations beyond the defaults.

Configuration Quick Reference
==============================

For Custom RTE Presets
-----------------------

These examples show how to create **custom configurations** that override the automatic defaults.
If you just installed the extension and it's working, you don't need these.

Minimum Custom Toolbar
~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

   # Only needed if customizing the default toolbar
   editor:
     config:
       toolbar:
         items:
           - insertimage

Custom Toolbar with Specific Buttons
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml

   # Example: Custom preset with limited toolbar
   editor:
     config:
       toolbar:
         items:
           - bold
           - italic
           - insertimage

Full-Featured Custom Setup
~~~~~~~~~~~~~~~~~~~~~~~~~~~

See :ref:`integration-configuration-complete-example`

Configuration Patterns
======================

By Use Case
-----------

- **Basic editor** → :ref:`integration-configuration-minimal`
- **Style-aware images** → :ref:`integration-configuration-custom-styles`
- **Responsive images** → :ref:`integration-configuration`
- **Restricted access** → :ref:`integration-configuration-permissions`

By Component
------------

- **CKEditor plugin** → :ref:`integration-configuration`
- **File browser** → :ref:`integration-configuration`
- **Frontend rendering** → :ref:`integration-configuration-frontend-rendering`
- **Image processing** → :ref:`integration-configuration-image-processing`

Configuration Topics
====================

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: 🛠️ RTE Setup

        RTE configuration, presets, and toolbar setup

        ..  card-footer:: :ref:`Read more <integration-configuration-rte-setup>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚙️ TSConfig

        Page TSConfig settings, permissions, and file mounts

        ..  card-footer:: :ref:`Read more <integration-configuration-tsconfig>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🖼️ Frontend Rendering

        TypoScript configuration and frontend rendering setup

        ..  card-footer:: :ref:`Read more <integration-configuration-frontend-rendering>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🔧 Advanced Configuration

        Custom styles, performance optimization, and best practices

        ..  card-footer:: :ref:`Read more <integration-configuration-advanced>`
            :button-style: btn btn-primary stretched-link

Related Documentation
=====================

.. card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: 📚 Examples

        Practical configuration examples and common use cases

        ..  card-footer:: :ref:`Read more <examples-common-use-cases>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 🔌 API Documentation

        Backend integration and PHP APIs

        ..  card-footer:: :ref:`Read more <api-documentation>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: ⚙️ CKEditor Plugin

        Frontend plugin development guide

        ..  card-footer:: :ref:`Read more <ckeditor-plugin-development>`
            :button-style: btn btn-secondary stretched-link

    ..  card:: 🐛 Troubleshooting

        Configuration issues and debugging

        ..  card-footer:: :ref:`Read more <troubleshooting-index>`
            :button-style: btn btn-secondary stretched-link

.. toctree::
   :hidden:
   :maxdepth: 1

   RTE-Setup
   TSConfig
   Frontend-Rendering
   Advanced-Configuration
