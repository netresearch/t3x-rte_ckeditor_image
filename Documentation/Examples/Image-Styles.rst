.. include:: /Includes.rst.txt

.. Copyright (c) 2025-2026 Netresearch DTT GmbH
   SPDX-License-Identifier: AGPL-3.0-or-later

.. _examples-image-styles:

============
Image styles
============

This extension provides **two ways** to apply styles to images in the editor.
Understanding the difference is important for choosing the right approach.

.. contents:: Table of Contents
   :depth: 3
   :local:

Overview: Two styling approaches
================================

.. important::

    There are two separate styling systems:

    1. **Built-in balloon toolbar** (works out of the box)
    2. **Native CKEditor style dropdown** (requires configuration)

    Most users should use the **built-in balloon toolbar** which works
    automatically with the ``rteWithImages`` preset.

Built-in balloon toolbar (recommended)
======================================

The extension provides **built-in image alignment buttons** that appear in a
balloon toolbar when you click on an image.

**No configuration required** - works out of the box with the
``rteWithImages`` preset.

Available styles
----------------

.. list-table::
   :header-rows: 1
   :widths: 25 25 50

   * - Button
     - CSS Class
     - Effect
   * - Align Left
     - ``image-left``
     - Float left with text wrap
   * - Align Center
     - ``image-center``
     - Centered block element
   * - Align Right
     - ``image-right``
     - Float right with text wrap
   * - Block
     - ``image-block``
     - Full-width block element

The toolbar also has an edit button, a caption toggle and a button that
switches the image between block and inline type. An inline image gets the
``image-inline`` class.

How to use
----------

1. Insert an image using the :guilabel:`Insert Image` button in the toolbar.
2. **Click on the image** in the editor.
3. A **balloon toolbar** appears above the image with alignment buttons.
4. Click an alignment button to apply the style.

.. note::

    The balloon toolbar appears directly above the selected image with icons
    for editing, caption, image type (block or inline) and alignment
    (left, center, right, block).

Toolbar items
-------------

The balloon toolbar is defined in the plugin (``typo3image.js``) and is not
configurable. It contains these items:

.. code-block:: text
    :caption: Balloon toolbar items

    editTypo3Image | toggleImageCaption | toggleImageType |
    imageStyle:image-left, imageStyle:image-center,
    imageStyle:image-right, imageStyle:image-block

.. note::

    ``rteWithImages.yaml`` contains a ``typo3image.toolbar`` list, but the
    plugin does not read it. Changing it has no effect.

CSS styles
----------

The extension provides CSS styles in :file:`image-alignment.css`:

.. code-block:: css
    :caption: Built-in alignment CSS (excerpt)

    .image-left { float: left; margin: 0 1rem 1rem 0; clear: left; }
    .image-right { float: right; margin: 0 0 1rem 1rem; clear: right; }
    .image-center { display: block; margin-left: auto; margin-right: auto; margin-bottom: 1rem; }
    .image-inline { display: inline-block; vertical-align: middle; margin: 0 0.25rem; }
    .image-block { display: block; width: 100%; margin: 1rem 0; }

.. tip::

    The Site Set includes this CSS in the frontend. Without the Site Set,
    include it yourself:

    .. code-block:: typoscript

        page {
            includeCSS {
                imageAlignment = EXT:rte_ckeditor_image/Resources/Public/Css/image-alignment.css
            }
        }

Caption width constraint
------------------------

.. versionadded:: 13.6.0

Figures with captions are automatically constrained to the image width
via an inline ``max-width`` style on the ``<figure>`` element. This
prevents long caption text from expanding the figure beyond the image
boundary.

.. figure:: /Images/caption-width-fix-667.png
    :alt: Before and after comparison of figcaption width constraint
    :class: with-shadow

    Left: caption overflows image. Right: caption wraps within
    image width.

The constraint is applied by the Fluid partial
:file:`Partials/Image/Figure.html`:

.. code-block:: html
    :caption: Figure.html — conditional max-width

    <figure class="{figureClasses}{additionalClass}"
            {f:if(condition: image.width,
              then: ' style="max-width: {image.width}px"')}>

