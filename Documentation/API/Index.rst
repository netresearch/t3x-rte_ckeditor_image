.. _api-documentation:

==================
API Documentation
==================

Complete API reference for all PHP classes in the RTE CKEditor Image extension.

.. contents:: Table of Contents
   :depth: 2
   :local:

Overview
========

This section provides detailed documentation for the PHP backend components that power the extension's functionality.

Available Documentation
=======================

Controllers
-----------

:ref:`Controllers API Reference <api-controllers>`

Frontend and backend controllers for image handling:

- ``SelectImageController`` - Backend image selection and preview
- ``ImageRenderingController`` - Frontend image rendering
- ``ImageLinkRenderingController`` - Linked image rendering

Data Handling
-------------

:ref:`Data Handling API Reference <api-datahandling>`

Database hooks and image processing:

- ``RteImagesDbHook`` - Content processing and magic images
- Soft reference handling
- External image fetching
- Image transformation and storage

Event Listeners
---------------

:ref:`Event Listeners Reference <api-eventlisteners>`

PSR-14 event system integration:

- ``RteConfigurationListener`` - RTE configuration injection
- Event-driven architecture patterns
- Custom event handling

Usage Examples
==============

See :ref:`Common Use Cases <examples-common-use-cases>` for practical implementation examples of these APIs.

Related Documentation
=====================

- :ref:`Architecture Overview <architecture-overview>` - Understand how components interact
- :ref:`CKEditor Plugin Development <ckeditor-plugin-development>` - Frontend JavaScript components
- :ref:`Configuration Guide <integration-configuration>` - Configure PHP components
