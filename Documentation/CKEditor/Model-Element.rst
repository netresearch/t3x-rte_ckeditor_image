.. include:: /Includes.rst.txt

.. _ckeditor-model-element:

==================================
CKEditor Model Element Reference
==================================

Complete reference for the ``typo3image`` custom model element in CKEditor 5.

Overview
========

The ``typo3image`` is a custom model element that represents TYPO3 FAL-integrated images in the CKEditor document model. It extends CKEditor's base ``$blockObject`` and includes TYPO3-specific attributes for FAL integration, image processing, and metadata management.

**File**: ``Resources/Public/JavaScript/Plugins/typo3image.js``

Model vs View Architecture
===========================

Understanding CKEditor 5 Architecture
--------------------------------------

CKEditor 5 uses a Model-View-Controller (MVC) architecture::

   ┌─────────────────────────────────────────────────┐
   │ Model Layer (Data)                              │
   │ - Abstract representation of document           │
   │ - Business logic and validation                 │
   │ - typo3image element with attributes            │
   └──────────────────┬──────────────────────────────┘
                      │
                      │ Conversions
                      │
   ┌──────────────────▼──────────────────────────────┐
   │ View Layer (DOM)                                │
   │ - Visual representation in editor               │
   │ - <img> elements with HTML attributes           │
   │ - User sees and interacts with                  │
   └──────────────────┬──────────────────────────────┘
                      │
                      │ Rendering
                      │
   ┌──────────────────▼──────────────────────────────┐
   │ DOM (Browser)                                   │
   │ - Actual HTML in contenteditable               │
   │ - <img src="..." data-htmlarea-file-uid="123"/> │
   └─────────────────────────────────────────────────┘

Why Separate Model and View?
-----------------------------

- **Data Integrity**: Model maintains clean data structure regardless of DOM quirks
- **Cross-Platform**: Same model can render differently on different platforms
- **Collaboration**: Multiple users can edit same model with conflict resolution
- **Undo/Redo**: Model changes tracked for history management
- **Validation**: Business rules enforced in model layer

Model Elements
==============

The extension provides two model elements for different use cases:

- **typo3image**: Block-level images wrapped in ``<figure>`` (with optional caption)
- **typo3imageInline**: True inline images that flow with text

Block Image Schema (typo3image)
-------------------------------

.. code-block:: javascript

   editor.model.schema.register('typo3image', {
       inheritAllFrom: '$blockObject',
       allowIn: ['$text', '$block'],
       allowAttributes: [
           'src', 'fileUid', 'fileTable',
           'alt', 'altOverride', 'title', 'titleOverride',
           'class', 'enableZoom', 'width', 'height',
           'htmlA', 'imageLinkHref', 'imageLinkTarget', 'imageLinkTitle',
           'imageLinkClass', 'imageLinkParams'
       ],
   });

Inline Image Schema (typo3imageInline)
--------------------------------------

.. versionadded:: 13.6

   True inline images with cursor positioning support.

.. code-block:: javascript

   editor.model.schema.register('typo3imageInline', {
       inheritAllFrom: '$inlineObject',
       allowIn: ['$block'],
       allowAttributes: [
           'src', 'fileUid', 'fileTable',
           'alt', 'altOverride', 'title', 'titleOverride',
           'class', 'enableZoom', 'width', 'height', 'noScale', 'quality',
           'imageLinkHref', 'imageLinkTarget', 'imageLinkTitle',
           'imageLinkClass', 'imageLinkParams'
       ],
   });

Key Differences
^^^^^^^^^^^^^^^

.. list-table::
   :header-rows: 1
   :widths: 30 35 35

   * - Feature
     - typo3image (Block)
     - typo3imageInline (Inline)
   * - Inheritance
     - ``$blockObject``
     - ``$inlineObject``
   * - Caption Support
     - ✅ Yes (typo3imageCaption child)
     - ❌ No
   * - Text Flow
     - Breaks paragraph (block-level)
     - Flows with text (inline)
   * - Cursor Position
     - Before/after figure
     - Before/after on same line
   * - Output HTML
     - ``<figure><img></figure>``
     - ``<img class="image-inline">``
   * - Style Classes
     - ``image-left``, ``image-right``, ``image-center``, ``image-block``
     - ``image-inline``

