.. include:: /Includes.rst.txt

.. _ckeditor-style-integration:

=============================
CKEditor Style Integration
=============================

Complete guide to integrating the typo3image plugin with CKEditor's style system (StyleUtils and GeneralHtmlSupport).

Overview
========

.. versionadded:: 13.0.0
   Integration with ``GeneralHtmlSupport`` is now required for style functionality.
   Previous versions only required ``StyleUtils``, which caused the style dropdown
   to be disabled for images.

**Critical Dependencies**:

.. code-block:: javascript

   static get requires() {
       return ['StyleUtils', 'GeneralHtmlSupport'];
   }

.. warning::
   Both ``StyleUtils`` and ``GeneralHtmlSupport`` are **mandatory** for style functionality. Missing either plugin causes style drop-down to be disabled for images.

The Style System Problem
=========================

Before v13.0.0 (Broken)
-----------------------

.. code-block:: javascript

   // Missing GeneralHtmlSupport dependency
   static get requires() {
       return ['StyleUtils'];  // Incomplete!
   }

**Issue**: Style drop-down disabled when image selected

**Symptoms**:

- Styles grayed out when typo3image selected
- Class changes not applied to images
- No visual feedback when applying styles

After v13.0.0 (Fixed)
---------------------

.. code-block:: javascript

   // Both dependencies required
   static get requires() {
       return ['StyleUtils', 'GeneralHtmlSupport'];
   }

**Result**: Full style system integration working correctly

Style System Architecture
=========================

Three-Layer Integration
-----------------------

::

   ┌─────────────────────────────────────────┐
   │ StyleUtils Plugin                       │
   │ - Manages style definitions             │
   │ - Provides event system                 │
   │ - Determines style applicability        │
   └───────────┬─────────────────────────────┘
               │
               │ Events
               │
   ┌───────────▼─────────────────────────────┐
   │ Typo3Image Plugin                       │
   │ - Listens to StyleUtils events         │
   │ - Reports typo3image availability       │
   │ - Returns correct model elements        │
   └───────────┬─────────────────────────────┘
               │
               │ Operations
               │
   ┌───────────▼─────────────────────────────┐
   │ GeneralHtmlSupport Plugin               │
   │ - Applies class changes to model        │
   │ - Manages HTML attribute manipulation   │
   │ - Ensures class sync with view          │
   └─────────────────────────────────────────┘

StyleUtils Event System
=======================

Event: isStyleEnabledForBlock
------------------------------

**Purpose**: Determines if a style can be applied to the selected element

**When Fired**: User selects element, style drop-down needs update

**Default Behavior**: Only enable styles for elements matching style definition

**typo3image Override**:

.. code-block:: javascript

   this.listenTo(styleUtils, 'isStyleEnabledForBlock', (event, [style, element]) => {
       if (style.element === 'img') {
           for (const item of editor.model.document.selection.getFirstRange().getItems()) {
               if (item.name === 'typo3image') {
                   event.return = true;  // Enable img styles for typo3image
               }
           }
       }
   });

**Logic Breakdown**:

1. **Check Style Element**: ``if (style.element === 'img')``

   - Only process styles defined for ``<img>`` elements
   - Ignore styles for other elements (p, h1, etc.)

2. **Iterate Selection**: ``for (const item of ...getFirstRange().getItems())``

   - Get all items in current selection range
   - Check if any item is a typo3image

3. **Enable Style**: ``event.return = true``

   - Tell StyleUtils that img styles ARE applicable to typo3image
   - Without this, style drop-down would be disabled

.. note::
   CKEditor doesn't natively know that ``typo3image`` (model element) corresponds to ``<img>`` (view element). This listener bridges that gap.

Event: isStyleActiveForBlock
-----------------------------

**Purpose**: Checks if a style is currently active (applied) on selected element

**When Fired**: User selects element, style drop-down shows active styles

**Default Behavior**: Check if element has required classes

**typo3image Implementation**:

.. code-block:: javascript

   this.listenTo(styleUtils, 'isStyleActiveForBlock', (event, [style, element]) => {
       if (style.element === 'img') {
           for (const item of editor.model.document.selection.getFirstRange().getItems()) {
               if (item.name === 'typo3image') {
                   const classAttribute = item.getAttribute('class');
                   if (classAttribute && typeof classAttribute === 'string') {
                       const classlist = classAttribute.split(' ');
                       // Check if ALL style classes are present
                       if (style.classes.filter(value => !classlist.includes(value)).length === 0) {
                           event.return = true;  // Style is active
                       }
                   }
               }
           }
       }
   });

**Logic Breakdown**:

1. **Check Style Element**: Only process img styles

2. **Find typo3image**: Iterate selection to find typo3image element

