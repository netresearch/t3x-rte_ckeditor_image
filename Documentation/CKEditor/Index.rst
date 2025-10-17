.. _ckeditor-plugin-development:

==============================
CKEditor Plugin Development
==============================

Complete documentation for the CKEditor 5 plugin implementation.

Overview
========

The typo3image plugin is a custom CKEditor 5 plugin that integrates TYPO3's File Abstraction Layer (FAL) with the rich text editor, enabling seamless image management within the CKEditor interface.

Available Documentation
========================

Plugin Development
------------------

:ref:`ckeditor-plugin-development-guide`

Complete plugin architecture and implementation:

- Plugin structure and initialization
- UI components (button, toolbar, dialog)
- Commands and command execution
- Integration with CKEditor ecosystem
- Event handling and lifecycle

Model Element
-------------

:ref:`ckeditor-model-element`

The typo3image custom model element:

- Element schema and attributes
- Model manipulation methods
- Attribute handling and validation
- Integration with CKEditor model
- Differences from standard ``imageBlock``

Style Integration
-----------------

:ref:`ckeditor-style-integration`

Critical for v13.0.0+ - Style system integration:

- StyleUtils and GeneralHtmlSupport dependencies
- Style application to typo3image elements
- Configuration and customization
- Troubleshooting style issues
- Bug fixes in v13.0.0

Conversions
-----------

:ref:`ckeditor-conversions`

Upcast and downcast conversion patterns:

- HTML → Model (upcast) conversions
- Model → HTML (downcast) conversions
- Attribute conversion patterns
- Custom conversion implementations
- Debugging conversion issues

Quick Links
===========

Getting Started
---------------

1. Read :ref:`ckeditor-plugin-development-guide` for overall structure
2. Understand :ref:`ckeditor-model-element` for data handling
3. Study :ref:`ckeditor-conversions` for HTML transformation

Common Tasks
------------

- **Add custom attributes** → See :ref:`ckeditor-model-element`
- **Implement custom styles** → See :ref:`ckeditor-style-integration`
- **Debug conversion issues** → See :ref:`ckeditor-conversions`
- **Extend plugin features** → See :ref:`ckeditor-plugin-development-guide`

Critical Information
====================

Version 13.0.0+ Requirements
-----------------------------

The plugin **requires** these CKEditor dependencies:

.. code-block:: javascript

   static get requires() {
       return ['StyleUtils', 'GeneralHtmlSupport'];
   }

.. warning::
   Missing either dependency will disable style functionality. See :ref:`ckeditor-style-integration` for details.

Related Documentation
=====================

- :ref:`api-documentation` - PHP backend integration
- :ref:`configuration` - Plugin configuration
- :ref:`common-use-cases` - Practical implementation examples