Usage Example
^^^^^^^^^^^^^

Text can flow around inline images: |inline-example|

.. |inline-example| image:: /Images/inline-image-example.png
   :height: 20px

In the editor, users can type text before and after inline images on the same line,
just like typing around any other inline element (bold text, links, etc.).

Toggle Command
^^^^^^^^^^^^^^

Users can convert between block and inline via the ``toggleImageType`` command:

.. code-block:: javascript

   // Toggle current image between block and inline
   editor.execute('toggleImageType');

   // Check current type
   const isInline = editor.commands.get('toggleImageType').value === 'inline';

**Block → Inline Conversion:**

- Caption is removed (inline images cannot have captions)
- Block style classes removed, ``image-inline`` added
- Image becomes inline in text flow

**Inline → Block Conversion:**

- Image wrapped in figure
- ``image-block`` class added (or previous alignment class)
- Image becomes block-level element

Schema Properties Explained
----------------------------

inheritAllFrom: '$blockObject'
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Inherits all properties from CKEditor's base ``$blockObject``:

- **Selectable**: Can be selected like any block element
- **Object**: Treated as atomic unit (not text content)
- **Focusable**: Can receive focus for editing
- **Non-Breaking**: Cannot be split by Enter key

allowIn: ['$text', '$block']
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

Defines where ``typo3image`` can exist:

- **$text**: Inside text content (inline-like behavior)
- **$block**: Inside block elements (paragraphs, divs, etc.)

**Result**: Images can be placed in any text flow or block context.

allowAttributes: [...]
^^^^^^^^^^^^^^^^^^^^^^

Lists all valid attributes for the model element. Attributes not listed are stripped.

Attribute Reference
===================

Core Attributes
---------------

src
^^^

.. code-block:: javascript

   type: String
   required: true

**Purpose**: Image source URL (absolute or relative)

**Examples**:

.. code-block:: javascript

   src: '/fileadmin/user_upload/image.jpg'
   src: '/fileadmin/_processed_/a/b/csm_image_123.jpg'
   src: 'https://example.com/external.jpg'

**Usage**:

.. code-block:: javascript

   const src = modelElement.getAttribute('src');
   writer.setAttribute('src', '/new/path.jpg', modelElement);

fileUid
^^^^^^^

.. code-block:: javascript

   type: Number
   required: true (for TYPO3 FAL integration)

**Purpose**: TYPO3 File Abstraction Layer file UID

**Range**: Positive integer matching ``sys_file.uid``

**Example**:

.. code-block:: javascript

   fileUid: 123

**Usage**:

.. code-block:: javascript

   const fileUid = modelElement.getAttribute('fileUid');
   writer.setAttribute('fileUid', 456, modelElement);

**Backend Integration**:

.. code-block:: javascript

   // Fetch file info from backend
   const fileUid = modelElement.getAttribute('fileUid');
   const fileInfo = await fetch(
       routeUrl + '&action=info&fileId=' + fileUid
   ).then(r => r.json());

fileTable
^^^^^^^^^

.. code-block:: javascript

   type: String
   default: 'sys_file'

**Purpose**: Database table name for file reference

**Valid Values**: ``'sys_file'`` (default), ``'sys_file_reference'`` (rarely used)

**Example**:

.. code-block:: javascript

   fileTable: 'sys_file'

**Usage**:

.. code-block:: javascript

   const table = modelElement.getAttribute('fileTable') || 'sys_file';

Metadata Attributes
-------------------

alt
^^^

.. code-block:: javascript

   type: String
   default: ''

**Purpose**: Alternative text for accessibility (WCAG compliance)

**Recommendations**:

- Describe image content concisely
- Required for accessibility
- Max ~125 characters for optimal screen reader experience

**Example**:

.. code-block:: javascript

   alt: 'Product photo showing red widget from front angle'

**Usage**:

.. code-block:: javascript

   writer.setAttribute('alt', 'New alt text', modelElement);

altOverride
^^^^^^^^^^^

.. code-block:: javascript

   type: Boolean
   default: false

**Purpose**: Flag indicating alt text was manually overridden by user

**Behavior**:

- ``false``: Use alt from FAL file metadata
- ``true``: Use custom alt text from ``alt`` attribute

**Example**:

.. code-block:: javascript

   altOverride: true  // Custom alt text takes precedence

**Usage Pattern**:

.. code-block:: javascript

   // In image dialog
   if (customAltCheckbox.checked) {
       writer.setAttribute('alt', customAltValue, modelElement);
       writer.setAttribute('altOverride', true, modelElement);
   } else {
       writer.removeAttribute('alt', modelElement);
       writer.removeAttribute('altOverride', modelElement);
   }

title
^^^^^

.. code-block:: javascript

   type: String
   default: ''

**Purpose**: Advisory title (tooltip text shown on hover)

**Recommendations**:

- Optional supplementary information
- Not a replacement for alt text
- Brief contextual information

**Example**:

.. code-block:: javascript

   title: 'Click to view full size'

**Usage**:

.. code-block:: javascript

   writer.setAttribute('title', 'Tooltip text', modelElement);

titleOverride
^^^^^^^^^^^^^

.. code-block:: javascript

   type: Boolean
   default: false

**Purpose**: Flag indicating title was manually overridden by user

**Behavior**:

- ``false``: Use title from FAL file metadata
- ``true``: Use custom title from ``title`` attribute

**Example**:

.. code-block:: javascript

   titleOverride: true

Visual Attributes
-----------------

class
^^^^^

.. code-block:: javascript

   type: String
   default: ''

**Purpose**: Space-separated CSS class names for styling

**Style Integration**: Modified by CKEditor style system via ``GeneralHtmlSupport``

**Examples**:

.. code-block:: javascript

   class: 'float-left img-responsive'
   class: 'img-thumbnail d-block mx-auto'

**Usage**:

.. code-block:: javascript

   // Manual class setting
   writer.setAttribute('class', 'my-class another-class', modelElement);

   // Style system automatically updates this attribute
   // when user selects a style from dropdown

**Style System Integration**:

.. code-block:: javascript

   // CKEditor style definition
   {
       name: 'Image Left',
       element: 'img',
       classes: ['float-left', 'mr-3']
   }

   // Results in:
   class: 'float-left mr-3'

width
^^^^^

.. code-block:: javascript

   type: String (pixels without unit)
   default: ''

**Purpose**: Image display width in pixels

**Format**: Numeric string without 'px' unit

**Examples**:

.. code-block:: javascript

   width: '800'
   width: '1200'

**Usage**:

.. code-block:: javascript

   writer.setAttribute('width', '800', modelElement);

**Aspect Ratio Preservation**:

.. code-block:: javascript

   // When width changes, height should be recalculated
   const newWidth = 800;
   const originalWidth = img.width;
   const originalHeight = img.height;
   const ratio = originalWidth / originalHeight;
   const newHeight = Math.ceil(newWidth / ratio);

   writer.setAttribute('width', String(newWidth), modelElement);
   writer.setAttribute('height', String(newHeight), modelElement);

height
^^^^^^

.. code-block:: javascript

   type: String (pixels without unit)
   default: ''

**Purpose**: Image display height in pixels

**Format**: Numeric string without 'px' unit

**Examples**:

.. code-block:: javascript

   height: '600'
   height: '900'

**Usage**:

.. code-block:: javascript

   writer.setAttribute('height', '600', modelElement);

enableZoom
^^^^^^^^^^

.. code-block:: javascript

   type: Boolean
   default: false

