.. include:: /Includes.rst.txt

.. _integration:

============================
Integration & Configuration
============================

Complete configuration reference and integration guide for the RTE CKEditor Image extension.

Configuration Quick Reference
==============================

Essential Configurations
------------------------

Minimum Setup
~~~~~~~~~~~~~

.. code-block:: yaml

   editor:
     config:
       toolbar:
         items:
           - typo3image

Recommended Setup
~~~~~~~~~~~~~~~~~

.. code-block:: yaml

   editor:
     config:
       toolbar:
         items:
           - typo3image
       typo3image:
         maxWidth: 1920
         maxHeight: 1080

Full-Featured Setup
~~~~~~~~~~~~~~~~~~~

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

- :ref:`examples-common-use-cases` - Practical configuration examples
- :ref:`api-documentation` - Backend integration
- :ref:`ckeditor-plugin-development` - Frontend plugin
- :ref:`troubleshooting-index` - Configuration issues

.. toctree::
   :hidden:
   :maxdepth: 1

   RTE-Setup
   TSConfig
   Frontend-Rendering
   Advanced-Configuration