Long unbreakable words or URLs in captions break via
``overflow-wrap: break-word`` in :file:`image-alignment.css`.

Native CKEditor style dropdown (advanced)
=========================================

For **custom styles** beyond basic alignment, you can use the native CKEditor
style dropdown. This requires additional configuration.

.. important::

    The native style dropdown is **separate** from the built-in balloon
    toolbar buttons. Use this approach when you need custom CSS classes
    (e.g., Bootstrap utilities).

Requirements
------------

1. Add ``style`` to your toolbar configuration.
2. Define style definitions for ``img`` elements.
3. Ensure ``StyleUtils`` and ``GeneralHtmlSupport`` plugins are loaded
   (automatic).

Configuration example
---------------------

.. code-block:: yaml
    :caption: EXT:my_site/Configuration/RTE/MyPreset.yaml

    imports:
      - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Default.yaml" }

    editor:
      config:
        # Add 'style' to the toolbar
        toolbar:
          items:
            - style           # <-- Required for style dropdown
            - heading
            - '|'
            - insertimage
            - link
            - '|'
            - bold
            - italic

        # Define styles for images
        style:
          definitions:
            - name: 'Float Left (Bootstrap)'
              element: 'img'
              classes: ['float-start', 'me-3', 'mb-3']
            - name: 'Float Right (Bootstrap)'
              element: 'img'
              classes: ['float-end', 'ms-3', 'mb-3']
            - name: 'Centered'
              element: 'img'
              classes: ['d-block', 'mx-auto']
            - name: 'Rounded'
              element: 'img'
              classes: ['rounded']
            - name: 'Thumbnail'
              element: 'img'
              classes: ['img-thumbnail']

How to use
----------

1. Insert an image.
2. **Select the image** (click on it).
3. Open the :guilabel:`Style` dropdown in the main toolbar.
4. Select a style from the dropdown.

.. note::

    The style dropdown only shows styles applicable to the selected element.
    Make sure you have the image selected when looking for image styles.

The style dropdown groups the definitions automatically into block styles
and text styles. CKEditor 5 has no option for custom style groups.

Troubleshooting
===============

Style dropdown not appearing
----------------------------

**Symptoms**: No "Styles" dropdown visible in the toolbar.

**Solution**: Add ``style`` to your toolbar configuration:

.. code-block:: yaml

    editor:
      config:
        toolbar:
          items:
            - style    # <-- Add this
            - heading
            - insertimage
            # ...

Styles disabled when image selected
-----------------------------------

**Symptoms**: Style dropdown shows options but they're grayed out for images.

**Cause**: Missing dependencies or incorrect style definitions.

**Solution**: Verify your style definitions target ``img`` elements:

.. code-block:: yaml

    style:
      definitions:
        - name: 'My Image Style'
          element: 'img'      # <-- Must be 'img'
          classes: ['my-class']

Balloon toolbar not showing
---------------------------

**Symptoms**: No toolbar appears when clicking an image.

**Solution**: Ensure you're using the ``rteWithImages`` preset or a preset
that imports the image plugin.

.. code-block:: yaml
    :caption: Check your preset includes the image plugin

    imports:
      - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Default.yaml" }

Choosing the right approach
===========================

.. list-table::
   :header-rows: 1
   :widths: 20 40 40

   * - Feature
     - Balloon Toolbar (Built-in)
     - Style Dropdown (Native)
   * - Configuration
     - None required
     - YAML configuration needed
   * - Styles available
     - 4 alignment options (left, center, right, block) plus inline/block toggle
     - Custom (you define them)
   * - Location
     - Balloon above image
     - Main toolbar dropdown
   * - Best for
     - Basic alignment
     - Custom Bootstrap/CSS classes
   * - CSS included
     - Yes (image-alignment.css)
     - No (provide your own)

**Recommendation**: Start with the built-in balloon toolbar. Only configure
the native style dropdown if you need custom CSS classes beyond the basic
alignments.

Related documentation
=====================

- :ref:`ckeditor-style-integration` - Technical details on StyleUtils
- :ref:`examples-responsive-images` - Responsive image examples
- :ref:`integration-configuration` - Configuration guide