**Purpose**: Enable zoom/click-to-enlarge functionality (TYPO3-specific feature)

**Behavior**:

- ``true``: Image becomes clickable, opens larger version
- ``false``: Image is static, no click interaction

**Example**:

.. code-block:: javascript

   enableZoom: true

**Frontend Rendering**:

.. code-block:: html

   <!-- When enableZoom is true -->
   <a href="large-image.jpg" data-lightbox="gallery">
       <img src="thumb.jpg" data-htmlarea-zoom="true" />
   </a>

**Usage**:

.. code-block:: javascript

   writer.setAttribute('enableZoom', true, modelElement);

Link Attributes
---------------

htmlA
^^^^^

.. code-block:: javascript

   type: String
   default: undefined

**Purpose**: Complete HTML anchor tag wrapping the image (legacy attribute)

**Format**: Full HTML string

**Example**:

.. code-block:: javascript

   htmlA: '<a href="/page/123" target="_blank" title="Link title">...</a>'

.. note::
   This is a legacy attribute. Modern approach uses separate link attributes.

linkHref
^^^^^^^^

.. code-block:: javascript

   type: String
   default: undefined

**Purpose**: Link URL when image is wrapped in anchor tag

**Examples**:

.. code-block:: javascript

   linkHref: '/page/detail'
   linkHref: 'https://example.com'
   linkHref: 'mailto:info@example.com'

**Usage**:

.. code-block:: javascript

   writer.setAttribute('linkHref', '/page/123', modelElement);

linkTarget
^^^^^^^^^^

.. code-block:: javascript

   type: String
   default: undefined

**Purpose**: Link target attribute (_blank, _self, _parent, _top)

**Valid Values**: ``'_blank'``, ``'_self'``, ``'_parent'``, ``'_top'``, or named frame

**Example**:

.. code-block:: javascript

   linkTarget: '_blank'

**Usage**:

.. code-block:: javascript

   writer.setAttribute('linkTarget', '_blank', modelElement);

linkTitle
^^^^^^^^^

.. code-block:: javascript

   type: String
   default: undefined

**Purpose**: Title attribute for the wrapping anchor tag

**Example**:

.. code-block:: javascript

   linkTitle: 'Click to view product details'

**Usage**:

.. code-block:: javascript

   writer.setAttribute('linkTitle', 'Link description', modelElement);

linkClass
^^^^^^^^^

.. versionadded:: 13.5.0

.. code-block:: javascript

   type: String
   default: undefined

**Purpose**: CSS class for the wrapping anchor tag

**Example**:

.. code-block:: javascript

   linkClass: 'external-link btn-primary'

**Usage**:

.. code-block:: javascript

   writer.setAttribute('linkClass', 'my-link-class', modelElement);

linkParams
^^^^^^^^^^

.. versionadded:: 13.5.0

.. code-block:: javascript

   type: String
   default: undefined

**Purpose**: Additional URL parameters for the link (TypoLink additionalParams)

**Format**: Query string starting with ``&`` (e.g., ``&L=1&type=123``)

**Example**:

.. code-block:: javascript

   linkParams: '&L=1&type=123'

**Usage**:

.. code-block:: javascript

   writer.setAttribute('linkParams', '&cHash=abc123', modelElement);

**TypoLink Integration**:

This corresponds to the fifth parameter in TYPO3's TypoLink format::

   url target class "title" additionalParams
   ↓                         ↓
   linkHref                 linkParams

Working with Model Elements
============================

Creating Model Elements
-----------------------

.. code-block:: javascript

   editor.model.change(writer => {
       const typo3image = writer.createElement('typo3image', {
           src: '/fileadmin/image.jpg',
           fileUid: 123,
           fileTable: 'sys_file',
           width: '800',
           height: '600',
           alt: 'Description',
           class: 'img-fluid'
       });

       // Insert at current selection
       const insertPosition = editor.model.document.selection.getFirstPosition();
       editor.model.insertContent(typo3image, insertPosition);
   });

