.. include:: /Includes.rst.txt

.. _ckeditor-image-quality-selector:

======================
Image Quality Selector
======================

.. versionadded:: 13.1.0
   The image quality selector with SVG dimension support and multiplier-based processing.

The image dialog includes a quality selector dropdown that controls image processing
for optimal display on different devices and use cases.

Quality Options
===============

The extension provides five quality levels for processed images:

.. confval:: No Scaling

   :Multiplier: 1.0x
   :Processing: Skip image processing, use original file
   :Indicator: ● Gray

   Best for:
   - Newsletters and email
   - PDF exports
   - When maximum quality is required
   - SVG files (vector graphics)

.. confval:: Standard (1.0x)

   :Multiplier: 1.0x
   :Processing: Match display dimensions exactly
   :Indicator: ● Yellow

   Best for:
   - Standard web displays
   - Content images
   - Balanced quality and file size

.. confval:: Retina (2.0x)

   :Multiplier: 2.0x
   :Processing: 2× display dimensions
   :Indicator: ● Green

   Best for:
   - High-DPI displays (default)
   - MacBook Retina screens
   - Modern mobile devices
   - Sharp images on 4K monitors

   .. note::
      This is the default quality setting for optimal display on modern devices.

.. confval:: Ultra (3.0x)

   :Multiplier: 3.0x
   :Processing: 3× display dimensions
   :Indicator: ● Cyan

   Best for:
   - Very sharp images required
   - Large format displays
   - Hero images and key visuals

.. confval:: Print (6.0x)

   :Multiplier: 6.0x
   :Processing: 6× display dimensions
   :Indicator: ● Blue

   Best for:
   - Print-quality output
   - High-resolution downloads
   - Professional photography
   - SVG files (recommended default)

Dialog Layout
=============

The image properties dialog features a responsive 3-row layout:

**Row 1: Dimensions and Quality**
   - Display width in px (col-sm-4)
   - Display height in px (col-sm-4)
   - Scaling quality selector (col-sm-4)

**Row 2: Advisory Title**
   - Advisory Title text input (full width)

**Row 3: Alternative Text**
   - Alternative Text for accessibility (full width)

Visual Indicators
=================

Each quality option includes:

- **Color-coded marker (●)** - Visual quality indicator
- **Multiplier display** - Processing factor (e.g., "2.0x")
- **Persistent selection** - Saved with image via ``data-quality`` attribute

Technical Implementation
========================

Backend Processing
------------------

The backend automatically:

1. **Detects SVG files** - Extracts dimensions from viewBox or width/height attributes
2. **Calculates target dimensions** - Multiplies display dimensions by quality multiplier
3. **Preserves aspect ratio** - Maintains original image proportions
4. **Suggests optimal dimensions** - Provides backend dimension suggestions
5. **Respects user input** - Never overwrites user-entered dimensions

Frontend Persistence
--------------------

Quality selection persists using:

- **data-quality attribute** - Stores selected quality (none, standard, retina, ultra, print)
- **Backward compatibility** - Maps legacy data-noscale to "No Scaling"
- **Priority order** - data-quality > data-noscale > SVG default (print) > standard default (retina)

.. code-block:: html
   :caption: Rendered HTML with quality attribute

   <img src="image.jpg"
        width="400"
        height="300"
        data-quality="retina"
        alt="Example image">

SVG Support
===========

SVG (Scalable Vector Graphics) files receive special handling:

**Dimension Extraction:**

.. code-block:: xml
   :caption: SVG with viewBox

   <svg viewBox="0 0 800 600" xmlns="http://www.w3.org/2000/svg">
     <!-- Vector content -->
   </svg>

.. code-block:: xml
   :caption: SVG with width/height attributes

   <svg width="800" height="600" xmlns="http://www.w3.org/2000/svg">
     <!-- Vector content -->
   </svg>

**Processing:**

- **Default quality**: Print (6.0x) for maximum sharpness
- **No rasterization**: Original SVG file always used
- **Dimension calculation**: Extracted from viewBox or width/height
- **Aspect ratio preservation**: Automatic scaling calculation

Best Practices
==============

Choosing Quality Levels
------------------------

.. list-table::
   :header-rows: 1
   :widths: 20 30 25 25

   * - Use Case
     - Recommended Quality
     - Reason
     - File Size Impact
   * - Hero images
     - Ultra (3.0x)
     - Maximum sharpness
     - Large
   * - Content images
     - Retina (2.0x)
     - High-DPI displays
     - Medium
   * - Thumbnails
     - Standard (1.0x)
     - Sufficient quality
     - Small
   * - Newsletters/Email
     - No Scaling
     - Original quality
     - Varies
   * - SVG graphics
     - Print (6.0x)
     - Vector sharpness
     - None (vector)

Performance Considerations
--------------------------

**Higher quality = Larger file size**

- Retina (2.0x): ~4× file size vs Standard
- Ultra (3.0x): ~9× file size vs Standard
- Print (6.0x): ~36× file size vs Standard

**Optimization tips:**

1. Use appropriate quality for context (not always maximum)
2. Consider mobile bandwidth for content images
3. Use "No Scaling" for SVG files when possible
4. Balance visual quality with page load performance

Migration from noScale
======================

The quality selector replaces the legacy "Skip Image Processing" checkbox:

**Before (deprecated):**

.. code-block:: html

   <img src="image.jpg" data-noscale="1">

**After (modern):**

.. code-block:: html

   <img src="image.jpg" data-quality="none">

**Backward compatibility:**

- Legacy ``data-noscale`` attributes are automatically mapped to "No Scaling" quality
- Existing images continue to work without modification
- New images use ``data-quality`` attribute

See Also
========

- :ref:`integration-configuration-image-processing` - Image processing configuration
- :ref:`integration-configuration-frontend-rendering` - Frontend TypoScript setup
- :ref:`troubleshooting-performance-issues` - Performance optimization guide
