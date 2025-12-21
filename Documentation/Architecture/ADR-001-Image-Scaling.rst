.. _adr-001-image-scaling:

====================================
ADR-001: Image Scaling Behavior
====================================

:Status: Accepted
:Date: 2025-10-27
:Authors: Development Team
:Context: RTE CKEditor Image Extension for TYPO3

.. contents:: Table of Contents
   :depth: 3
   :local:

Context and Problem Statement
==============================

The RTE CKEditor Image extension needs to provide flexible image processing options that balance quality, performance, and file size. Users need clear control over when images should be processed versus when original files should be used directly.

The system must handle various scenarios:

- Different display quality requirements (web, retina displays, print)
- Performance optimization (avoid unnecessary processing)
- File size considerations (prevent serving oversized originals)
- SVG handling (vector graphics don't need raster processing)

Decision Drivers
=================

- **User Control**: Clear options for when to process vs use originals
- **Performance**: Avoid unnecessary image processing operations
- **Quality**: Provide appropriate quality for different use cases
- **File Size**: Prevent serving excessively large files to browsers
- **Browser Compatibility**: Ensure proper rendering across devices

Considered Options
==================

Option 1: Always Process Images
--------------------------------

- **Pros**: Consistent behavior, predictable output
- **Cons**: Unnecessary processing, performance overhead, potential quality loss

Option 2: Never Process Images (Always Use Originals)
------------------------------------------------------

- **Pros**: Maximum quality, no processing overhead
- **Cons**: Large file sizes, no optimization for different displays

Option 3: Intelligent Conditional Processing (Selected)
--------------------------------------------------------

- **Pros**: Balances quality, performance, and file size
- **Cons**: More complex logic, requires configuration

Decision Outcome
================

**Chosen option**: Option 3 - Intelligent Conditional Processing

The system implements a multi-tier approach with explicit user control and automatic optimization.

Image Processing Modes
=======================

1. No Scaling (Skip Processing Entirely)
-----------------------------------------

**Behavior**: Use original file without any TYPO3 image processing

**When to Use**:

- Newsletters (external email clients)
- PDF generation
- When maximum quality is required
- When original file optimization is already optimal

**Backend Attribute**: ``data-noscale="1"``

**Example Scenario**:

.. code-block:: text

   Original Image: 1920×1080 px (500 KB)
   Display Size:   1920×1080 px
   Processing:     NONE - original file used directly
   Output URL:     /fileadmin/user_upload/image.jpg
   Result:         Exact original file served (500 KB)

**Processing Info Message** (Gray):

.. code-block:: text

   Processing Info: Image 1920×1080 px will be displayed at 1920×1080 px = ● Standard Quality (1.0x scaling)

2. Low Quality (0.9x Multiplier)
---------------------------------

**Behavior**: Process with reduced quality for smaller file sizes

**When to Use**:

- Thumbnail images
- Background images where quality is less critical
- Bandwidth-constrained scenarios

**Example Scenario**:

.. code-block:: text

   Original Image: 1920×1080 px
   Display Size:   800×450 px
   Multiplier:     0.9x
   Calculation:    800×450 × 0.9 = 720×405 px
   Processing:     YES - image scaled and compressed
   Output URL:     /typo3temp/assets/processed/image_[hash].jpg
   Result:         Processed file at 720×405 px

**Processing Info Message** (Red):

.. code-block:: text

   Processing Info: Image 1920×1080 px will be resized to 720×405 px and displayed at 800×450 px = ● Low Quality (0.9x scaling)

3. Standard Quality (1.0x Multiplier)
--------------------------------------

**Behavior**: Process with exact display dimensions

**When to Use**:

- General content images
- Standard web displays (non-retina)
- Balanced quality and file size

**Example Scenario**:

.. code-block:: text

   Original Image: 1920×1080 px
   Display Size:   800×450 px
   Multiplier:     1.0x
   Calculation:    800×450 × 1.0 = 800×450 px
   Processing:     YES - image scaled to exact display size
   Output URL:     /typo3temp/assets/processed/image_[hash].jpg
   Result:         Processed file at 800×450 px

**Processing Info Message** (Orange):

.. code-block:: text

   Processing Info: Image 1920×1080 px will be resized to 800×450 px and displayed at 800×450 px = ● Standard Quality (1.0x scaling)

4. Retina Quality (2.0x Multiplier)
------------------------------------

**Behavior**: Process with 2x display dimensions for high-DPI screens

**When to Use**:

- Retina/HiDPI displays (MacBook, iPhone, modern monitors)
- High-quality content imagery
- Professional photography portfolios

**Example Scenario**:

.. code-block:: text

   Original Image: 1920×1500 px
   Display Size:   960×750 px
   Multiplier:     2.0x
   Calculation:    960×750 × 2.0 = 1920×1500 px
   Processing:     YES - image scaled for retina displays
   Output URL:     /typo3temp/assets/processed/image_[hash].jpg
   Result:         Processed file at 1920×1500 px

**Processing Info Message** (Green):

.. code-block:: text

   Processing Info: Image 1920×1500 px will be resized to 1920×1500 px and displayed at 960×750 px = ● Retina Quality (2.0x scaling)

5. Ultra Quality (3.0x Multiplier)
-----------------------------------

**Behavior**: Process with 3x display dimensions for ultra-high-DPI

**When to Use**:

- 4K/5K displays
- Professional design work
- Maximum quality requirements

**Example Scenario**:

.. code-block:: text

   Original Image: 5760×3240 px
   Display Size:   640×360 px
   Multiplier:     3.0x
   Calculation:    640×360 × 3.0 = 1920×1080 px
   Processing:     YES - image scaled for ultra displays
   Output URL:     /typo3temp/assets/processed/image_[hash].jpg
   Result:         Processed file at 1920×1080 px

**Processing Info Message** (Cyan):

.. code-block:: text

   Processing Info: Image 5760×3240 px will be resized to 1920×1080 px and displayed at 640×360 px = ● Ultra Quality (3.0x scaling)

6. Print Quality (6.0x Multiplier)
-----------------------------------

**Behavior**: Process with 6x display dimensions for print output

**When to Use**:

- Print-ready materials
- High-resolution documents
- Maximum quality print output

**Example Scenario**:

.. code-block:: text

   Original Image: 5760×3240 px
   Display Size:   320×180 px
   Multiplier:     6.0x
   Calculation:    320×180 × 6.0 = 1920×1080 px
   Processing:     YES - image scaled for print quality
   Output URL:     /typo3temp/assets/processed/image_[hash].jpg
   Result:         Processed file at 1920×1080 px

**Processing Info Message** (Blue):

.. code-block:: text

   Processing Info: Image 5760×3240 px will be resized to 1920×1080 px and displayed at 320×180 px = ● Print Quality (6.0x scaling)

Quality Calculation Logic
==========================

Achievable Quality
------------------

The system automatically determines what quality can actually be achieved based on the original image dimensions:

**Quality Formula**: ``actualQuality = min(imageWidth / displayWidth, imageHeight / displayHeight)``

**Examples**:

- Image 1920×1500, Display 960×750 → Quality = min(1920/960, 1500/750) = min(2.0, 2.0) = **2.0x (Retina)**
- Image 1920×1500, Display 192×150 → Quality = min(1920/192, 1500/150) = min(10.0, 10.0) = **10.0x (Print)**
- Image 1920×1500, Display 1920×1500 → Quality = min(1920/1920, 1500/1500) = min(1.0, 1.0) = **1.0x (Standard)**
- Image 1920×1500, Display 2840×3000 → Quality = min(1920/2840, 1500/3000) = min(0.68, 0.5) = **0.5x (Poor)**

Processing Multipliers vs Achievable Quality
---------------------------------------------

When a processing option (Standard, Retina, Ultra, Print) is selected:

- **Requested Size** = Display × Multiplier
- **Processed Size** = min(Requested Size, Original Size) — **Never upscale!**
- **Actual Quality** = Processed Size / Display Size

**Example**: Image 1920×1500, Display 1920×1500, Retina (2.0x)

- Requested: 1920×1500 × 2.0 = 3840×3000
- Processed: min(3840×3000, 1920×1500) = 1920×1500
- Actual Quality: 1920/1920 = 1.0x (Standard, not Retina!)

Automatic Optimization Rules
=============================

Rule 1: SVG Files (Always Skip Processing)
-------------------------------------------

**Behavior**: SVG files are NEVER processed regardless of settings

**Rationale**:

- SVG is vector format that scales perfectly at any resolution
- ImageMagick would rasterize SVG, losing vector benefits
- Browser handles SVG scaling natively

**Example**:

.. code-block:: text

   File:           logo.svg (vector)
   Display Size:   400×300 px
   Setting:        ANY (ignored for SVG)
   Processing:     NONE - original SVG used
   Result:         Browser scales SVG natively

**Processing Info Message** (Gray):

.. code-block:: text

   Processing Info: Vector image will not be processed (scales perfectly at any resolution).

Rule 2: Dimensions Match Exactly (Skip Processing)
---------------------------------------------------

**Behavior**: When display dimensions exactly match original, skip processing

**Rationale**:

- No resize needed = no quality benefit from processing
- Avoid unnecessary processing overhead
- Preserve original file quality

**Example**:

.. code-block:: text

   Original Image: 1920×1080 px
   Display Size:   1920×1080 px
   Setting:        Standard (1.0x)
   Processing:     NONE - dimensions match exactly
   Result:         Original file used

**Processing Info Message** (varies by scaling option):

.. code-block:: text

   No Scaling: Image 1920×1080 px will be used unchanged (no processing)
   Standard (1.0x): Image 1920×1080 px will be displayed at 1920×1080 px = ● Standard Quality (1.0x scaling)
   Retina (2.0x): Image 1920×1080 px will be displayed at 1920×1080 px = ● Standard Quality (1.0x scaling) [cannot achieve 2.0x]

**Note**: When requested quality cannot be achieved (original image too small), the message shows the actual achievable quality.

**Note**: This rule is overridden by file size threshold (see Rule 4).

Rule 3: Display Exceeds Image Size (Skip Processing + Warning)
---------------------------------------------------------------

**Behavior**: When display size is larger than original image, skip processing and warn

**Rationale**:

- Upscaling degrades quality
- Better to use original at natural size
- User should be aware of quality limitation

**Example**:

.. code-block:: text

   Original Image: 800×600 px (small original)
   Display Size:   1920×1080 px (larger than original)
   Setting:        Retina (2.0x)
   Processing:     NONE - cannot upscale quality
   Result:         Original 800×600 px used, stretched by browser
   Warning:        Quality degradation expected

**Processing Info Message** (Red warning):

.. code-block:: text

   Processing Info: Image 800×600 px will be displayed at 1920×1080 px = ● Poor Quality (0.4x scaling)

Rule 4: File Size Threshold (Force Processing)
-----------------------------------------------

**Behavior**: Large files are processed even when dimensions match

**Configuration**:

.. code-block:: typoscript

   lib.parseFunc_RTE.tags.img {
       noScale = 1
       noScale {
           maxFileSizeForAuto = 2000000  # 2MB threshold
       }
   }

**Rationale**:

- Prevent serving multi-megabyte originals
- Optimize file size through compression
- Balance quality and bandwidth

**Example**:

.. code-block:: text

   Original Image: 1920×1080 px (5 MB uncompressed TIFF)
   Display Size:   1920×1080 px (exact match)
   File Size:      5,242,880 bytes (> 2MB threshold)
   Processing:     YES - exceeds size threshold
   Result:         Processed JPEG at 1920×1080 px (~500 KB)

Frontend Rendering (ImageRenderingController)
==============================================

Processing Decision Logic
--------------------------

.. code-block:: php

   protected function shouldSkipProcessing(
       File $originalFile,
       array $imageConfiguration,
       bool $noScale,
       int $maxFileSizeForAuto = 0
   ): bool {
       // RULE 1: SVG files - always skip
       if (strtolower($originalFile->getExtension()) === 'svg') {
           return true;
       }

       // RULE 2: Explicit noScale setting OR data-noscale attribute
       if ($noScale) {
           return true;
       }

       // Get dimensions
       $originalWidth = (int) ($originalFile->getProperty('width') ?? 0);
       $originalHeight = (int) ($originalFile->getProperty('height') ?? 0);
       $requestedWidth = (int) ($imageConfiguration['width'] ?? 0);
       $requestedHeight = (int) ($imageConfiguration['height'] ?? 0);

       // RULE 3: No dimensions requested - use original
       if ($requestedWidth === 0 && $requestedHeight === 0) {
           return true;
       }

       // RULE 4: Dimensions match exactly
       if ($requestedWidth === $originalWidth && $requestedHeight === $originalHeight) {
           // Check file size threshold
           if ($maxFileSizeForAuto > 0) {
               $fileSize = $originalFile->getSize();
               // Exceeds threshold - process to reduce size
               if ($fileSize > $maxFileSizeForAuto) {
                   return false;
               }
           }
           // Within threshold or no limit - skip processing
           return true;
       }

       // Different dimensions - processing needed
       return false;
   }

Configuration Examples
=======================

Global No Processing (All RTE Images)
--------------------------------------

.. code-block:: typoscript
   :caption: TypoScript Setup

   # TypoScript Setup
   lib.parseFunc_RTE.tags.img.noScale = 1

**Result**: ALL images use originals, no processing

Selective No Processing (Per Image)
------------------------------------

Users set "No Scaling" option in image dialog.

**Result**: Only images with ``data-noscale="1"`` skip processing

File Size Optimized
-------------------

.. code-block:: typoscript
   :caption: TypoScript Setup

   lib.parseFunc_RTE.tags.img {
       noScale = 0  # Enable processing
       noScale {
           maxFileSizeForAuto = 2000000  # 2MB
       }
   }

**Result**: Images processed only when needed, automatic optimization for large files

User Interface Indicators
==========================

Color Coding
------------

.. list-table::
   :header-rows: 1
   :widths: 20 20 20 40

   * - Quality
     - Color
     - Hex
     - Usage
   * - No Scaling
     - Gray
     - #6c757d
     - No processing
   * - Low
     - Red
     - #dc3545
     - Reduced quality
   * - Standard
     - Orange
     - #ffc107
     - Balanced
   * - Retina
     - Green
     - #28a745
     - High quality
   * - Ultra
     - Cyan
     - #17a2b8
     - Ultra quality
   * - Print
     - Blue
     - #007bff
     - Print quality

Processing Info States
-----------------------

1. **No Processing** (Gray) - Original file used
2. **Normal Processing** (Blue) - Standard resize operation
3. **Exact Match** (Green) - No processing needed, dimensions match
4. **Oversized Display** (Red) - Warning about quality limitation

Technical Implications
======================

Backend (SelectImageController)
--------------------------------

- **Validation**: Enforce dimension limits (1-10000px) to prevent resource exhaustion
- **Security**: Verify file access permissions (IDOR protection)
- **Performance**: Use efficient file property access

Frontend (ImageRenderingController)
------------------------------------

- **Caching**: Processed images cached in ``typo3temp/assets/``
- **Security**: Block non-public files from frontend rendering
- **Performance**: Skip processing when possible to reduce server load

JavaScript (typo3image.js)
---------------------------

- **Real-time Calculation**: Show expected output dimensions
- **Visual Feedback**: Color-coded quality indicators
- **Validation**: Prevent invalid dimension combinations

Consequences
============

Positive
--------

- **Flexibility**: Users control when processing occurs
- **Performance**: Automatic optimization reduces unnecessary operations
- **Quality**: Appropriate processing for different use cases
- **File Size**: Prevents serving oversized originals

Negative
--------

- **Complexity**: More logic to maintain and test
- **Learning Curve**: Users need to understand when to use each option
- **Edge Cases**: Requires careful handling of dimension mismatches

Compliance
==========

- **TYPO3 Standards**: Follows FAL (File Abstraction Layer) patterns
- **Security**: Implements access control and resource limits
- **Performance**: Optimizes for typical web usage patterns

References
==========

- `TYPO3 Image Processing <https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Fal/>`__
- `Retina Display Guidelines <https://developer.apple.com/design/human-interface-guidelines/images>`__
- `Image Optimization Best Practices <https://web.dev/fast/#optimize-your-images>`__

Revision History
================

.. list-table::
   :header-rows: 1
   :widths: 20 20 60

   * - Date
     - Version
     - Changes
   * - 2025-10-27
     - 1.0
     - Initial ADR documenting scaling behavior
