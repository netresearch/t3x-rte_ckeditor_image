.. _documentation-index:

====================================
RTE CKEditor Image - Documentation
====================================

Comprehensive documentation for the TYPO3 CKEditor Image extension (v13.0.0+).

.. contents:: Table of Contents
   :depth: 2
   :local:

Documentation Structure
=======================

Architecture & Design
---------------------

- **Architecture Overview** (:ref:`architecture-overview`) - System design, component architecture, and data flow diagrams

API Documentation
-----------------

- **Controllers** - SelectImageController, ImageRenderingController, and ImageLinkRenderingController APIs
- **Event Listeners** - PSR-14 event system, RteConfigurationListener, and configuration injection
- **Data Handling** - RteImagesDbHook, soft references, magic images, and database processing

CKEditor Plugin
---------------

- **Plugin Development** - Complete plugin architecture, UI components, and integration patterns
- **Model Element** - typo3image model element schema, attributes, and manipulation
- **Style Integration** - StyleUtils and GeneralHtmlSupport integration (critical for v13.0.0+)
- **Conversions** - Upcast/downcast system for HTML ↔ Model transformations

Integration & Configuration
----------------------------

- **Configuration Guide** - Complete RTE, TSConfig, and TypoScript configuration reference

Troubleshooting
---------------

- **Common Issues** - FAQ, solutions, and debugging techniques for frequent problems

Examples
--------

- **Common Use Cases** - 10+ practical examples: responsive images, lightbox, lazy loading, custom styles, testing

Quick Start
===========

For basic installation and usage, see the main README.md in the project root.

Essential Reading for Setup
----------------------------

1. Configuration Guide - All configuration options
2. :ref:`architecture-overview` - Understand the system
3. Common Issues - Troubleshoot problems

Essential Reading for Development
----------------------------------

1. Plugin Development - CKEditor plugin structure
2. API Documentation - Backend controllers
3. Model Element - Custom model element

Critical Information
====================

Version 13.0.0+ Requirements
-----------------------------

.. important::
   **MANDATORY Dependencies**

   Both plugins are **required** for style functionality. Missing either causes style drop-down to be disabled for images.

.. code-block:: javascript

   static get requires() {
       return ['StyleUtils', 'GeneralHtmlSupport'];
   }

Recent Bug Fixes
----------------

- **v13.0.0**: Fixed style integration with ``GeneralHtmlSupport`` dependency
- **v13.0.0**: Improved style application to typo3image elements
- See Common Issues for migration guidance

Documentation Use Cases
=======================

"I want to add custom image styles"
------------------------------------

- Read Configuration Guide (Custom Image Styles section)
- See examples in Common Use Cases (Custom Image Styles)

"Images not appearing in frontend"
-----------------------------------

- Check Common Issues (Frontend Rendering Issues)
- Verify Configuration Guide (Frontend Rendering Setup)

"Style drop-down disabled for images"
--------------------------------------

- Read Common Issues (Style Drop-down Not Working)
- Understand Style Integration (The Style System Problem)

"Need to customize image processing"
-------------------------------------

- Study Data Handling API (Image Processing Methods)
- See Common Use Cases (Custom Backend Processing)

"Developing custom CKEditor plugin features"
---------------------------------------------

- Start with Plugin Development
- Understand Model Element and Conversions

Development Guide
=================

Contributing to Extension
--------------------------

**Step 1**: Understand the architecture

- :ref:`architecture-overview` - System design
- API Documentation - Backend components

**Step 2**: Learn the CKEditor integration

- Plugin Development - Plugin structure
- Model Element - Custom model
- Style Integration - Style system

**Step 3**: Follow development patterns

- Data Handling - Backend processing patterns
- Event Listeners - Event-driven architecture

Testing
-------

See Common Use Cases (Testing Examples) for:

- Functional test examples
- Unit test examples
- Test execution commands

Additional Resources
====================

- **GitHub Repository**: https://github.com/netresearch/t3x-rte_ckeditor_image
- **TYPO3 Extension Repository**: https://extensions.typo3.org/extension/rte_ckeditor_image
- **Packagist**: https://packagist.org/packages/netresearch/rte-ckeditor-image
- **Issue Tracker**: https://github.com/netresearch/t3x-rte_ckeditor_image/issues
- **TYPO3 Documentation**: https://docs.typo3.org/

Documentation Conventions
=========================

- **Code examples**: PHP 8.2+ features, strict types
- **Configuration examples**: TYPO3 13.4+ compatible
- **File paths**: Use ``EXT:extension_key`` notation
- **Class names**: Fully-qualified namespaces (FQN)
- **Code style**: PSR-12 / PER-CS 2.0 compliant

Documentation Coverage
======================

This documentation covers:

- ✅ Complete API reference for all PHP classes
- ✅ Full CKEditor plugin documentation
- ✅ Configuration options (RTE, TSConfig, TypoScript)
- ✅ 10+ practical use case examples
- ✅ Troubleshooting guide with solutions
- ✅ Architecture and design patterns
- ✅ Event system and hooks
- ✅ Model/view/conversion system

Contributing to Documentation
==============================

Found an error or want to improve the documentation?

1. Check existing issues: https://github.com/netresearch/t3x-rte_ckeditor_image/issues
2. Submit corrections or improvements via pull request
3. Follow documentation conventions above
4. Update cross-references when adding new sections

Documentation History
=====================

**v13.0.0**: Major documentation update

- Added detailed API reference
- Comprehensive CKEditor plugin documentation
- Style integration bug fix documentation
- 10+ practical examples
- Complete troubleshooting guide
