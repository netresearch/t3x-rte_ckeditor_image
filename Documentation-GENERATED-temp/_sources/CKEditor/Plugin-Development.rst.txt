.. _ckeditor-plugin-development-guide:

==============================
CKEditor Plugin Development
==============================

Complete guide to the Typo3Image CKEditor 5 plugin architecture and development patterns.

Plugin Overview
===============

**File**: ``Resources/Public/JavaScript/Plugins/typo3image.js``

**Plugin Class**: ``Typo3Image extends Core.Plugin``

**Required Dependencies**:

.. code-block:: javascript

   static get requires() {
       return ['StyleUtils', 'GeneralHtmlSupport'];
   }

.. warning::
   Both ``StyleUtils`` and ``GeneralHtmlSupport`` are **mandatory**. Missing them causes style functionality to fail.

Plugin Structure
================

.. code-block:: javascript

   export default class Typo3Image extends Core.Plugin {
       static pluginName = 'Typo3Image';

       static get requires() {
           return ['StyleUtils', 'GeneralHtmlSupport'];
       }

       init() {
           // Plugin initialization
           // - Define schema
           // - Register conversions
           // - Add UI components
           // - Register event listeners
       }
   }

Custom Model Element: typo3image
=================================

Schema Definition
-----------------

.. code-block:: javascript

   editor.model.schema.register('typo3image', {
       inheritAllFrom: '$blockObject',
       allowIn: ['$text', '$block'],
       allowAttributes: [
           'src', 'fileUid', 'fileTable',
           'alt', 'altOverride', 'title', 'titleOverride',
           'class', 'enableZoom', 'width', 'height',
           'htmlA', 'linkHref', 'linkTarget', 'linkTitle'
       ],
   });

Attribute Descriptions
----------------------

================  ========  ===========================================
Attribute         Type      Description
================  ========  ===========================================
src               string    Image source URL
fileUid           number    TYPO3 FAL file UID
fileTable         string    Database table (default: 'sys_file')
alt               string    Alternative text
altOverride       boolean   Alt text override flag
title             string    Advisory title
titleOverride     boolean   Title override flag
class             string    CSS classes (space-separated)
enableZoom        boolean   Zoom/clickenlarge functionality
width             string    Image width
height            string    Image height
htmlA             string    Link wrapper HTML
linkHref          string    Link URL
linkTarget        string    Link target
linkTitle         string    Link title
================  ========  ===========================================

Conversion System
=================

Upcast: HTML → Model
--------------------

Converts ``<img>`` elements with FAL attributes to ``typo3image`` model elements:

.. code-block:: javascript

   editor.conversion.for('upcast').elementToElement({
       view: {
           name: 'img',
           attributes: ['data-htmlarea-file-uid', 'src']
       },
       model: (viewElement, { writer }) => {
           return writer.createElement('typo3image', {
               fileUid: viewElement.getAttribute('data-htmlarea-file-uid'),
               fileTable: viewElement.getAttribute('data-htmlarea-file-table') || 'sys_file',
               src: viewElement.getAttribute('src'),
               width: viewElement.getAttribute('width') || '',
               height: viewElement.getAttribute('height') || '',
               class: viewElement.getAttribute('class') || '',
               alt: viewElement.getAttribute('alt') || '',
               altOverride: viewElement.getAttribute('data-alt-override') || false,
               title: viewElement.getAttribute('title') || '',
               titleOverride: viewElement.getAttribute('data-title-override') || false,
               enableZoom: viewElement.getAttribute('data-htmlarea-zoom') || false,
           });
       }
   });

Downcast: Model → HTML
-----------------------

Converts ``typo3image`` model elements to ``<img>`` HTML:

.. code-block:: javascript

   editor.conversion.for('downcast').elementToElement({
       model: {
           name: 'typo3image',
           attributes: ['fileUid', 'fileTable', 'src']
       },
       view: (modelElement, { writer }) => {
           const attributes = {
               'src': modelElement.getAttribute('src'),
               'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
               'data-htmlarea-file-table': modelElement.getAttribute('fileTable'),
               'width': modelElement.getAttribute('width'),
               'height': modelElement.getAttribute('height'),
               'class': modelElement.getAttribute('class') || '',
               'title': modelElement.getAttribute('title') || '',
               'alt': modelElement.getAttribute('alt') || '',
           };

           if (modelElement.getAttribute('titleOverride')) {
               attributes['data-title-override'] = true;
           }
           if (modelElement.getAttribute('altOverride')) {
               attributes['data-alt-override'] = true;
           }
           if (modelElement.getAttribute('enableZoom')) {
               attributes['data-htmlarea-zoom'] = true;
           }

           return writer.createEmptyElement('img', attributes);
       },
   });

