.. _adr-002-ckeditor-integration:

===========================================================
ADR-002: Native CKEditor 5 vs Custom TYPO3 Image Plugin
===========================================================

:Status: Accepted
:Date: 2025-11-09
:Authors: Development Team
:Context: RTE CKEditor Image Extension for TYPO3

.. contents:: Table of Contents
   :depth: 3
   :local:

Context and Problem Statement
==============================

TYPO3 integrates CKEditor 5 as its Rich Text Editor (RTE), and images are a fundamental content element. CKEditor 5 provides comprehensive native image plugins (Image, ImageCaption, ImageToolbar, ImageResize, ImageStyle, LinkImage) with excellent WYSIWYG capabilities including:

- Inline editable captions
- Contextual toolbars on image click
- Visual resize handles
- Pre-defined image styles (alignment, sizing)
- Image linking capabilities
- Text alternative (alt) editing

However, TYPO3 has specific requirements for file handling through its File Abstraction Layer (FAL) that are incompatible with CKEditor 5's native image implementation.

**The Question**: Should we use CKEditor 5's native image plugins or implement a custom plugin specifically for TYPO3?

Decision Drivers
================

TYPO3 Core Requirements
------------------------

- **FAL Integration**: All files must be managed through TYPO3's File Abstraction Layer
- **Reference Tracking**: ``sys_file_reference`` database records for all file usage
- **Magic Image Processing**: TYPO3's automatic image optimization and variant generation
- **Security**: File access permissions and public/non-public file handling
- **Backend Integration**: File selector dialog and metadata management

User Experience Requirements
-----------------------------

- **WYSIWYG Editing**: Inline caption editing, visual resize, contextual toolbars
- **Accessibility**: Alt text, semantic HTML structure
- **Flexibility**: Image styles, linking, sizing options
- **Performance**: Optimized image delivery

Technical Requirements
-----------------------

- **Data Persistence**: FAL attributes must survive RTE → DB → RTE round-trips
- **Backend Rendering**: PHP-based frontend rendering with TypoScript integration
- **Compatibility**: Work across TYPO3 versions (v12/v13)

Considered Options
==================

Option 1: Use CKEditor 5 Native Image Plugins
----------------------------------------------

Use the official CKEditor 5 image feature set:

- ``@ckeditor/ckeditor5-image`` (base Image plugin)
- ``@ckeditor/ckeditor5-image/imagecaption`` (ImageCaption)
- ``@ckeditor/ckeditor5-image/imagetoolbar`` (ImageToolbar)
- ``@ckeditor/ckeditor5-image/imageresize`` (ImageResize)
- ``@ckeditor/ckeditor5-image/imagestyle`` (ImageStyle)
- ``@ckeditor/ckeditor5-link/linkimage`` (LinkImage)

**Pros**:

- ✅ Excellent WYSIWYG experience out-of-the-box
- ✅ Inline editable captions with proper UX
- ✅ Contextual balloon toolbar on image selection
- ✅ Visual resize handles with dimension control
- ✅ Pre-built image styles (alignment, sizing)
- ✅ Official support and documentation
- ✅ Regular updates and bug fixes
- ✅ Accessibility features built-in
- ✅ Tested across browsers and devices

**Cons**:

- ❌ **No FAL Support**: Uses direct image URLs (``src`` attribute), not FAL file references
- ❌ **No Reference Tracking**: Cannot create ``sys_file_reference`` records
- ❌ **No Magic Image Processing**: Cannot integrate with TYPO3's image processing pipeline
- ❌ **No Backend Integration**: Cannot use TYPO3's file selector dialog
- ❌ **Data Attribute Loss**: Cannot store FAL-specific metadata (``data-htmlarea-file-uid``, ``data-htmlarea-file-table``)
- ❌ **Security Issues**: Cannot enforce TYPO3's file access permissions
- ❌ **Frontend Rendering Incompatibility**: PHP rendering expects FAL attributes, not direct URLs
- ❌ **TYPO3 Core Explicitly Disables It**: Core configuration removes the plugin

Option 2: Custom TYPO3 Image Plugin (Selected)
-----------------------------------------------

Implement a custom CKEditor 5 plugin (``typo3image``) that:

- Uses custom model element (``typo3image`` instead of ``imageBlock``)
- Stores FAL attributes in the model
- Integrates with TYPO3's file selector dialog
- Provides backend rendering through PHP controllers
- Supports magic image processing

**Pros**:

- ✅ **Full FAL Integration**: Stores file UID and table references
- ✅ **Reference Tracking**: Creates proper ``sys_file_reference`` records
- ✅ **Magic Image Processing**: Full integration with TYPO3's image pipeline
- ✅ **Backend Integration**: Uses TYPO3's file selector dialog
- ✅ **Data Persistence**: All FAL attributes preserved through save/load cycles
- ✅ **Security**: Respects TYPO3's file access permissions
- ✅ **Frontend Rendering**: PHP controllers handle all rendering logic
- ✅ **TypoScript Integration**: Full control via TypoScript configuration
- ✅ **Feature Control**: Can implement exactly what TYPO3 needs

**Cons**:

- ❌ Must implement WYSIWYG features manually (captions, toolbar, resize, styles)
- ❌ Higher development and maintenance effort
- ❌ Requires deep CKEditor 5 plugin architecture knowledge
- ❌ Must keep up with CKEditor 5 API changes
- ❌ More complex than using native plugins

Option 3: Hybrid Approach (Native Plugins + FAL Bridge)
--------------------------------------------------------

Use native CKEditor 5 plugins but add a bridge layer to convert to/from FAL.

**Pros**:

- ✅ WYSIWYG features from native plugins
- ✅ Potential FAL integration through conversion

**Cons**:

- ❌ **Model Incompatibility**: Native ``imageBlock`` model doesn't support FAL attributes
- ❌ **Data Loss Risk**: Conversion between incompatible models prone to errors
- ❌ **Plugin Conflicts**: Native plugins expect specific model schema
- ❌ **Maintenance Nightmare**: Must bridge two incompatible architectures
- ❌ **TYPO3 Core Still Disables Native Plugin**: Cannot be used without core modifications

Decision Outcome
================

**Chosen option**: Option 2 - Custom TYPO3 Image Plugin

The custom plugin approach is the **only viable option** for TYPO3 integration.

Evidence and Technical Justification
=====================================

1. TYPO3 Core Explicitly Disables Native Image Plugin
------------------------------------------------------

**File**: ``typo3/sysext/rte_ckeditor/Configuration/RTE/Default.yaml``

.. code-block:: yaml

   editor:
     config:
       removePlugins:
         - image  # ← Native Image plugin is disabled

**Source**: TYPO3 Core Documentation

   "By default, images in CKE are disabled within configuration typo3/sysext/rte_ckeditor/Configuration/RTE/Default.yaml with removePlugins: - image"

This is **not accidental** — it's a deliberate architectural decision by the TYPO3 core team.

2. FAL Requirements Incompatible with Native Plugins
-----------------------------------------------------

Required FAL Data Attributes
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: html

   <img
     src="fileadmin/user_upload/image.jpg"
     data-htmlarea-file-uid="123"
     data-htmlarea-file-table="sys_file"
     alt="Example"
     width="800"
     height="600"
   />

**Why These Matter**:

- ``data-htmlarea-file-uid``: Links to ``sys_file`` record for reference tracking
- ``data-htmlarea-file-table``: Specifies FAL table (always ``sys_file``)
- These attributes trigger ``sys_file_reference`` creation in TYPO3 backend
- Without them, TYPO3 cannot track where files are used
- Reference index breaks, file deletion safety checks fail

Native Plugin Model
~~~~~~~~~~~~~~~~~~~

.. code-block:: javascript

   // CKEditor 5 native imageBlock model
   editor.model.schema.register('imageBlock', {
     allowAttributes: ['src', 'alt', 'srcset', 'sizes']
     // ❌ No support for data-* attributes
     // ❌ No FAL metadata storage
   });

3. Magic Image Processing Requirements
---------------------------------------

**Frontend Rendering Flow**:

.. code-block:: text

   1. RTE saves: <img data-htmlarea-file-uid="123" width="800" height="600" />
   2. TYPO3 Backend: Creates sys_file_reference record
   3. Frontend Rendering (PHP):
      a. Load file from FAL via UID
      b. Apply TypoScript configuration
      c. Generate processed image variant
      d. Output: <img src="/typo3temp/processed/image_hash.jpg" />

**TypoScript Hook**:

.. code-block:: typoscript
   :caption: setup.typoscript:12

   lib.parseFunc_RTE {
       tags.img = TEXT
       tags.img {
           preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
       }
   }

**Why Native Plugins Can't Support This**:

- Native plugins use direct ``src`` URLs, not FAL UIDs
- ``ImageRenderingController->renderImageAttributes()`` expects FAL data attributes
- No way to look up file in FAL without UID
- Cannot apply magic image processing without FAL context

4. Backend Integration Requirements
------------------------------------

**TYPO3 File Selector Dialog**:

.. code-block:: javascript
   :caption: typo3image.js:349

   externalPlugins: {
     typo3image: {
       route: "rteckeditorimage_wizard_select_image"  // ← TYPO3 backend route
     }
   }

**Flow**:

1. User clicks "Insert Image" button
2. Custom plugin opens TYPO3's file selector dialog
3. User selects file from FAL storage
4. Dialog returns ``{ fileUid: 123, fileTable: 'sys_file', ... }``
5. Plugin creates ``typo3image`` model element with FAL attributes

**Native Plugin Limitation**:

- Native ``uploadImage`` plugin expects file upload or URL input
- No integration point for TYPO3's file selector
- Cannot access FAL metadata
- Cannot select existing files from storage

5. Database Reference Tracking
-------------------------------

**sys_file_reference Table**:

.. code-block:: sql

   CREATE TABLE sys_file_reference (
     uid_local int,        -- Points to sys_file.uid
     uid_foreign int,      -- Points to tt_content.uid
     tablenames varchar,   -- 'tt_content'
     fieldname varchar     -- 'bodytext'
   );

**Created by**:

.. code-block:: php
   :caption: Classes/Database/RteImagesDbHook.php:18

   $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
       = RteImagesDbHook::class;

**Why This Matters**:

- TYPO3's "Used on" feature shows where images are referenced
- Prevents deleting images still in use
- File usage statistics
- Broken link detection
- **Requires FAL UIDs** — native plugins provide only URLs

Implementation: Custom Plugin Architecture
===========================================

Model Schema
------------

.. code-block:: javascript
   :caption: typo3image.js:871-894

   editor.model.schema.register('typo3image', {
       inheritAllFrom: '$blockObject',
       allowAttributes: [
           'fileUid',          // FAL: sys_file.uid
           'fileTable',        // FAL: table name (sys_file)
           'src',              // Image URL (for editing preview)
           'alt',              // Accessibility
           'title',            // Image title
           'width',            // Display dimensions
           'height',           // Display dimensions
           'enableZoom',       // Click-to-enlarge
           'noScale',          // Skip image processing
           'quality',          // Processing quality multiplier
           'caption',          // Image caption text
           'htmlA',            // Link wrapper attributes
           // ... more FAL-specific attributes
       ]
   });

Upcast Converter (HTML → Model)
--------------------------------

.. code-block:: javascript
   :caption: typo3image.js:1061-1077

   editor.conversion.for('upcast').elementToElement({
       view: {
           name: 'img',
           attributes: {
               'data-htmlarea-file-uid': true  // ← FAL attribute required
           }
       },
       model: (viewElement, { writer }) => {
           return writer.createElement('typo3image', {
               fileUid: viewElement.getAttribute('data-htmlarea-file-uid'),
               fileTable: viewElement.getAttribute('data-htmlarea-file-table'),
               src: viewElement.getAttribute('src'),
               // ... extract all FAL attributes
           });
       }
   });

Downcast Converter (Model → HTML)
----------------------------------

.. code-block:: javascript
   :caption: typo3image.js:1113-1129

   editor.conversion.for('dataDowncast').elementToElement({
       model: 'typo3image',
       view: (modelElement, { writer }) => {
           const img = writer.createEmptyElement('img', {
               'src': modelElement.getAttribute('src'),
               'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
               'data-htmlarea-file-table': modelElement.getAttribute('fileTable'),
               // ... output all FAL attributes
           });
           return toWidget(figure, writer);
       }
   });

Frontend Rendering
------------------

