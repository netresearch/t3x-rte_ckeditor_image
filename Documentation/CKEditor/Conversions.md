# CKEditor Conversion System

Complete guide to the upcast/downcast conversion system for transforming between HTML and model representations.

## Conversion Architecture

### Three-Layer System

```
┌──────────────────────────────────────┐
│ Data Layer (Database/API)           │
│ HTML with data-* attributes          │
│ <img data-htmlarea-file-uid="123"/>  │
└────────────┬─────────────────────────┘
             │
             │ Upcast (Load)
             │
┌────────────▼─────────────────────────┐
│ Model Layer (Abstract)               │
│ typo3image element with attributes   │
│ { name: 'typo3image', fileUid: 123 } │
└────────────┬─────────────────────────┘
             │
             │ Downcast (Render)
             │
┌────────────▼─────────────────────────┐
│ View Layer (Editor Display)          │
│ Visual HTML in contenteditable       │
│ <img src="..." class="..." />        │
└──────────────────────────────────────┘
```

---

## Upcast Conversions

### Purpose

**Upcast**: Transforms HTML (from database/API) into model elements when loading content into editor

**When Used**:
- Initial content load into CKEditor
- Paste from clipboard
- Insert HTML programmatically

### Image Element Upcast

```javascript
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
```

### Configuration Breakdown

#### View Matcher

```javascript
view: {
    name: 'img',
    attributes: ['data-htmlarea-file-uid', 'src']
}
```

**Purpose**: Defines which HTML elements should be converted

**Matching Logic**:
- **Element name**: Must be `<img>`
- **Required attributes**: Must have both `data-htmlarea-file-uid` AND `src`

**Examples**:

✅ **Matched (will be upcasted)**:
```html
<img data-htmlarea-file-uid="123" src="/fileadmin/image.jpg" />
```

❌ **Not matched (regular img passthrough)**:
```html
<img src="/fileadmin/image.jpg" />  <!-- Missing data-htmlarea-file-uid -->
<span data-htmlarea-file-uid="123"></span>  <!-- Wrong element -->
```

---

#### Model Creator Function

```javascript
model: (viewElement, { writer }) => {
    return writer.createElement('typo3image', {
        // attributes...
    });
}
```

**Parameters**:
- `viewElement`: The matched `<img>` element from HTML
- `writer`: Model writer for creating elements

**Return**: New model element with extracted attributes

---

#### Attribute Extraction

```javascript
fileUid: viewElement.getAttribute('data-htmlarea-file-uid'),
```

**Pattern**: Extract HTML attribute → map to model attribute

**Mappings**:

| HTML Attribute | Model Attribute | Transformation |
|----------------|-----------------|----------------|
| `data-htmlarea-file-uid` | `fileUid` | Direct copy |
| `data-htmlarea-file-table` | `fileTable` | Default: 'sys_file' |
| `src` | `src` | Direct copy |
| `width` | `width` | Default: empty string |
| `height` | `height` | Default: empty string |
| `class` | `class` | Default: empty string |
| `alt` | `alt` | Default: empty string |
| `data-alt-override` | `altOverride` | Default: false |
| `title` | `title` | Default: empty string |
| `data-title-override` | `titleOverride` | Default: false |
| `data-htmlarea-zoom` | `enableZoom` | Default: false |

---

### Upcast Example Flow

**Input HTML**:
```html
<img
    src="/fileadmin/image.jpg"
    data-htmlarea-file-uid="123"
    data-htmlarea-file-table="sys_file"
    width="800"
    height="600"
    alt="Product photo"
    title="Click to enlarge"
    class="img-fluid"
    data-htmlarea-zoom="true"
/>
```

**Upcast Process**:
1. CKEditor parser encounters `<img>` element
2. Checks if has `data-htmlarea-file-uid` and `src` ✅
3. Calls model creator function
4. Extracts all attributes
5. Creates model element

**Result Model Element**:
```javascript
{
    name: 'typo3image',
    attributes: {
        fileUid: 123,
        fileTable: 'sys_file',
        src: '/fileadmin/image.jpg',
        width: '800',
        height: '600',
        alt: 'Product photo',
        altOverride: false,
        title: 'Click to enlarge',
        titleOverride: false,
        class: 'img-fluid',
        enableZoom: true
    }
}
```