3. **Get Classes**: ``const classAttribute = item.getAttribute('class')``

   - Read current class attribute from model element
   - Returns space-separated string (e.g., "float-left img-responsive")

4. **Parse Classes**: ``const classlist = classAttribute.split(' ')``

   - Convert string to array: ``["float-left", "img-responsive"]``

5. **Check Match**: ``style.classes.filter(value => !classlist.includes(value)).length === 0``

   - Check if ALL style classes are present in element
   - Example: Style has ``['float-left', 'mr-3']``, check both exist
   - If any missing, style is NOT active

**Example**:

.. code-block:: javascript

   // Style definition
   {
       name: 'Image Left',
       element: 'img',
       classes: ['float-left', 'mr-3']
   }

   // Element class attribute
   class: 'float-left mr-3 img-responsive'

   // Check: Are 'float-left' AND 'mr-3' both present?
   ['float-left', 'mr-3'].filter(cls =>
       !['float-left', 'mr-3', 'img-responsive'].includes(cls)
   ).length === 0  // true → style is active

Event: getAffectedBlocks
-------------------------

**Purpose**: Returns which model elements should be affected by style operation

**When Fired**: User applies/removes a style

**Default Behavior**: Return block elements from selection

**typo3image Implementation**:

.. code-block:: javascript

   this.listenTo(styleUtils, 'getAffectedBlocks', (event, [style, element]) => {
       if (style.element === 'img') {
           for (const item of editor.model.document.selection.getFirstRange().getItems()) {
               if (item.name === 'typo3image') {
                   event.return = [item];  // Return typo3image element
                   break;
               }
           }
       }
   });

**Logic Breakdown**:

1. **Check Style Element**: Only process img styles

2. **Find typo3image**: Iterate to find typo3image in selection

3. **Return Element**: ``event.return = [item]``

   - Return array with single typo3image element
   - StyleUtils will apply style changes to this element

4. **Break Loop**: Once found, stop searching

.. note::
   StyleUtils can affect multiple blocks (e.g., multiple paragraphs selected). For images, typically only one image is selected.

GeneralHtmlSupport Integration
===============================

What is GeneralHtmlSupport?
----------------------------

**Purpose**: Manages HTML attributes that aren't core CKEditor features

**Capabilities**:

- Add/remove classes via style system
- Manage data-* attributes
- Handle custom HTML attributes
- Sync model attributes with view

Decoration Pattern
------------------

.. code-block:: javascript

   const ghs = editor.plugins.get('GeneralHtmlSupport');
   ghs.decorate('addModelHtmlClass');
   ghs.decorate('removeModelHtmlClass');

**What** ``decorate()`` **Does**:

- Makes method observable via event system
- Allows plugins to intercept and customize behavior
- Enables event listeners to modify operations

Event: addModelHtmlClass
-------------------------

**Purpose**: Add CSS class to model element

**When Fired**: Style system applies a style (adds classes)

**typo3image Implementation**:

.. code-block:: javascript

   this.listenTo(ghs, 'addModelHtmlClass', (event, [viewElement, className, selectable]) => {
       if (selectable && selectable.name === 'typo3image') {
           editor.model.change(writer => {
               writer.setAttribute('class', className.join(' '), selectable);
           });
       }
   });

**Parameters**:

- ``viewElement``: View layer element (not used for typo3image)
- ``className``: Array of class names to add
- ``selectable``: Model element to modify

**Logic**:

1. **Check Element**: ``if (selectable && selectable.name === 'typo3image')``

   - Only process typo3image elements

2. **Join Classes**: ``className.join(' ')``

   - Convert array to space-separated string
   - Example: ``['float-left', 'mr-3']`` → ``'float-left mr-3'``

3. **Update Model**: ``writer.setAttribute('class', ..., selectable)``

   - Apply classes to model element
   - Triggers view update automatically

**Example Flow**::

   User clicks "Image Left" style
       ↓
   StyleUtils determines style applies to typo3image
       ↓
   GeneralHtmlSupport.addModelHtmlClass fired
       ↓
   Event handler: className = ['float-left', 'mr-3']
       ↓
   Model updated: class = 'float-left mr-3'
       ↓
   View automatically updates: <img class="float-left mr-3" ... />

Event: removeModelHtmlClass
----------------------------

**Purpose**: Remove CSS class from model element

**When Fired**: Style system removes a style (removes classes)

**typo3image Implementation**:

.. code-block:: javascript

   this.listenTo(ghs, 'removeModelHtmlClass', (event, [viewElement, className, selectable]) => {
       if (selectable && selectable.name === 'typo3image') {
           editor.model.change(writer => {
               writer.removeAttribute('class', selectable);
           });
       }
   });

**Logic**:

1. **Check Element**: Only process typo3image

2. **Remove Attribute**: ``writer.removeAttribute('class', selectable)``

   - Completely removes class attribute
   - Note: Doesn't selectively remove classes, removes all

