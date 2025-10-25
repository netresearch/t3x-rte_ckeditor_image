# Resources/AGENTS.md

<!-- Managed by agent: keep sections & order; edit content, not structure. Last updated: 2025-10-15 -->

**Scope:** JavaScript/CKEditor 5 plugin, frontend assets, translations
**Parent:** [../AGENTS.md](../AGENTS.md)

## 📋 Overview

Frontend implementation for TYPO3 CKEditor Image extension. Components:

### JavaScript Plugin
- **typo3image.js** - Complete CKEditor 5 plugin implementation
  - Custom model element: `typo3image`
  - UI components: image browser, attribute dialog
  - Style integration: StyleUtils + GeneralHtmlSupport
  - Conversions: HTML ↔ Model (upcast/downcast)
  - Event handlers: click, double-click, dblclick observer

### Assets
- **Public/Icons/** - Extension icon
- **Public/Images/** - Demo images
- **Private/Language/** - Translation files (xlf format)

### File Structure
```
Resources/
├── Private/
│   └── Language/
│       └── locallang_be.xlf          # Backend translations
└── Public/
    ├── Icons/
    │   └── Extension.png             # Extension icon
    ├── Images/
    │   └── demo.gif                  # Demo/documentation
    └── JavaScript/
        └── Plugins/
            └── typo3image.js         # CKEditor 5 plugin (ES6)
```

## 🏗️ Architecture Patterns

### CKEditor 5 Plugin Pattern
```javascript
export default class Typo3Image extends Core.Plugin {
    static pluginName = 'Typo3Image';

    static get requires() {
        return ['StyleUtils', 'GeneralHtmlSupport'];  // ⚠️ REQUIRED for v13.0.0+
    }

    init() {
        const editor = this.editor;
        // Plugin initialization
    }
}
```

### Key Components
1. **Model Element Registration:** Custom `typo3image` schema
2. **Conversions:** Bidirectional HTML ↔ Model transformations
3. **UI Components:** Image browser modal, attribute dialog
4. **Style Integration:** StyleUtils event listeners for block styles
5. **GeneralHtmlSupport Integration:** HTML class attribute synchronization

## 🔧 Build & Tests

### JavaScript Development
```bash
# No npm build required - pure ES6 modules
# CKEditor packages provided by TYPO3 core

# Code quality (PHP side affects structure)
make lint                    # Includes structural validation
```

### Important Notes
- **CKEditor 5 packages:** Provided by TYPO3 core, imported directly via @ckeditor/* namespace
- **TYPO3 integration:** JavaScript module loaded by TYPO3 backend
- **ES6 modules:** Modern import syntax
- **jQuery available:** For TYPO3 modal integration

## 📝 Code Style

### ES6 Module Standards

**1. Import Pattern**
```javascript
// CKEditor 5 imports - direct from npm packages
import { Plugin } from '@ckeditor/ckeditor5-core';
import { ButtonView } from '@ckeditor/ckeditor5-ui';
import { DomEventObserver } from '@ckeditor/ckeditor5-engine';

// TYPO3 backend imports
import { default as Modal } from '@typo3/backend/modal.js';
import $ from 'jquery';
```

**2. Class Pattern**
```javascript
export default class Typo3Image extends Core.Plugin {
    static pluginName = 'Typo3Image';          // Required: unique identifier

    static get requires() {                     // Required: dependencies
        return ['StyleUtils', 'GeneralHtmlSupport'];
    }

    init() {                                    // Required: initialization
        const editor = this.editor;
        // Implementation
    }
}
```

**3. Indentation**
- **Spaces:** 4 spaces (not tabs)
- **Consistent:** Match existing CKEditor 5 conventions
- **Nested:** Clear visual hierarchy

**4. Variable Naming**
```javascript
const editor = this.editor;              // camelCase for variables
const styleUtils = editor.plugins.get('StyleUtils');
const $element = $('<div>');             // $ prefix for jQuery objects
```

## 🔒 Security

### TYPO3 Backend Integration
- **Modal API:** Use `Modal.advanced()` for dialogs
- **Route URLs:** From editor config, not hardcoded
- **Parameter encoding:** Use `encodeURIComponent()` for URL params
- **AJAX requests:** Via jQuery.getJSON() with TYPO3 routes

### XSS Prevention
```javascript
// ✅ Good: Proper escaping
$('<label>')
    .text(config.label)  // Auto-escaped
    .appendTo($group);

const escapedClass = $('<div/>')
    .html($inputCssClass.val().trim())
    .text();  // HTML entity encoding

// ❌ Bad: Direct HTML injection
$group.html('<label>' + config.label + '</label>');  // Vulnerable to XSS
```

### Data Validation
```javascript
// ✅ Good: Type coercion and validation
const fileUid = (int)($request->getQueryParams()['fileId'] ?? 0);

// ✅ Good: Attribute filtering
const allowedAttributes = [
    '!src', 'alt', 'title', 'class', 'rel', 'width', 'height',
    'data-htmlarea-zoom', 'data-title-override', 'data-alt-override'
];

filteredAttr = Object.keys(attributesNew)
    .filter(key => allowedAttributes.includes(key))
    .reduce((obj, key) => {
        obj[key] = attributesNew[key];
        return obj;
    }, {});
```

## ✅ PR/Commit Checklist

### JavaScript-Specific Checks
1. ✅ **Plugin requirements:** StyleUtils + GeneralHtmlSupport in `requires()`
2. ✅ **ES6 syntax:** No CommonJS, use ES6 imports/exports
3. ✅ **CKEditor API:** Follow CKEditor 5 conventions (model, view, conversion)
4. ✅ **Model schema:** Properly defined with allowAttributes
5. ✅ **Conversions:** Both upcast (HTML→model) and downcast (model→HTML)
6. ✅ **Event listeners:** Proper cleanup, avoid memory leaks
7. ✅ **TYPO3 integration:** Use Modal API, UriBuilder routes
8. ✅ **Indentation:** 4 spaces, consistent throughout
9. ✅ **Documentation:** Update CKEditor plugin docs if API changed

## 🎓 Good vs Bad Examples

### ✅ Good: Plugin Structure (v13.0.0+)

```javascript
export default class Typo3Image extends Core.Plugin {
    static pluginName = 'Typo3Image';

    static get requires() {
        // ✅ REQUIRED: Both plugins for style functionality
        return ['StyleUtils', 'GeneralHtmlSupport'];
    }

    init() {
        const editor = this.editor;

        // ✅ Good: Get plugin instances
        const styleUtils = editor.plugins.get('StyleUtils');
        const ghs = editor.plugins.get('GeneralHtmlSupport');

        // ✅ Good: Listen to style events
        this.listenTo(styleUtils, 'isStyleEnabledForBlock', (event, [style, element]) => {
            if (style.element === 'img') {
                for (const item of editor.model.document.selection.getFirstRange().getItems()) {
                    if (item.name === 'typo3image') {
                        event.return = true;
                    }
                }
            }
        });

        // ✅ Good: Decorate GHS methods
        ghs.decorate('addModelHtmlClass');
        this.listenTo(ghs, 'addModelHtmlClass', (event, [viewElement, className, selectable]) => {
            if (selectable && selectable.name === 'typo3image') {
                editor.model.change(writer => {
                    writer.setAttribute('class', className.join(' '), selectable);
                });
            }
        });
    }
}
```

### ❌ Bad: Missing Dependencies (v12.x pattern)

```javascript
export default class Typo3Image extends Core.Plugin {
    static pluginName = 'Typo3Image';

    // ❌ Missing requires() - breaks style integration in v13.0.0+
    // Style drop-down will be disabled for typo3image elements

    init() {
        const editor = this.editor;

        // ❌ No StyleUtils integration
        // ❌ No GeneralHtmlSupport integration
        // Style functionality will not work
    }
}
```

### ✅ Good: Model Schema Definition

```javascript
editor.model.schema.register('typo3image', {
    inheritAllFrom: '$blockObject',               // ✅ Proper inheritance
    allowIn: ['$text', '$block'],                 // ✅ Where it can be placed
    allowAttributes: [                            // ✅ Explicit attributes
        'src',
        'fileUid',
        'fileTable',
        'alt',
        'altOverride',
        'title',
        'titleOverride',
        'class',
        'enableZoom',
        'width',
        'height'
    ],
});
```

### ❌ Bad: Incomplete Schema

```javascript
editor.model.schema.register('typo3image', {
    // ❌ No inheritance - element behavior undefined
    // ❌ No allowIn - element cannot be inserted
    // ❌ No allowAttributes - no attributes can be set
});
```

### ✅ Good: Conversion Pattern

```javascript
// ✅ Upcast: HTML → Model
editor.conversion
    .for('upcast')
    .elementToElement({
        view: {
            name: 'img',
            attributes: ['data-htmlarea-file-uid', 'src']
        },
        model: (viewElement, { writer }) => {
            return writer.createElement('typo3image', {
                fileUid: viewElement.getAttribute('data-htmlarea-file-uid'),
                src: viewElement.getAttribute('src'),
                width: viewElement.getAttribute('width') || '',
                height: viewElement.getAttribute('height') || ''
            });
        }
    });

// ✅ Downcast: Model → HTML
editor.conversion
    .for('downcast')
    .elementToElement({
        model: 'typo3image',
        view: (modelElement, { writer }) => {
            const attributes = {
                'src': modelElement.getAttribute('src'),
                'data-htmlarea-file-uid': modelElement.getAttribute('fileUid'),
                'width': modelElement.getAttribute('width'),
                'height': modelElement.getAttribute('height')
            };
            return writer.createEmptyElement('img', attributes);
        }
    });
```

### ❌ Bad: Missing Conversions

```javascript
// ❌ Only downcast - editor cannot load existing HTML
editor.conversion
    .for('downcast')
    .elementToElement({
        model: 'typo3image',
        view: 'img'
    });

// ❌ Missing upcast - existing images in content won't be recognized
// ❌ Result: Content loss when loading/saving
```

### ✅ Good: TYPO3 Integration

```javascript
function getImageInfo(editor, table, uid, params) {
    // ✅ Get route from editor config
    let url = editor.config.get('style').typo3image.routeUrl
        + '&action=info'
        + '&fileId=' + encodeURIComponent(uid)          // ✅ Encode params
        + '&table=' + encodeURIComponent(table);

    // ✅ Use jQuery for AJAX (TYPO3 standard)
    return $.getJSON(url);
}

// ✅ Use TYPO3 Modal API
const modal = Modal.advanced({
    title: 'Image Properties',
    content: dialog.$el,
    buttons: [
        {
            text: 'Ok',
            trigger: function() {
                // Handle OK
                modal.hideModal();
            }
        }
    ]
});
```

### ❌ Bad: Hardcoded Integration

```javascript
function getImageInfo(editor, table, uid, params) {
    // ❌ Hardcoded URL
    let url = '/typo3/ajax/rteckeditorimage?action=info'
        + '&fileId=' + uid                              // ❌ No encoding
        + '&table=' + table;

    // ❌ Fetch API instead of jQuery
    return fetch(url).then(r => r.json());
}

// ❌ Browser native modal
const result = window.prompt('Image URL:', '');
```

## 🆘 When Stuck

### Documentation
- **Plugin Development:** [docs/CKEditor/Plugin-Development.md](../docs/CKEditor/Plugin-Development.md)
- **Model Element:** [docs/CKEditor/Model-Element.md](../docs/CKEditor/Model-Element.md)
- **Style Integration:** [docs/CKEditor/Style-Integration.md](../docs/CKEditor/Style-Integration.md)
- **Conversions:** [docs/CKEditor/Conversions.md](../docs/CKEditor/Conversions.md)

### CKEditor 5 Resources
- **Plugin API:** https://ckeditor.com/docs/ckeditor5/latest/framework/architecture/plugins.html
- **Model:** https://ckeditor.com/docs/ckeditor5/latest/framework/architecture/editing-engine.html
- **Schema:** https://ckeditor.com/docs/ckeditor5/latest/api/module_engine_model_schema-Schema.html
- **Conversion:** https://ckeditor.com/docs/ckeditor5/latest/framework/deep-dive/conversion/intro.html

### TYPO3 Resources
- **Backend Modal API:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Backend/JavaScript/Modal/Index.html
- **CKEditor Integration:** https://docs.typo3.org/c/typo3/cms-rte-ckeditor/main/en-us/

### Common Issues
- **Style drop-down disabled:** Missing GeneralHtmlSupport or StyleUtils in `requires()`
- **Images not loading:** Check upcast conversion, verify data attributes
- **Styles not applying:** Verify all three StyleUtils event listeners
- **Class not syncing:** Check GHS decorate + listener setup

## 📐 House Rules

### CKEditor 5 Patterns
- **Plugin inheritance:** Extend `Core.Plugin`
- **Static properties:** `pluginName` (required), `requires` (dependencies)
- **Init method:** All setup in `init()`, access via `this.editor`
- **Event listeners:** Use `this.listenTo()` for proper cleanup
- **Model changes:** Always wrap in `editor.model.change(writer => {...})`

### Model Element Design
- **Schema first:** Define schema before conversions
- **Minimal attributes:** Only store what's needed for round-tripping
- **Inheritance:** Use `inheritAllFrom: '$blockObject'` for block elements
- **Namespace attributes:** Use `data-*` prefix for custom attributes

### Conversions
- **Bidirectional:** Always implement both upcast and downcast
- **Attribute mapping:** Map all model attributes to view
- **Data preservation:** Don't lose attributes during conversion
- **Attribute converters:** For dynamic attributes (e.g., class)

### Style Integration (v13.0.0+)
- **Required plugins:** StyleUtils + GeneralHtmlSupport
- **Three event listeners:**
  1. `isStyleEnabledForBlock` - Enable styles for typo3image
  2. `isStyleActiveForBlock` - Check if style is active
  3. `getAffectedBlocks` - Return typo3image element
- **GHS decoration:** Decorate addModelHtmlClass + removeModelHtmlClass
- **Class synchronization:** Keep model and view class attributes in sync

### TYPO3 Integration
- **Route URLs:** From editor config, never hardcoded
- **Modal API:** Use `Modal.advanced()` for dialogs
- **jQuery available:** Use for AJAX and DOM manipulation
- **Localization:** Via backend language files (xlf format)

### Performance
- **Event listeners:** Clean up via `this.listenTo()` (auto-cleanup on destroy)
- **Observer lifecycle:** Register observers once in `init()`
- **DOM queries:** Cache jQuery selectors, avoid repeated queries
- **Batch changes:** Group model changes in single `editor.model.change()` call

### Backward Compatibility
- **Legacy attributes:** Support old `data-htmlarea-clickenlarge` alongside new `data-htmlarea-zoom`
- **Attribute migration:** Transform deprecated attributes during upcast
- **Graceful degradation:** Handle missing attributes with defaults

## 🔗 Related

- **[Classes/AGENTS.md](../Classes/AGENTS.md)** - PHP backend integration
- **[Tests/AGENTS.md](../Tests/AGENTS.md)** - Testing patterns
- **[docs/CKEditor/](../docs/CKEditor/)** - Complete CKEditor documentation
- **Resources/Public/JavaScript/Plugins/typo3image.js:424** - Plugin requires() method