Class Attribute Converter
--------------------------

Makes class changes immediately visible in the editor:

.. code-block:: javascript

   editor.conversion.for('downcast').attributeToAttribute({
       model: { name: 'typo3image', key: 'class' },
       view: 'class'
   });

UI Components
=============

Insert Image Button
-------------------

Registered in ``editor.ui.componentFactory``:

.. code-block:: javascript

   editor.ui.componentFactory.add('insertimage', () => {
       const button = new UI.ButtonView();

       button.set({
           label: 'Insert image',
           icon: '<svg>...</svg>',
           tooltip: true,
           withText: false,
       });

       button.on('execute', () => {
           const selectedElement = editor.model.document.selection.getSelectedElement();

           if (selectedElement && selectedElement.name === 'typo3image') {
               // Edit existing image
               edit(selectedElement, editor, attributes);
           } else {
               // Insert new image
               selectImage(editor).then(selectedImage => {
                   edit(selectedImage, editor, {});
               });
           }
       });

       return button;
   });

Image Selection Flow
====================

selectImage() Function
----------------------

Opens TYPO3 Modal with file browser:

.. code-block:: javascript

   function selectImage(editor) {
       const deferred = $.Deferred();
       const bparams = ['', '', '', ''];
       const contentUrl = editor.config.get('style').typo3image.routeUrl
           + '&contentsLanguage=en&editorId=123&bparams=' + bparams.join('|');

       const modal = Modal.advanced({
           type: Modal.types.iframe,
           title: 'Select Image',
           content: contentUrl,
           size: Modal.sizes.large,
           callback: function (currentModal) {
               $(currentModal).find('iframe').on('load', function (e) {
                   $(this).contents().on('click', '[data-filelist-element]', function (e) {
                       if ($(this).data('filelist-type') !== 'file') {
                           return;
                       }

                       const selectedItem = {
                           uid: $(this).data('filelist-uid'),
                           table: 'sys_file',
                       };
                       currentModal.hideModal();
                       deferred.resolve(selectedItem);
                   });
               });
           }
       });

       return deferred;
   }

Image Properties Dialog
========================

getImageDialog() Function
--------------------------

Creates image properties form:

.. code-block:: javascript

   function getImageDialog(editor, img, attributes) {
       const d = {};
       const fields = [
           {
               width: { label: 'Width', type: 'number' },
               height: { label: 'Height', type: 'number' }
           },
           {
               title: { label: 'Advisory Title', type: 'text' },
               alt: { label: 'Alternative Text', type: 'text' }
           }
       ];

       // Create form elements
       d.$el = $('<div class="rteckeditorimage">');

       // ... form generation code ...

       // Aspect ratio preservation for width/height
       $el.on('input', function () {
           const ratio = img.width / img.height;
           const newHeight = Math.ceil(newWidth / ratio);
           $opposite.val(newHeight);
       });

       // Override checkboxes for title/alt
       cbox.on('click', function () {
           $el.prop('disabled', !cbox.prop('checked'));
           if (!cbox.prop('checked')) {
               $el.val('');  // Clear custom value
           }
       });

       d.get = function () {
           // Returns filtered attributes for allowed list
           return filteredAttributes;
       };

       return d;
   }

Dialog Features
---------------

- **Width/Height**: Number inputs with aspect ratio preservation
- **Title/Alt**: Text inputs with override checkboxes
- **Zoom**: Checkbox for clickenlarge functionality
- **CSS Class**: Text input for custom classes

Style System Integration
========================

Critical for CKEditor style drop-down functionality.

Event Listener: isStyleEnabledForBlock
---------------------------------------

Enables img styles when typo3image is selected:

.. code-block:: javascript

   this.listenTo(styleUtils, 'isStyleEnabledForBlock', (event, [style, element]) => {
       if (style.element === 'img') {
           for (const item of editor.model.document.selection.getFirstRange().getItems()) {
               if (item.name === 'typo3image') {
                   event.return = true;
               }
           }
       }
   });