Updating Attributes
-------------------

.. code-block:: javascript

   editor.model.change(writer => {
       const selectedElement = editor.model.document.selection.getSelectedElement();

       if (selectedElement && selectedElement.name === 'typo3image') {
           // Update single attribute
           writer.setAttribute('width', '1200', selectedElement);

           // Update multiple attributes
           writer.setAttributes({
               width: '1200',
               height: '800',
               class: 'img-large'
           }, selectedElement);
       }
   });

Reading Attributes
------------------

.. code-block:: javascript

   const selectedElement = editor.model.document.selection.getSelectedElement();

   if (selectedElement && selectedElement.name === 'typo3image') {
       // Read single attribute
       const src = selectedElement.getAttribute('src');
       const fileUid = selectedElement.getAttribute('fileUid');

       // Read with default fallback
       const alt = selectedElement.getAttribute('alt') || '';
       const width = selectedElement.getAttribute('width') || '0';

       // Check if attribute exists
       const hasClass = selectedElement.hasAttribute('class');

       // Get all attributes
       const allAttrs = Array.from(selectedElement.getAttributes());
       console.log(allAttrs);  // [['src', '...'], ['fileUid', 123], ...]
   }

Removing Attributes
-------------------

.. code-block:: javascript

   editor.model.change(writer => {
       const selectedElement = editor.model.document.selection.getSelectedElement();

       if (selectedElement && selectedElement.name === 'typo3image') {
           // Remove single attribute
           writer.removeAttribute('class', selectedElement);

           // Remove multiple attributes
           writer.removeAttribute('title', selectedElement);
           writer.removeAttribute('titleOverride', selectedElement);
       }
   });

Model Selection
===============

Selecting Elements
------------------

.. code-block:: javascript

   // Select element programmatically
   editor.model.change(writer => {
       const element = /* get element reference */;
       writer.setSelection(element, 'on');  // 'on' = select element itself
   });

Getting Selected Element
-------------------------

.. code-block:: javascript

   const selection = editor.model.document.selection;
   const selectedElement = selection.getSelectedElement();

   if (selectedElement && selectedElement.name === 'typo3image') {
       // Image is selected
       console.log('Selected typo3image element');
   }

Iterating Selection Range
--------------------------

.. code-block:: javascript

   const selection = editor.model.document.selection;
   const range = selection.getFirstRange();

   for (const item of range.getItems()) {
       if (item.is('element', 'typo3image')) {
           console.log('Found typo3image:', item.getAttribute('src'));
       }
   }

Model Traversal
===============

Finding Parent Elements
-----------------------

.. code-block:: javascript

   const element = /* typo3image element */;
   const parent = element.parent;

   console.log(parent.name);  // e.g., 'paragraph', '$root'

Finding Previous/Next Siblings
-------------------------------

.. code-block:: javascript

   const element = /* typo3image element */;
   const previousSibling = element.previousSibling;
   const nextSibling = element.nextSibling;

Walking the Model Tree
----------------------

.. code-block:: javascript

   function findAllImages(root) {
       const images = [];
       const walker = editor.model.createRangeIn(root).getWalker();

       for (const {item} of walker) {
           if (item.is('element', 'typo3image')) {
               images.push(item);
           }
       }

       return images;
   }

   // Find all images in document
   const allImages = findAllImages(editor.model.document.getRoot());

Attribute Validation
====================

Allowed Attributes Enforcement
-------------------------------

CKEditor automatically strips attributes not in ``allowAttributes`` list:

.. code-block:: javascript

   // This attribute will be stripped
   writer.setAttribute('invalidAttr', 'value', modelElement);

   // Only these attributes are preserved
   allowAttributes: [
       'src', 'fileUid', 'fileTable',
       'alt', 'altOverride', 'title', 'titleOverride',
       'class', 'enableZoom', 'width', 'height',
       'htmlA', 'linkHref', 'linkTarget', 'linkTitle',
       'linkClass', 'linkParams'
   ]

