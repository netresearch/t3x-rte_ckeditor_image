.. include:: /Includes.rst.txt

.. _api-documentation:
.. _api-index:

==================
API Documentation
==================

Complete API reference for all PHP classes in the RTE CKEditor Image extension.

.. contents:: Table of Contents
   :depth: 2
   :local:

.. versionchanged:: 13.1.5

   Major architecture update: legacy controllers replaced with service-based pipeline.
   See :ref:`api-services` for the new approach.

API Components
==============

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: 🎮 Controllers API

        TypoScript adapters and backend controllers for image handling.

        ..  card-footer:: :ref:`Read more <api-controllers>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ⚙️ Services API

        New service architecture with clean separation of concerns.

        ..  card-footer:: :ref:`Read more <api-services>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 📦 Data Transfer Objects

        Type-safe DTOs for validated image data.

        ..  card-footer:: :ref:`Read more <api-dtos>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 📊 Data Handling API

        Database hooks, content processing, and image transformations.

        ..  card-footer:: :ref:`Read more <api-datahandling>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🔔 Event Listeners

        PSR-14 event system integration for RTE configuration.

        ..  card-footer:: :ref:`Read more <api-eventlisteners>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🖼️ ViewHelpers

        Fluid ViewHelpers for Content Blocks backend previews.

        ..  card-footer:: :ref:`Read more <api-viewhelpers>`
            :button-style: btn btn-primary stretched-link

Usage Examples
==============

See :ref:`Common Use Cases <examples-common-use-cases>` for practical implementation examples of these APIs.

Related Documentation
=====================

- :ref:`Architecture Overview <architecture-overview>` - Understand how components interact
- :ref:`CKEditor Plugin Development <ckeditor-plugin-development>` - Frontend JavaScript components
- :ref:`Configuration Guide <integration-configuration>` - Configure PHP components

.. toctree::
   :hidden:
   :maxdepth: 1

   Controllers
   Services
   DTOs
   DataHandling
   EventListeners
   ViewHelpers
