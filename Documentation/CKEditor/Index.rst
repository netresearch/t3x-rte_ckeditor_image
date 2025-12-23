.. include:: /Includes.rst.txt

.. _ckeditor-plugin-development:

==============================
CKEditor Plugin Development
==============================

Complete documentation for the CKEditor 5 plugin implementation.

The typo3image plugin is a custom CKEditor 5 plugin that integrates TYPO3's File Abstraction Layer (FAL) with the rich text editor, enabling seamless image management within the CKEditor interface.

Plugin Components
=================

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: 🔌 Plugin Development

        Plugin architecture, UI components, commands, and event handling

        ..  card-footer:: :ref:`Read more <ckeditor-plugin-development-guide>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 📐 Model Element

        The typo3image custom element schema, attributes, and model integration

        ..  card-footer:: :ref:`Read more <ckeditor-model-element>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🎨 Style Integration

        Style system integration with StyleUtils and GeneralHtmlSupport (critical for v13.0.0+)

        ..  card-footer:: :ref:`Read more <ckeditor-style-integration>`
            :button-style: btn btn-primary stretched-link

    ..  card:: ↔️ Conversions

        HTML ↔ Model conversion patterns for upcast and downcast transformations

        ..  card-footer:: :ref:`Read more <ckeditor-conversions>`
            :button-style: btn btn-primary stretched-link

    ..  card:: 🎚️ Image Quality Selector

        Quality multipliers, SVG support, and dimension handling

        ..  card-footer:: :ref:`Read more <ckeditor-image-quality-selector>`
            :button-style: btn btn-primary stretched-link

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
- :ref:`integration-configuration` - Plugin configuration
- :ref:`examples-common-use-cases` - Practical implementation examples

.. toctree::
   :hidden:
   :maxdepth: 1

   Plugin-Development
   Model-Element
   Style-Integration
   Conversions
   Image-Quality-Selector
