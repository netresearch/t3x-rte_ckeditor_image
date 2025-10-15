# CKEditor 5 Integration

## JavaScript Plugin Structure

### Plugin Class: Typo3Image
**Location**: `Resources/Public/JavaScript/Plugins/typo3image.js`

**Base**: `Core.Plugin` (CKEditor 5)

**Required Dependencies**:
- `StyleUtils` - Style system integration (CRITICAL)
- `GeneralHtmlSupport` - HTML attribute support (CRITICAL)

⚠️ **Important**: Both dependencies are MANDATORY. Missing `GeneralHtmlSupport` causes style functionality to fail.

## Custom Model Element: typo3image

### Schema Definition
```javascript
inheritAllFrom: '$blockObject'
allowIn: ['$text', '$block']
```

### Attributes
- `src` - Image source URL
- `fileUid` - TYPO3 FAL file UID
- `fileTable` - TYPO3 table (default: 'sys_file')
- `alt` - Alternative text
- `altOverride` - Boolean flag for alt override
- `title` - Advisory title
- `titleOverride` - Boolean flag for title override
- `class` - CSS classes
- `enableZoom` - Zoom/clickenlarge functionality
- `width` - Image width
- `height` - Image height
- `htmlA`, `linkHref`, `linkTarget`, `linkTitle` - Link attributes (for wrapped images)

## Key Functionality

### Image Selection: selectImage()
- Opens TYPO3 Modal with iframe
- Backend file browser integration
- Returns: `{uid: number, table: string}`

### Image Editing: edit()
- Calls `getImageInfo()` for current image data
- Opens attribute dialog via `askImageAttributes()`
- Updates model element with new attributes
- Uses `editor.model.insertObject()` for insertion

### Image Dialog: getImageDialog()
**Fields**:
1. Width/Height (number inputs with aspect ratio preservation)
2. Title/Alt (text inputs with override checkboxes)
3. Zoom checkbox (data-htmlarea-zoom attribute)
4. CSS Class input

**Behavior**:
- Aspect ratio automatically maintained during dimension changes
- Override checkboxes enable/disable title/alt inputs
- Mousewheel support for dimension adjustment

### Image Info API: getImageInfo()
**Endpoint**: Backend route with parameters
- `action=info`
- `fileId` (UID)
- `table` (file table)
- `P[width]`, `P[height]` (optional dimensions)

**Returns**: JSON with image metadata and processed variants

## Style System Integration (CRITICAL)

### Recent Bug Fixes
Three event listeners added to fix style drop-down functionality:

#### 1. isStyleEnabledForBlock
**Purpose**: Enable img styles when typo3image selected
```javascript
this.listenTo(styleUtils, 'isStyleEnabledForBlock', (event, [style, element]) => {
    if (style.element === 'img') {
        // Check if typo3image in selection
        event.return = true;
    }
})
```

#### 2. isStyleActiveForBlock
**Purpose**: Check if style is active for typo3image
```javascript
this.listenTo(styleUtils, 'isStyleActiveForBlock', (event, [style, element]) => {
    if (style.element === 'img') {
        // Compare typo3image class attribute with style classes
        event.return = true/false;
    }
})
```

#### 3. getAffectedBlocks
**Purpose**: Return correct model element for style operations
```javascript
this.listenTo(styleUtils, 'getAffectedBlocks', (event, [style, element]) => {
    if (style.element === 'img') {
        // Return typo3image model element
        event.return = [item];
    }
})
```

### GeneralHtmlSupport Integration
Two event listeners for class attribute management:

#### 1. addModelHtmlClass
```javascript
ghs.decorate('addModelHtmlClass')
this.listenTo(ghs, 'addModelHtmlClass', (event, [viewElement, className, selectable]) => {
    // Update class attribute on typo3image model
    writer.setAttribute('class', className.join(' '), selectable);
})
```

#### 2. removeModelHtmlClass
```javascript
ghs.decorate('removeModelHtmlClass')
this.listenTo(ghs, 'removeModelHtmlClass', (event, [viewElement, className, selectable]) => {
    // Remove class attribute from typo3image model
    writer.removeAttribute('class', selectable);
})
```

## Conversion System

### Upcast (HTML → Model)
Converts `<img>` with `data-htmlarea-file-uid` to `typo3image` model element
- Maps all data attributes to model attributes
- Preserves override flags, zoom settings

### Downcast (Model → HTML)
Converts `typo3image` model to `<img>` element
- Sets all necessary data attributes
- Conditionally adds override/zoom attributes
- Attribute converter for `class` attribute updates

### Downcast Class Attribute Converter
```javascript
editor.conversion.for('downcast').attributeToAttribute({
    model: { name: 'typo3image', key: 'class' },
    view: 'class'
})
```
Makes class attribute changes immediately visible in editor view

## Event Observers

### DoubleClickObserver
Custom observer extending `Engine.DomEventObserver`
- Detects double-click on images
- Opens edit dialog for typo3image elements

### Click Handler
Single-click selects typo3image element
```javascript
editor.listenTo(editor.editing.view.document, 'click', (event, data) => {
    // Select typo3image on click
    writer.setSelection(modelElement, 'on');
})
```

## Module Registration (Configuration/JavaScriptModules.php)
```php
'dependencies' => ['rte_ckeditor'],
'tags' => ['backend.form'],
'imports' => [
    '@netresearch/rte-ckeditor-image/' => 'EXT:rte_ckeditor_image/Resources/Public/JavaScript/'
]
```