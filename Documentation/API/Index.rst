.. _api-documentation:
.. _api-index:

==================
API Documentation
==================

Complete API reference for all PHP classes in the RTE CKEditor Image extension.

.. contents:: Table of Contents
   :depth: 2
   :local:

API Components
==============

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Controllers API <api-controllers>`

        Frontend and backend controllers for image handling and rendering

        ..  card-footer:: :ref:`Read more <api-controllers>`
            :button-style: btn btn-primary

    ..  card:: :ref:`Data Handling API <api-datahandling>`

        Database hooks, content processing, and image transformations

        ..  card-footer:: :ref:`Read more <api-datahandling>`
            :button-style: btn btn-primary

    ..  card:: :ref:`Event Listeners <api-eventlisteners>`

        PSR-14 event system integration for RTE configuration

        ..  card-footer:: :ref:`Read more <api-eventlisteners>`
            :button-style: btn btn-primary

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
   DataHandling
   EventListeners