.. note::
   **Limitation**: Current implementation removes ALL classes when any style is removed. Could be enhanced to only remove specific classes.

**Enhancement Pattern**:

.. code-block:: javascript

   // Better implementation: remove only specific classes
   this.listenTo(ghs, 'removeModelHtmlClass', (event, [viewElement, className, selectable]) => {
       if (selectable && selectable.name === 'typo3image') {
           editor.model.change(writer => {
               const currentClass = selectable.getAttribute('class') || '';
               const currentClasses = currentClass.split(' ').filter(Boolean);
               const classesToRemove = className;

               // Keep classes not being removed
               const newClasses = currentClasses.filter(
                   cls => !classesToRemove.includes(cls)
               );

               if (newClasses.length > 0) {
                   writer.setAttribute('class', newClasses.join(' '), selectable);
               } else {
                   writer.removeAttribute('class', selectable);
               }
           });
       }
   });

Complete Integration Example
=============================

Style Configuration (YAML)
---------------------------

.. code-block:: yaml

   # Configuration/RTE/Default.yaml
   editor:
     config:
       style:
         definitions:
           - name: 'Image Left'
             element: 'img'
             classes: ['float-left', 'mr-3']
           - name: 'Image Right'
             element: 'img'
             classes: ['float-right', 'ml-3']
           - name: 'Image Center'
             element: 'img'
             classes: ['d-block', 'mx-auto']
           - name: 'Full Width'
             element: 'img'
             classes: ['w-100']

Plugin Integration (JavaScript)
--------------------------------

.. code-block:: javascript

   export default class Typo3Image extends Core.Plugin {
       static get requires() {
           return ['StyleUtils', 'GeneralHtmlSupport'];
       }

       init() {
           const editor = this.editor;
           const styleUtils = editor.plugins.get('StyleUtils');
           const ghs = editor.plugins.get('GeneralHtmlSupport');

           // Enable img styles for typo3image
           this.listenTo(styleUtils, 'isStyleEnabledForBlock', (event, [style, element]) => {
               if (style.element === 'img') {
                   for (const item of editor.model.document.selection.getFirstRange().getItems()) {
                       if (item.name === 'typo3image') {
                           event.return = true;
                       }
                   }
               }
           });

           // Check if style is active
           this.listenTo(styleUtils, 'isStyleActiveForBlock', (event, [style, element]) => {
               if (style.element === 'img') {
                   for (const item of editor.model.document.selection.getFirstRange().getItems()) {
                       if (item.name === 'typo3image') {
                           const classAttribute = item.getAttribute('class');
                           if (classAttribute && typeof classAttribute === 'string') {
                               const classlist = classAttribute.split(' ');
                               if (style.classes.filter(value => !classlist.includes(value)).length === 0) {
                                   event.return = true;
                               }
                           }
                       }
                   }
               }
           });

           // Return affected elements
           this.listenTo(styleUtils, 'getAffectedBlocks', (event, [style, element]) => {
               if (style.element === 'img') {
                   for (const item of editor.model.document.selection.getFirstRange().getItems()) {
                       if (item.name === 'typo3image') {
                           event.return = [item];
                           break;
                       }
                   }
               }
           });

           // Apply classes
           ghs.decorate('addModelHtmlClass');
           this.listenTo(ghs, 'addModelHtmlClass', (event, [viewElement, className, selectable]) => {
               if (selectable && selectable.name === 'typo3image') {
                   editor.model.change(writer => {
                       writer.setAttribute('class', className.join(' '), selectable);
                   });
               }
           });

           // Remove classes
           ghs.decorate('removeModelHtmlClass');
           this.listenTo(ghs, 'removeModelHtmlClass', (event, [viewElement, className, selectable]) => {
               if (selectable && selectable.name === 'typo3image') {
                   editor.model.change(writer => {
                       writer.removeAttribute('class', selectable);
                   });
               }
           });
       }
   }

Troubleshooting Style Issues
=============================

Issue: Style Drop-down Disabled for Images
-------------------------------------------

**Symptoms**:

- Select image → style drop-down grayed out
- No styles available when image selected

**Causes**:

1. Missing ``GeneralHtmlSupport`` dependency
2. Missing ``StyleUtils`` dependency
3. Event listeners not registered
4. Style definitions don't target 'img' element

**Solutions**:

Verify Dependencies
^^^^^^^^^^^^^^^^^^^

.. code-block:: javascript

   static get requires() {
       return ['StyleUtils', 'GeneralHtmlSupport'];  // Both required!
   }

Verify Style Definitions
^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: yaml

   style:
     definitions:
       - name: 'My Style'
         element: 'img'  # Must be 'img', not 'image'
         classes: ['my-class']