Event Listener: isStyleActiveForBlock
--------------------------------------

Checks if style is currently applied:

.. code-block:: javascript

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

Event Listener: getAffectedBlocks
----------------------------------

Returns correct model element for style operations:

.. code-block:: javascript

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

GeneralHtmlSupport Integration
===============================

Manages class attribute updates from style system.

addModelHtmlClass Listener
---------------------------

.. code-block:: javascript

   const ghs = editor.plugins.get('GeneralHtmlSupport');
   ghs.decorate('addModelHtmlClass');

   this.listenTo(ghs, 'addModelHtmlClass', (event, [viewElement, className, selectable]) => {
       if (selectable && selectable.name === 'typo3image') {
           editor.model.change(writer => {
               writer.setAttribute('class', className.join(' '), selectable);
           });
       }
   });

removeModelHtmlClass Listener
------------------------------

.. code-block:: javascript

   ghs.decorate('removeModelHtmlClass');

   this.listenTo(ghs, 'removeModelHtmlClass', (event, [viewElement, className, selectable]) => {
       if (selectable && selectable.name === 'typo3image') {
           editor.model.change(writer => {
               writer.removeAttribute('class', selectable);
           });
       }
   });

Event Observers
===============

DoubleClickObserver
-------------------

Custom observer for double-click detection:

.. code-block:: javascript

   class DoubleClickObserver extends Engine.DomEventObserver {
       constructor(view) {
           super(view);
           this.domEventType = 'dblclick';
       }

       onDomEvent(domEvent) {
           this.fire(domEvent.type, domEvent);
       }
   }

   // Register observer
   editor.editing.view.addObserver(DoubleClickObserver);

   // Listen for double-click
   editor.listenTo(editor.editing.view.document, 'dblclick', (event, data) => {
       const modelElement = editor.editing.mapper.toModelElement(data.target);
       if (modelElement && modelElement.name === 'typo3image') {
           // Open edit dialog
           edit({...}, editor, {...});
       }
   });

Click Handler
-------------

Single-click selects image:

.. code-block:: javascript

   editor.listenTo(editor.editing.view.document, 'click', (event, data) => {
       const modelElement = editor.editing.mapper.toModelElement(data.target);
       if (modelElement && modelElement.name === 'typo3image') {
           editor.model.change(writer => {
               writer.setSelection(modelElement, 'on');
           });
       }
   });

Backend API Integration
=======================

getImageInfo() Function
------------------------

Fetches image data from backend:

.. code-block:: javascript

   function getImageInfo(editor, table, uid, params) {
       let url = editor.config.get('style').typo3image.routeUrl
           + '&action=info&fileId=' + encodeURIComponent(uid)
           + '&table=' + encodeURIComponent(table)
           + '&contentsLanguage=en&editorId=123';

       if (params.width) {
           url += '&P[width]=' + params.width;
       }
       if (params.height) {
           url += '&P[height]=' + params.height;
       }

       return $.getJSON(url);
   }

Plugin Configuration
====================

Registration (Configuration/RTE/Plugin.yaml)
---------------------------------------------

.. code-block:: yaml

   editor:
     config:
       importModules:
         - '@netresearch/rte-ckeditor-image/Plugins/typo3image.js'

     externalPlugins:
       typo3image: { route: "rteckeditorimage_wizard_select_image" }

   processing:
     allowTagsOutside:
       - img

JavaScript Module Registration
-------------------------------

.. code-block:: php

   // Configuration/JavaScriptModules.php
   return [
       'dependencies' => ['rte_ckeditor'],
       'tags' => ['backend.form'],
       'imports' => [
           '@netresearch/rte-ckeditor-image/' => 'EXT:rte_ckeditor_image/Resources/Public/JavaScript/',
       ],
   ];

Development Tips
================

1. **Always test style integration** - Verify StyleUtils and GeneralHtmlSupport work correctly
2. **Use browser console** - Monitor CKEditor model changes with ``editor.model.document.on('change')``
3. **Check conversions** - Verify upcast/downcast produce expected results
4. **Test attribute updates** - Ensure class and other attributes update correctly
5. **Debug with breakpoints** - Use browser DevTools to step through plugin code

Related Documentation
=====================

- :ref:`ckeditor-model-element`
- :ref:`ckeditor-style-integration`
- :ref:`ckeditor-conversions`
- :ref:`architecture-overview`