---

## Downcast Conversions

### Purpose

**Downcast**: Transforms model elements into HTML for editor display and data saving

**Two Pipelines**:
1. **Editing Downcast**: Render in contenteditable (editor view)
2. **Data Downcast**: Serialize for database storage

---

### Image Element Downcast

```javascript
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
```

### Configuration Breakdown

#### Model Matcher

```javascript
model: {
    name: 'typo3image',
    attributes: ['fileUid', 'fileTable', 'src']
}
```

**Purpose**: Defines which model elements trigger this conversion

**Matching**:
- Element name is `typo3image`
- Has `fileUid`, `fileTable`, `src` attributes (required for meaningful output)

---

#### View Creator Function

```javascript
view: (modelElement, { writer }) => {
    return writer.createEmptyElement('img', attributes);
}
```

**Parameters**:
- `modelElement`: The typo3image model element
- `writer`: View writer for creating elements

**Return**: New view element (`<img>`)

---

#### Attribute Mapping

```javascript
const attributes = {
    'src': modelElement.getAttribute('src'),
    'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
    // ...
};
```

**Pattern**: Read model attribute → map to HTML attribute

**Reverse Mappings**:

| Model Attribute | HTML Attribute | Transformation |
|-----------------|----------------|----------------|
| `src` | `src` | Direct copy |
| `fileUid` | `data-htmlarea-file-uid` | Direct copy |
| `fileTable` | `data-htmlarea-file-table` | Direct copy |
| `width` | `width` | Direct copy |
| `height` | `height` | Direct copy |
| `class` | `class` | Default: empty string |
| `alt` | `alt` | Default: empty string |
| `title` | `title` | Default: empty string |
| `altOverride` | `data-alt-override` | Only if true |
| `titleOverride` | `data-title-override` | Only if true |
| `enableZoom` | `data-htmlarea-zoom` | Only if true |

---

#### Conditional Attributes

```javascript
if (modelElement.getAttribute('titleOverride')) {
    attributes['data-title-override'] = true;
}
```

**Pattern**: Only add boolean attributes when true

**Why**: Cleaner HTML output, avoid unnecessary attributes

**Result**:
```html
<!-- When titleOverride = true -->
<img ... data-title-override="true" />

<!-- When titleOverride = false or absent -->
<img ... />  <!-- No data-title-override attribute -->
```

---

### Downcast Example Flow

**Input Model Element**:
```javascript
{
    name: 'typo3image',
    attributes: {
        fileUid: 123,
        fileTable: 'sys_file',
        src: '/fileadmin/image.jpg',
        width: '800',
        height: '600',
        alt: 'Product photo',
        altOverride: true,
        class: 'img-fluid',
        enableZoom: true
    }
}
```

**Downcast Process**:
1. CKEditor needs to render model element
2. Finds typo3image → elementToElement converter
3. Calls view creator function
4. Maps all attributes
5. Adds conditional attributes
6. Creates view element

**Result HTML**:
```html
<img
    src="/fileadmin/image.jpg"
    data-htmlarea-file-uid="123"
    data-htmlarea-file-table="sys_file"
    width="800"
    height="600"
    alt="Product photo"
    data-alt-override="true"
    class="img-fluid"
    data-htmlarea-zoom="true"
/>
```

---

## Attribute Converters

### Class Attribute Converter

```javascript
editor.conversion.for('downcast').attributeToAttribute({
    model: { name: 'typo3image', key: 'class' },
    view: 'class'
});
```

**Purpose**: Immediately sync class attribute changes to view

**Why Needed**: The elementToElement converter only runs on element creation. This converter handles attribute updates.

**Behavior**:
```javascript
// User changes class via style system
editor.model.change(writer => {
    writer.setAttribute('class', 'float-left mr-3', modelElement);
});

// Immediately reflected in view
<img class="float-left mr-3" ... />
```

---

### Custom Attribute Converters

You can add converters for any attribute:

```javascript
// Width changes immediately visible
editor.conversion.for('downcast').attributeToAttribute({
    model: { name: 'typo3image', key: 'width' },
    view: 'width'
});

// Alt changes immediately visible
editor.conversion.for('downcast').attributeToAttribute({
    model: { name: 'typo3image', key: 'alt' },
    view: 'alt'
});
```

---

## Data Pipeline

### Complete Load → Edit → Save Flow

```
1. Load from Database
   ─────────────────────►
   HTML String
   <img data-htmlarea-file-uid="123" src="..." />

2. Upcast (HTML → Model)
   ─────────────────────►
   Model Element
   typo3image { fileUid: 123, src: "..." }

3. Edit in Editor
   ─────────────────────►
   Model Changes
   width: "800" → "1200"
   class: "" → "float-left"

4. Downcast (Model → View)
   ─────────────────────►
   View Updates
   <img width="1200" class="float-left" ... />

5. Save to Database
   ─────────────────────►
   Data Downcast → HTML String
   <img data-htmlarea-file-uid="123" width="1200" class="float-left" ... />

6. Backend Processing
   ─────────────────────►
   RteImagesDbHook processes HTML
   Magic image processing, URL updates
```

---

## Paste Handling

### Paste from External Source

When pasting HTML from external sources (websites, Word, etc.):

```
1. Browser Paste Event
   ─────────────────────►
   External HTML
   <img src="https://example.com/image.jpg" />

2. Upcast Attempted
   ─────────────────────►
   Check: data-htmlarea-file-uid present? ❌
   Result: Upcast skipped, treated as regular <img>

3. Fallback Handling
   ─────────────────────►
   CKEditor default image handling
   May need custom paste processor for external images
```

### Paste from Same Editor

```
1. Copy typo3image
   ─────────────────────►
   Clipboard contains model element

2. Paste
   ─────────────────────►
   Direct model copy (no conversion needed)

3. Result
   ─────────────────────►
   Duplicate typo3image with same attributes
```

---

## Custom Conversion Patterns

### Pattern 1: Transformation During Upcast

```javascript
editor.conversion.for('upcast').elementToElement({
    view: {
        name: 'img',
        attributes: ['data-htmlarea-file-uid']
    },
    model: (viewElement, { writer }) => {
        // Transform srcset to src
        const src = viewElement.getAttribute('src') ||
                    viewElement.getAttribute('srcset')?.split(',')[0];

        // Parse dimensions from style
        const style = viewElement.getAttribute('style') || '';
        const widthMatch = style.match(/width:\s*(\d+)px/);
        const heightMatch = style.match(/height:\s*(\d+)px/);

        return writer.createElement('typo3image', {
            src: src,
            width: widthMatch ? widthMatch[1] : '',
            height: heightMatch ? heightMatch[1] : '',
            // ... other attributes
        });
    }
});
```

---

### Pattern 2: Conditional Downcast

```javascript
editor.conversion.for('downcast').elementToElement({
    model: 'typo3image',
    view: (modelElement, { writer }) => {
        // Different output based on context
        const width = parseInt(modelElement.getAttribute('width'), 10);

        // Large images get responsive class
        if (width > 1200) {
            attributes['class'] = (attributes['class'] || '') + ' img-responsive';
        }

        return writer.createEmptyElement('img', attributes);
    }
});
```

---

### Pattern 3: Multi-Element Conversion

```javascript
// Convert linked image to nested structure
editor.conversion.for('downcast').elementToStructure({
    model: 'typo3image',
    view: (modelElement, { writer }) => {
        const linkHref = modelElement.getAttribute('linkHref');

        if (linkHref) {
            // Create nested structure: <a><img/></a>
            const img = writer.createEmptyElement('img', imgAttributes);
            const link = writer.createContainerElement('a', { href: linkHref });
            writer.insert(writer.createPositionAt(link, 0), img);
            return link;
        } else {
            // Just image
            return writer.createEmptyElement('img', imgAttributes);
        }
    }
});
```

---

## Debugging Conversions

### Logging Upcast

