.. _architecture:
.. _architecture-index:

============================
Architecture & Design
============================

System architecture, component design, and technical implementation details for the RTE CKEditor Image extension.

.. contents:: Table of Contents
   :depth: 2
   :local:

Overview
========

This section explains the architectural decisions, design patterns, and component interactions in the RTE CKEditor Image extension.

Architecture Topics
===================

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`System Architecture <architecture-system-components>`

        Three-layer architecture, core components, technology stack, and security/performance considerations

        ..  card-footer:: :ref:`Read more <architecture-system-components>`
            :button-style: btn btn-primary

    ..  card:: :ref:`Design Patterns <architecture-design-patterns>`

        Key design patterns, integration points, data flow, and extension points for developers

        ..  card-footer:: :ref:`Read more <architecture-design-patterns>`
            :button-style: btn btn-primary

Quick Links
===========

System Understanding
--------------------

- **Learn the architecture** → :ref:`architecture-system-components`
- **Understand data flow** → :ref:`architecture-data-flow`
- **Extend the system** → :ref:`architecture-extension-points`

Integration Reference
---------------------

- **TYPO3 Core integration** → :ref:`architecture-integration-points`
- **CKEditor integration** → :ref:`ckeditor-plugin-development`
- **Backend APIs** → :ref:`api-documentation`

Related Documentation
=====================

- :ref:`ckeditor-plugin-development` - CKEditor 5 plugin implementation
- :ref:`api-documentation` - PHP backend APIs
- :ref:`integration-configuration` - Configuration reference
- :ref:`examples-common-use-cases` - Practical examples

.. toctree::
   :hidden:
   :maxdepth: 1

   System-Architecture
   Design-Patterns