Check Event Listeners
^^^^^^^^^^^^^^^^^^^^^

.. code-block:: javascript

   // Debug in browser console
   const styleUtils = editor.plugins.get('StyleUtils');
   console.log(styleUtils.listenerCount('isStyleEnabledForBlock'));
   // Should be > 0

Issue: Style Changes Not Applied
---------------------------------

**Symptoms**:

- Style selected from drop-down
- No visual change to image
- Class attribute not updated

**Causes**:

1. GeneralHtmlSupport event listeners not registered
2. Model-to-view conversion missing class attribute
3. CSS classes not defined in stylesheet

**Solutions**:

Verify GHS Listeners
^^^^^^^^^^^^^^^^^^^^

.. code-block:: javascript

   const ghs = editor.plugins.get('GeneralHtmlSupport');
   console.log(ghs.listenerCount('addModelHtmlClass'));
   // Should be > 0

Check Class Attribute Conversion
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code-block:: javascript

   editor.conversion.for('downcast').attributeToAttribute({
       model: { name: 'typo3image', key: 'class' },
       view: 'class'
   });

Verify CSS Loaded
^^^^^^^^^^^^^^^^^

.. code-block:: css

   /* In your stylesheet */
   .float-left { float: left; margin-right: 1rem; }
   .float-right { float: right; margin-left: 1rem; }

Issue: Styles Not Shown as Active
----------------------------------

**Symptoms**:

- Image has correct classes
- Style not checked/highlighted in drop-down
- Cannot tell which style is applied

**Cause**: ``isStyleActiveForBlock`` listener not working correctly

**Solution**:

Debug Class Matching
^^^^^^^^^^^^^^^^^^^^

.. code-block:: javascript

   // In isStyleActiveForBlock listener
   console.log('Element classes:', item.getAttribute('class'));
   console.log('Style classes:', style.classes);

   const classlist = item.getAttribute('class').split(' ');
   const missing = style.classes.filter(cls => !classlist.includes(cls));
   console.log('Missing classes:', missing);

Advanced Style Patterns
========================

Multiple Class Styles
----------------------

.. code-block:: yaml

   # Complex styles with multiple classes
   style:
     definitions:
       - name: 'Responsive Image Card'
         element: 'img'
         classes: ['img-fluid', 'rounded', 'shadow-sm', 'd-block']

**Application**:

.. code-block:: javascript

   // Results in model:
   class: 'img-fluid rounded shadow-sm d-block'

   // View output:
   <img class="img-fluid rounded shadow-sm d-block" src="..." />

Conditional Style Availability
-------------------------------

.. code-block:: javascript

   // Only enable certain styles for specific users
   this.listenTo(styleUtils, 'isStyleEnabledForBlock', (event, [style, element]) => {
       if (style.element === 'img' && style.name === 'Admin Only Style') {
           // Check user permission
           if (!userHasAdminPermission()) {
               event.return = false;  // Disable this style
               event.stop();  // Prevent further processing
               return;
           }
       }

       // Default behavior for other styles
       if (style.element === 'img') {
           for (const item of editor.model.document.selection.getFirstRange().getItems()) {
               if (item.name === 'typo3image') {
                   event.return = true;
               }
           }
       }
   });

Style Groups
------------

.. code-block:: yaml

   # Organize styles into groups
   style:
     definitions:
       - name: 'Left Align'
         element: 'img'
         classes: ['float-left']
       - name: 'Right Align'
         element: 'img'
         classes: ['float-right']
       - name: 'Center Align'
         element: 'img'
         classes: ['mx-auto', 'd-block']

     groupDefinitions:
       - name: 'Image Alignment'
         styles: ['Left Align', 'Right Align', 'Center Align']

Performance Considerations
==========================

Event Listener Efficiency
--------------------------

.. code-block:: javascript

   // Inefficient: Iterates entire range multiple times
   this.listenTo(styleUtils, 'isStyleEnabledForBlock', (event, [style]) => {
       if (style.element === 'img') {
           for (const item of editor.model.document.selection.getFirstRange().getItems()) {
               if (item.name === 'typo3image') {
                   event.return = true;
               }
           }
       }
   });

   // Efficient: Cache selection check
   const isTypo3ImageSelected = () => {
       const selection = editor.model.document.selection;
       const element = selection.getSelectedElement();
       return element && element.name === 'typo3image';
   };

   this.listenTo(styleUtils, 'isStyleEnabledForBlock', (event, [style]) => {
       if (style.element === 'img' && isTypo3ImageSelected()) {
           event.return = true;
       }
   });

Related Documentation
=====================

- :ref:`ckeditor-plugin-development-guide`
- :ref:`ckeditor-model-element`
- :ref:`ckeditor-conversions`
- :ref:`configuration`
- :ref:`troubleshooting-index`
