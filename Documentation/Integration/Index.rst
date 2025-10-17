.. include:: /Includes.rst.txt

.. _integration:

============================
Integration & Configuration
============================

Complete configuration reference and integration guide for the RTE CKEditor Image extension.

Overview
========

This section provides comprehensive configuration documentation covering all aspects of integrating and configuring the extension in your TYPO3 installation.

Available Documentation
=======================

Configuration Guide
-------------------

:ref:`Complete configuration reference <integration-configuration>` covering:

RTE Configuration (YAML)
~~~~~~~~~~~~~~~~~~~~~~~~

- Editor configuration
- Toolbar button placement
- Image plugin options
- Style definitions
- Processing rules

Page TSConfig
~~~~~~~~~~~~~

- Backend user permissions
- File browser configuration
- Maximum image dimensions
- Allowed file types
- Upload folder settings

TypoScript Configuration
~~~~~~~~~~~~~~~~~~~~~~~~

- Frontend rendering setup
- Image processing options
- Link rendering configuration
- Lazy loading settings
- Custom attributes

Extension Configuration
~~~~~~~~~~~~~~~~~~~~~~~

- Global extension settings
- Security settings
- Performance options
- Feature toggles

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
- **Responsive images** → :ref:`integration-configuration-responsive`
- **Restricted access** → :ref:`integration-configuration-permissions`

By Component
------------

- **CKEditor plugin** → :ref:`integration-configuration-plugin`
- **File browser** → :ref:`integration-configuration-file-browser`
- **Frontend rendering** → :ref:`integration-configuration-frontend-rendering`
- **Image processing** → :ref:`integration-configuration-image-processing`

Integration Guides
==================

Fresh Installation
------------------

1. Install extension via Composer
2. Configure RTE (YAML)
3. Set up TSConfig
4. Configure TypoScript
5. Clear caches

Existing Installation
---------------------

1. Review current RTE configuration
2. Merge typo3image configuration
3. Update user permissions
4. Test in staging environment
5. Deploy to production

Migration from Other Solutions
------------------------------

- From native TYPO3 image handling
- From third-party extensions
- Configuration migration patterns

Related Documentation
=====================

- :ref:`examples` - Practical configuration examples
- :ref:`api` - Backend integration
- :ref:`ckeditor` - Frontend plugin
- :ref:`troubleshooting-common-issues` - Configuration issues
