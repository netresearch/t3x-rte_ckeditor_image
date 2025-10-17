.. _architecture-design-patterns:
.. _architecture-integration-points:
.. _architecture-data-flow:
.. _architecture-extension-points:

============================
Design Patterns & Integration
============================

Design patterns, integration points, data flow, and extension mechanisms for the RTE CKEditor Image extension.

.. contents:: Table of Contents
   :depth: 3
   :local:

Overview
========

This document explains the design patterns, integration approaches, and data flow used in the RTE CKEditor Image extension. For system architecture and components, see :ref:`architecture-overview`.

Key Design Patterns
===================

The extension employs several proven design patterns for maintainability and extensibility:

- **MVC Pattern** - Controllers, models, and views separation
- **Event-Driven** - PSR-14 events for extensibility
- **Plugin Architecture** - Modular CKEditor plugin
- **Soft References** - TYPO3 reference tracking
- **Command Pattern** - CKEditor commands for actions

Dependency Injection
--------------------

All PHP classes use Symfony's dependency injection:

.. code-block:: yaml

   services:
     _defaults:
       autowire: true
       autoconfigure: true
       public: false

This approach provides:

- Loose coupling between components
- Easier testing through dependency substitution
- Clear component dependencies
- Automatic service wiring

Event-Driven Architecture
--------------------------

TYPO3 event system for loose coupling:

- ``AfterPrepareConfigurationForEditorEvent`` - RTE configuration
- TCEmain hooks for data processing

Benefits:

- Components can be extended without modification
- Third-party extensions can hook into processing
- Maintainable separation of concerns
- Clear extension points for customization

MVC Pattern
-----------

Controllers handle requests, models represent data, views render output:

- **Controllers**: Process backend requests and coordinate actions
- **FAL Models**: Represent files and their metadata
- **TypoScript Views**: Render frontend HTML output

This separation ensures:

- Clear responsibility boundaries
- Independent testing of each layer
- Flexible view rendering strategies
- Reusable business logic

Plugin Pattern
--------------

CKEditor 5 plugin system:

- Custom ``typo3image`` model element
- Editor commands and UI components
- Conversion system for data transformation

Implementation details:

- Plugin registration via ``Plugin.yaml``
- Custom schema definitions for model elements
- Bidirectional conversion (upcast/downcast)
- Integration with CKEditor's command system

Integration Points
==================

TYPO3 Core Integration
----------------------

1. **RTE CKEditor**: Extends TYPO3's CKEditor integration

   - Registers custom plugin through YAML configuration
   - Extends default RTE configuration
   - Adds TYPO3-specific functionality to editor

2. **FAL**: Uses File Abstraction Layer for file management

   - Leverages FAL for unified file handling
   - Respects file permissions and access rights
   - Supports all FAL storage drivers
   - Maintains file reference integrity

3. **TCEmain**: Hooks into data processing pipeline

   - Processes image references during save operations
   - Updates soft references automatically
   - Validates data integrity
   - Triggers reference index updates

4. **Soft References**: Tracks file references for integrity

   - Custom soft reference parser for RTE images
   - Enables reference tracking across content
   - Supports reference index operations
   - Prevents orphaned file records

CKEditor Integration
--------------------

1. **Plugin Registration**: Via JavaScriptModules.php and Plugin.yaml

   - Module registration in PHP
   - Plugin configuration in YAML
   - Integration with TYPO3's asset management
   - Proper loading order and dependencies

2. **Custom Model**: ``typo3image`` element with TYPO3-specific attributes

   - Schema definition for element structure
   - Support for data-htmlarea-* attributes
   - Custom properties for FAL integration
   - Validation rules for data integrity

3. **Style System**: Integration with CKEditor's style drop-down

   - Custom style definitions
   - Integration with GeneralHtmlSupport
   - TYPO3-specific class handling
   - Style persistence in content

4. **Conversion**: Bidirectional HTML ↔ Model conversion

   - **Upcast**: HTML to model during editor initialization
   - **Downcast**: Model to HTML during save operations
   - Attribute mapping and transformation
   - Special handling for TYPO3 data attributes

Data Flow
=========

Image Selection Flow
--------------------

.. code-block:: text

   User clicks insert image
       ↓
   CKEditor plugin opens modal
       ↓
   Backend route loads file browser
       ↓
   User selects image
       ↓
   JavaScript receives file UID
       ↓
   Backend API returns image info
       ↓
   Dialog opens with image properties
       ↓
   User confirms settings
       ↓
   typo3image model element created
       ↓
   Content saved to database

Detailed steps:

1. **User Interaction**: Editor toolbar button clicked
2. **Modal Opening**: CKEditor executes custom command
3. **Browser Loading**: AJAX call to backend route
4. **File Selection**: User navigates FAL structure
5. **Data Retrieval**: File UID sent to backend API
6. **Properties Dialog**: JavaScript populates form with file data
7. **Confirmation**: User sets dimensions, alignment, etc.
8. **Model Creation**: CKEditor creates typo3image element
9. **Persistence**: Content saved with data-htmlarea-* attributes

Frontend Rendering Flow
------------------------

.. code-block:: text

   RTE content loaded from database
       ↓
   lib.parseFunc_RTE processes content
       ↓
   ImageRenderingController hook invoked
       ↓
   FAL file loaded from UID
       ↓
   Magic image processing applied
       ↓
   Processed image URL generated
       ↓
   HTML with processed URL rendered
       ↓
   Internal data-* attributes removed

Detailed steps:

1. **Content Retrieval**: Database query loads RTE field
2. **TypoScript Processing**: lib.parseFunc_RTE activated
3. **Hook Execution**: Custom rendering hook triggered
4. **File Loading**: FAL resolves file UID to file object
5. **Image Processing**: Magic image generation (resize, crop, etc.)
6. **URL Generation**: Processed image URL created
7. **HTML Rendering**: Final img tag generated
8. **Attribute Cleanup**: Internal data-* attributes stripped

Extension Points
================

Developers can extend the extension through:

1. **Event listeners** (PSR-14 events)

   - ``AfterPrepareConfigurationForEditorEvent``: Customize RTE configuration
   - Custom events can be added for additional hooks
   - Event priority allows fine-grained control
   - Standard TYPO3 event dispatcher patterns

2. **TypoScript configuration**

   - Override rendering settings
   - Custom image processing instructions
   - Template modifications
   - Additional CSS classes or attributes

3. **XClasses** (not recommended)

   - Last resort for core modifications
   - Potential compatibility issues
   - Better alternatives usually exist
   - Should only be used when no other option available

4. **Custom processing hooks**

   - TCEmain hooks for data manipulation
   - Content element rendering hooks
   - Custom transformations during save/load
   - Validation and sanitization extensions

5. **Additional CKEditor plugins**

   - Complementary functionality
   - Integration with typo3image plugin
   - Custom commands and UI components
   - Extended model attributes

Example Event Listener
----------------------

.. code-block:: php

   use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

   class CustomRteConfigurationListener
   {
       public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
       {
           $config = $event->getConfiguration();

           // Modify configuration as needed
           $config['typo3image']['customSetting'] = 'value';

           $event->setConfiguration($config);
       }
   }

Example TypoScript Extension
-----------------------------

.. code-block:: typoscript

   lib.parseFunc_RTE {
       tags {
           img {
               width = 1920
               height = 1080

               // Custom processing
               params = class="custom-image-class"
           }
       }
   }

Related Documentation
=====================

- :ref:`architecture-overview` - System architecture and core components
- Component Details - Detailed component breakdown
- CKEditor Integration - Editor integration details
- API Reference - Complete API documentation
