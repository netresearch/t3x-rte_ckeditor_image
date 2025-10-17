# Controllers API

Controllers handle HTTP requests in the backend, providing image selection, information retrieval, and processing capabilities.

## SelectImageController

**Namespace**: `Netresearch\RteCKEditorImage\Controller`

**Purpose**: Main controller for image selection and processing in the CKEditor context.

**Backend Route**: `rteckeditorimage_wizard_select_image` → `/rte/wizard/selectimage`

### Methods

#### mainAction()
```php
public function mainAction(
    ServerRequestInterface $request
): ResponseInterface
```

**Purpose**: Entry point for the image browser/selection interface.

**Parameters**:
- `$request` - PSR-7 server request with query parameters

**Query Parameters**:
- `mode` - Browser mode (default: `file` from route configuration)
- `bparams` - Browser parameters passed to file browser

**Returns**: PSR-7 response with file browser HTML

**Usage**:
```javascript
// Called from CKEditor plugin
const contentUrl = routeUrl + '&contentsLanguage=en&editorId=123&bparams=' + bparams.join('|');
```

---

#### infoAction()
```php
public function infoAction(
    ServerRequestInterface $request
): ResponseInterface
```

**Purpose**: Returns JSON with image information and processed variants.

**Parameters**:
- `$request` - Server request with file identification and processing parameters

**Query Parameters**:
- `fileId` - FAL file UID
- `table` - Database table (usually `sys_file`)
- `P[width]` - Desired width (optional)
- `P[height]` - Desired height (optional)
- `action` - Action type (`info`)

**Returns**: JSON response with structure:
```json
{
  "uid": 123,
  "url": "/fileadmin/user_upload/image.jpg",
  "width": 1920,
  "height": 1080,
  "title": "Image title",
  "alt": "Alternative text",
  "processed": {
    "url": "/fileadmin/_processed_/image_hash.jpg",
    "width": 800,
    "height": 450
  },
  "lang": {
    "override": "Override %s",
    "overrideNoDefault": "Override (no default)",
    "zoom": "Zoom",
    "cssClass": "CSS Class"
  }
}
```

**Usage**:
```javascript
// From CKEditor plugin
getImageInfo(editor, 'sys_file', 123, {width: '800', height: '450'})
  .then(function(img) {
    // Use image data
  });
```

---

#### getImage()
```php
protected function getImage(
    int $fileUid,
    string $table
): ?\TYPO3\CMS\Core\Resource\File
```

**Purpose**: Retrieves FAL File object.

**Parameters**:
- `$fileUid` - File UID from FAL
- `$table` - Database table (sys_file)

**Returns**: File object or null if not found

**Throws**: Exception if file cannot be loaded

---

#### processImage()
```php
protected function processImage(
    \TYPO3\CMS\Core\Resource\File $file,
    array $processingInstructions
): ?\TYPO3\CMS\Core\Resource\ProcessedFile
```

**Purpose**: Creates processed image variant with specified dimensions.

**Parameters**:
- `$file` - Original FAL file
- `$processingInstructions` - Array with `width`, `height`, `crop`, etc.

**Returns**: Processed file or null

**Processing Instructions**:
```php
[
    'width' => '800',
    'height' => '600',
    'crop' => null  // Optional crop configuration
]
```

---

## ImageRenderingController

**Namespace**: `Netresearch\RteCKEditorImage\Controller`

**Purpose**: Frontend rendering controller for `<img>` tags in RTE content.

**TypoScript Integration**:
```typoscript
lib.parseFunc_RTE {
    tags.img = TEXT
    tags.img {
        current = 1
        preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
    }
}
```

### Methods

#### renderImageAttributes()
```php
public function renderImageAttributes(
    string $content,
    array $conf,
    \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $cObj
): string
```

**Purpose**: Processes `<img>` tags in RTE content, applying magic images and FAL processing.

**Parameters**:
- `$content` - Current HTML content (single `<img>` tag)
- `$conf` - TypoScript configuration
- `$cObj` - Content object renderer

**Returns**: Processed HTML with updated image URL and attributes

**Processing Steps**:
1. Parse `data-htmlarea-file-uid` attribute
2. Load FAL file from UID
3. Apply magic image processing (resize, crop)
4. Generate processed image URL
5. Remove internal data attributes
6. Return updated HTML

**Data Attributes Processed**:
- `data-htmlarea-file-uid` - FAL file reference
- `data-htmlarea-file-table` - Table name
- `data-htmlarea-zoom` - Zoom functionality
- `data-title-override` - Title override flag
- `data-alt-override` - Alt override flag

---

## ImageLinkRenderingController

**Namespace**: `Netresearch\RteCKEditorImage\Controller`

**Purpose**: Handles rendering of images within `<a>` tags (linked images).

**TypoScript Integration**:
```typoscript
lib.parseFunc_RTE {
    tags.a = TEXT
    tags.a {
        current = 1
        preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageLinkRenderingController->renderImages
    }
}
```

### Methods

#### renderImages()
```php
public function renderImages(
    string $content,
    array $conf,
    \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $cObj
): string
```

**Purpose**: Processes `<img>` tags within `<a>` tags, maintaining link functionality while applying image processing.

**Parameters**:
- `$content` - HTML content (complete `<a>` tag with nested `<img>`)
- `$conf` - TypoScript configuration
- `$cObj` - Content object renderer

**Returns**: Processed HTML with both link and image correctly rendered

**Usage Scenario**:
```html
<!-- Input -->
<a href="page-link">
  <img data-htmlarea-file-uid="123" src="..." />
</a>

<!-- Output -->
<a href="page-link">
  <img src="/fileadmin/_processed_/image_hash.jpg" width="800" height="600" />
</a>
```

---

## Service Configuration

All controllers are configured in `Configuration/Services.yaml`:

```yaml
Netresearch\RteCKEditorImage\Controller\SelectImageController:
  tags: ['backend.controller']
```

Controllers use constructor injection for dependencies like `ResourceFactory`.

---

## Usage Examples

### Calling Image Info from JavaScript
```javascript
function getImageInfo(editor, table, uid, params) {
    let url = editor.config.get('style').typo3image.routeUrl
        + '&action=info&fileId=' + encodeURIComponent(uid)
        + '&table=' + encodeURIComponent(table);

    if (params.width) {
        url += '&P[width]=' + params.width;
    }
    if (params.height) {
        url += '&P[height]=' + params.height;
    }

    return $.getJSON(url);
}
```

### TypoScript Configuration
```typoscript
lib.parseFunc_RTE {
    tags.img = TEXT
    tags.img {
        current = 1
        preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
    }

    nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib {
        # Remove internal attributes from frontend output
        data-htmlarea-file-uid.unset = 1
        data-htmlarea-file-table.unset = 1
        data-htmlarea-zoom.unset = 1
        data-title-override.unset = 1
        data-alt-override.unset = 1
    }
}
```

---

## Related Documentation

- [Architecture Overview](../Architecture/Overview.md)
- [Data Flow](../Architecture/DataFlow.md)
- [TypoScript Configuration](../Configuration/TypoScript-Reference.md)