```javascript
editor.conversion.for('upcast').elementToElement({
    view: { name: 'img', attributes: ['data-htmlarea-file-uid'] },
    model: (viewElement, { writer }) => {
        console.log('Upcasting image:', {
            src: viewElement.getAttribute('src'),
            fileUid: viewElement.getAttribute('data-htmlarea-file-uid'),
            allAttributes: Array.from(viewElement.getAttributes())
        });

        return writer.createElement('typo3image', {
            // ... attributes
        });
    }
});
```

### Logging Downcast

```javascript
editor.conversion.for('downcast').elementToElement({
    model: 'typo3image',
    view: (modelElement, { writer }) => {
        console.log('Downcasting typo3image:', {
            fileUid: modelElement.getAttribute('fileUid'),
            src: modelElement.getAttribute('src'),
            allAttributes: Array.from(modelElement.getAttributes())
        });

        return writer.createEmptyElement('img', attributes);
    }
});
```

### Inspecting Conversion Results

```javascript
// After loading content
editor.model.change(() => {
    const root = editor.model.document.getRoot();
    for (const item of root.getChildren()) {
        if (item.name === 'typo3image') {
            console.log('Found typo3image:', {
                fileUid: item.getAttribute('fileUid'),
                src: item.getAttribute('src')
            });
        }
    }
});
```

---

## Common Issues

### Issue: Images Not Converting on Load

**Symptoms**:
- HTML loaded but no typo3image elements in model
- Images appear as plain text or broken

**Causes**:
1. Missing `data-htmlarea-file-uid` attribute
2. Upcast converter not registered
3. View matcher too restrictive

**Solutions**:

✅ **Verify HTML has required attributes**:
```html
<!-- ✅ Will convert -->
<img data-htmlarea-file-uid="123" src="..." />

<!-- ❌ Won't convert (missing required attribute) -->
<img src="..." />
```

✅ **Check converter registration**:
```javascript
// Verify in browser console
console.log(editor.conversion);
// Should show upcast/downcast converters
```

---

### Issue: Attributes Lost During Conversion

**Symptoms**:
- Attributes present in HTML/model
- Missing in view/output

**Causes**:
1. Attribute not in schema `allowAttributes` list
2. Attribute not mapped in conversion
3. Conditional logic skipping attribute

**Solutions**:

✅ **Verify schema allows attribute**:
```javascript
allowAttributes: [
    'src', 'fileUid', /* add missing attribute here */
]
```

✅ **Add to conversion**:
```javascript
// In upcast
myCustomAttribute: viewElement.getAttribute('data-custom'),

// In downcast
'data-custom': modelElement.getAttribute('myCustomAttribute'),
```

---

### Issue: View Not Updating When Model Changes

**Symptoms**:
- Model attribute updated
- View doesn't reflect change
- Need to reload to see changes

**Cause**: Missing attribute converter for immediate sync

**Solution**:

✅ **Add attribute converter**:
```javascript
editor.conversion.for('downcast').attributeToAttribute({
    model: { name: 'typo3image', key: 'myAttribute' },
    view: 'data-my-attribute'
});
```

---

## Performance Optimization

### Batch Conversions

```javascript
// ❌ Inefficient: Convert one at a time
images.forEach(img => {
    editor.model.change(writer => {
        writer.setAttribute('class', 'processed', img);
    });
});

// ✅ Efficient: Single model change batch
editor.model.change(writer => {
    images.forEach(img => {
        writer.setAttribute('class', 'processed', img);
    });
});
```

### Lazy Attribute Reading

```javascript
// ❌ Inefficient: Read all attributes upfront
const allAttrs = {
    src: viewElement.getAttribute('src'),
    width: viewElement.getAttribute('width'),
    height: viewElement.getAttribute('height'),
    // ... 20 more attributes
};

// ✅ Efficient: Read only needed attributes
const src = viewElement.getAttribute('src');
const fileUid = viewElement.getAttribute('data-htmlarea-file-uid');
```

---

## Related Documentation

- [Plugin Development Guide](Plugin-Development.md)
- [Model Element Reference](Model-Element.md)
- [Style Integration](Style-Integration.md)
- [Architecture Overview](../Architecture/CKEditor-Integration.md)
- [Data Handling API](../API/DataHandling.md)