Custom Validation
-----------------

.. code-block:: javascript

   editor.model.schema.addAttributeCheck((context, attributeName) => {
       // Only allow width/height with valid numeric values
       if (attributeName === 'width' || attributeName === 'height') {
           if (context.endsWith('typo3image')) {
               const value = context.getAttribute(attributeName);
               return /^\d+$/.test(value);  // Must be numeric
           }
       }
       return true;
   });

Model Change Listeners
======================

Listening to Attribute Changes
-------------------------------

.. code-block:: javascript

   editor.model.document.on('change:data', () => {
       const changes = editor.model.document.differ.getChanges();

       for (const change of changes) {
           if (change.type === 'attribute' && change.attributeKey === 'class') {
               console.log('Class changed:', {
                   element: change.range.start.parent.name,
                   oldValue: change.attributeOldValue,
                   newValue: change.attributeNewValue
               });
           }
       }
   });

Listening to Element Insertion
-------------------------------

.. code-block:: javascript

   editor.model.document.on('change:data', () => {
       const changes = editor.model.document.differ.getChanges();

       for (const change of changes) {
           if (change.type === 'insert' && change.name === 'typo3image') {
               console.log('typo3image inserted:', change.position.path);
           }
       }
   });

Advanced Patterns
=================

Cloning Elements
----------------

.. code-block:: javascript

   editor.model.change(writer => {
       const original = /* get typo3image element */;

       // Clone with all attributes
       const clone = writer.cloneElement(original);

       // Insert clone
       const insertPosition = /* target position */;
       writer.insert(clone, insertPosition);
   });

Batch Attribute Updates
-----------------------

.. code-block:: javascript

   editor.model.change(writer => {
       const images = /* array of typo3image elements */;

       // Apply same class to all images
       images.forEach(img => {
           const currentClass = img.getAttribute('class') || '';
           const newClass = currentClass + ' batch-processed';
           writer.setAttribute('class', newClass.trim(), img);
       });
   });

Conditional Attribute Setting
------------------------------

.. code-block:: javascript

   editor.model.change(writer => {
       const element = /* typo3image element */;

       // Only set width if not already set
       if (!element.hasAttribute('width')) {
           writer.setAttribute('width', '800', element);
       }

       // Update alt only if override is enabled
       if (element.getAttribute('altOverride')) {
           writer.setAttribute('alt', customAltText, element);
       }
   });

Debugging Model Elements
========================

Inspect Element in Console
---------------------------

.. code-block:: javascript

   // Get selected element
   const element = editor.model.document.selection.getSelectedElement();

   // Log all attributes
   console.log('Element:', element.name);
   console.log('Attributes:', Array.from(element.getAttributes()));

   // Log specific attributes
   console.log('src:', element.getAttribute('src'));
   console.log('fileUid:', element.getAttribute('fileUid'));
   console.log('class:', element.getAttribute('class'));

Monitor Model Changes
---------------------

.. code-block:: javascript

   editor.model.document.on('change', (evt, batch) => {
       console.log('Model changed, batch type:', batch.type);
       console.log('Is undoable:', batch.isUndoable);

       const changes = editor.model.document.differ.getChanges();
       console.log('Changes:', changes);
   });

Visualize Model Structure
--------------------------

.. code-block:: javascript

   function logModelTree(element, indent = 0) {
       const prefix = ' '.repeat(indent);
       if (element.is('$text')) {
           console.log(prefix + 'TEXT:', element.data);
       } else {
           console.log(prefix + element.name);
           for (const child of element.getChildren()) {
               logModelTree(child, indent + 2);
           }
       }
   }

   // Log entire document structure
   logModelTree(editor.model.document.getRoot());

Related Documentation
=====================

- :ref:`ckeditor-plugin-development-guide`
- :ref:`ckeditor-style-integration`
- :ref:`ckeditor-conversions`
- :ref:`architecture-overview`