.. code-block:: php
   :caption: ImageRenderingController.php (conceptual)

   public function renderImageAttributes(string $content, array $conf): string
   {
       // Extract data-htmlarea-file-uid from HTML
       $fileUid = $this->extractFileUid($content);

       // Load file from FAL
       $file = $this->resourceFactory->getFileObject($fileUid);

       // Apply magic image processing
       $processedImage = $this->imageService->applyProcessingInstructions($file, [
           'width' => $width,
           'height' => $height,
           'crop' => $cropData,
       ]);

       // Generate <img> tag with processed URL
       return sprintf('<img src="%s" alt="%s" />',
           $processedImage->getPublicUrl(),
           $alt
       );
   }

Consequences
============

Positive
--------

- ✅ **Full TYPO3 Integration**: Complete FAL support, reference tracking, magic image processing
- ✅ **Security**: Respects TYPO3's file permission system
- ✅ **Flexibility**: Can implement exactly what TYPO3 needs, no compromises
- ✅ **TypoScript Control**: Full configuration via TypoScript
- ✅ **Future-Proof**: Can add TYPO3-specific features without CKEditor limitations
- ✅ **Backward Compatibility**: Works with existing TYPO3 content and workflows

Negative
--------

- ❌ **WYSIWYG Features Missing**: Must implement caption editing, contextual toolbar, visual resize, image styles manually
- ❌ **Development Effort**: Significant implementation work for WYSIWYG features
- ❌ **Maintenance Burden**: Must track CKEditor 5 API changes and update accordingly
- ❌ **Feature Parity Challenge**: Native plugins have better UX, must match quality
- ❌ **Knowledge Requirements**: Deep CKEditor 5 plugin architecture expertise needed

Current Limitations
-------------------

The custom plugin currently lacks some WYSIWYG features present in native plugins:

1. **Caption Not Inline Editable**: Uses dialog instead of click-to-edit

   - Root cause: ``toWidget()`` wrapper prevents nested editables
   - **Status**: Implementation gap - can be addressed with proper editable nesting

2. **No Contextual Toolbar**: No balloon toolbar on image selection

   - ✅ **IMPLEMENTED** in feature/wysiwyg-caption-fixes branch via WidgetToolbarRepository

3. **No Visual Resize**: No drag handles for resizing

   - Root cause: **WidgetResize is NOT available in TYPO3's CKEditor 5 build** (confirmed in typo3image.js:1576)
   - **Status**: Architectural limitation - cannot be implemented without CKEditor build changes
   - Alternative: Resize functionality available via context toolbar buttons

4. **No Image Styles**: No pre-defined alignment/sizing options

   - ✅ **IMPLEMENTED** via balloon toolbar (alignment buttons: left/center/right/block)

**Summary**: Most gaps are implementation issues that can be addressed. Visual resize handles are an **architectural limitation** due to WidgetResize unavailability in TYPO3's CKEditor 5 build.

Compliance
==========

- **TYPO3 Core Architecture**: Follows FAL (File Abstraction Layer) patterns
- **CKEditor 5 Plugin API**: Implements official plugin architecture
- **Security**: File access control and permission checking
- **Performance**: Optimized image processing pipeline
- **Accessibility**: Semantic HTML structure support

References
==========

- `TYPO3 FAL Documentation <https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Fal/>`__
- `TYPO3 RTE Configuration <https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Rte/>`__
- `CKEditor 5 Image Feature <https://ckeditor.com/docs/ckeditor5/latest/features/images/images-overview.html>`__
- `CKEditor 5 Plugin Development <https://ckeditor.com/docs/ckeditor5/latest/framework/architecture/plugins.html>`__
- TYPO3 Core RTE Default Configuration: ``typo3/sysext/rte_ckeditor/Configuration/RTE/Default.yaml``

Future Considerations
=====================

While native CKEditor 5 plugins cannot be used directly, their **implementation patterns** can guide our custom plugin development:

- Study ``ImageCaption`` source for inline editable caption UX
- Study ``ImageToolbar`` source for contextual balloon toolbar
- Study ``ImageResize`` source for visual resize handle implementation
- Study ``ImageStyle`` source for style dropdown integration

**Goal**: Achieve feature parity with native plugins while maintaining full TYPO3 FAL integration.

Revision History
================

.. list-table::
   :header-rows: 1
   :widths: 20 20 60

   * - Date
     - Version
     - Changes
   * - 2025-11-09
     - 1.0
     - Initial ADR documenting why native CKEditor 5 plugins cannot be used with TYPO3 FAL
