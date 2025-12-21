.. include:: /Includes.rst.txt

.. _architecture-overview:
.. _architecture-system-components:
.. _architecture-technology-stack:

============================
System Architecture
============================

System architecture overview for the RTE CKEditor Image extension, covering the three-layer architecture, core components, and technology stack.

.. contents:: Table of Contents
   :depth: 3
   :local:

Overview
========

This document explains the architectural structure and core components of the RTE CKEditor Image extension. For design patterns and integration details, see :ref:`architecture-design-patterns`.

Three-Layer Architecture
------------------------

1. **CKEditor Plugin Layer** (JavaScript)

   - Custom typo3image plugin
   - Model element definition
   - UI components and commands
   - Upcast/downcast conversions

2. **TYPO3 Backend Layer** (PHP)

   - Controllers for image selection and rendering
   - Database hooks for content processing
   - FAL integration
   - Event listeners

3. **Frontend Rendering Layer** (PHP/HTML)

   - TypoScript configuration
   - Image processing and optimization
   - HTML generation

System Design
=============

The rte_ckeditor_image extension follows TYPO3's modern extension architecture with CKEditor 5 integration, providing seamless FAL (File Abstraction Layer) image management within rich text editors.

High-Level Architecture
=======================

..  uml::
    :caption: High-level system architecture

    skinparam componentStyle rectangle
    skinparam backgroundColor white

    package "TYPO3 Backend" {
        component "CKEditor\nPlugin" as CKEditor
        component "Image\nController" as Controller
        component "FAL\nStorage" as FAL
        component "JavaScript\nDialog" as Dialog
        component "Backend\nRoute" as Route

        CKEditor --> Controller
        Controller --> FAL
        CKEditor --> Dialog
        Controller --> Route
    }

    database "Content Storage" as Storage {
        card "RTE Content with\ndata-htmlarea-* attributes" as Content
    }

    package "Frontend Rendering" {
        component "TypoScript\nHooks" as TS
        component "Image\nRendering" as Rendering
        component "Rendered\nHTML" as HTML

        TS --> Rendering
        Rendering --> HTML
    }

    Controller --> Storage
    Storage --> TS

Core Components
===============

Backend Layer
-------------

1. Controllers (``Classes/Controller/``)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- **SelectImageController**: Handles image selection and processing
- **ImageRenderingController**: Frontend image rendering
- **ImageLinkRenderingController**: Renders images within links

2. Event Listeners (``Classes/EventListener/``)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- **RteConfigurationListener**: Customizes RTE configuration before initialization

3. Database Hooks (``Classes/Database/``)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- **RteImagesDbHook**: TCEmain data processing for image references

4. Data Handling (``Classes/DataHandling/SoftReference/``)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- **RteImageSoftReferenceParser**: Tracks soft references for link management

Frontend Layer (CKEditor Plugin)
---------------------------------

JavaScript Module (``Resources/Public/JavaScript/Plugins/typo3image.js``)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- **Typo3Image Plugin**: CKEditor 5 plugin class
- **Custom Model**: ``typo3image`` element with rich attributes
- **UI Components**: Image dialog, selection modal
- **Style Integration**: StyleUtils and GeneralHtmlSupport integration
- **Conversion System**: Upcast (HTML → Model) and Downcast (Model → HTML)

Configuration Layer
-------------------

YAML Configuration
~~~~~~~~~~~~~~~~~~

- **Services.yaml**: Dependency injection container configuration
- **Plugin.yaml**: RTE plugin registration

TypoScript
~~~~~~~~~~

- **setup.typoscript**: Frontend rendering configuration
- **page.tsconfig**: Backend RTE configuration

Backend Routes
~~~~~~~~~~~~~~

- **Routes.php**: Backend route definitions for image selection

Technology Stack
================

- **PHP**: 8.2+ with strict types
- **TYPO3**: 13.4 LTS / 14.0+ (Core, Backend, Frontend, Extbase, RTE CKEditor)
- **JavaScript**: ES6 modules
- **CKEditor**: 5.x provided by TYPO3 core with direct imports from @ckeditor/* namespace
- **Dependency Injection**: Symfony service container
- **Standards**: PSR-12, PER-CS2.0

Security Considerations
=======================

- File access through FAL security layer
- Backend routes require authentication
- Input validation on all user data
- XSS prevention through proper encoding
- Data attribute sanitization on frontend

Performance Considerations
==========================

- Processed images cached by TYPO3
- Lazy loading support for frontend
- Minimal JavaScript footprint
- Efficient database queries with soft references

Related Documentation
=====================

- :ref:`architecture-design-patterns` - Design patterns, integration points, and data flow
- :ref:`api-controllers` - Controller API reference
- :ref:`ckeditor-plugin-development` - CKEditor plugin integration details
- :ref:`integration-security` - Security considerations
