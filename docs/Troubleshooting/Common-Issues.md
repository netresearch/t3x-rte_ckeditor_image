# Common Issues & Solutions

Frequently encountered issues and their solutions when working with rte_ckeditor_image.

## Installation & Configuration Issues

### Issue: Image Button Not Visible in Toolbar

**Symptoms**:
- Insert image button missing from CKEditor toolbar
- RTE loads but no image functionality

**Causes**:
1. Plugin not properly imported in RTE configuration
2. `removePlugins` includes image plugin
3. Toolbar configuration missing `insertimage` item

**Solution**:

```yaml
# Configuration/RTE/Default.yaml
imports:
  - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

editor:
  config:
    removePlugins: null  # Critical: Don't remove image plugin
    toolbar:
      items:
        - insertimage  # Add to toolbar
```

---

### Issue: Style Drop-Down Not Working with Images

**Symptoms**:
- Styles disabled when image selected
- Style changes not applied to images

**Cause**: Missing `GeneralHtmlSupport` dependency (fixed in v13.0.0+)

**Solution**: Ensure you're using extension version 13.0.0 or higher:

```bash
composer require netresearch/rte-ckeditor-image:^13.0
```

The plugin now requires:
```javascript
static get requires() {
    return ['StyleUtils', 'GeneralHtmlSupport'];  // Both mandatory
}
```

---

### Issue: Magic Image Maximum Dimensions Not Working

**Symptoms**:
- Images not respecting configured maxWidth/maxHeight
- Large images not being resized

**Cause**: TSConfig settings in custom template extension not loaded (TYPO3 bug #87068)

**Solution**: Add settings to root page config instead:

```typoscript
# In root page TSConfig (not template extension)
RTE.default.buttons.image.options.magic {
    maxWidth = 1920
    maxHeight = 9999
}
```

---

## Image Selection Issues

### Issue: File Browser Empty or Not Loading

**Symptoms**:
- Modal opens but shows no files
- File browser stuck loading

**Causes**:
1. No file mount configured for backend user
2. Missing file permissions
3. Empty fileadmin directory

**Solution**:

```typoscript
# User TSConfig
options.defaultUploadFolder = 1:fileadmin/user_upload/
```

Verify backend user has file mount in:
**Backend** → **User Management** → **Backend Users** → **File Mounts**

---

### Issue: "File Not Found" After Selection

**Symptoms**:
- Image selected but error occurs
- Empty image inserted

**Causes**:
1. File reference invalid
2. Storage not accessible
3. File deleted from filesystem

**Solution**:
1. Verify file exists in `fileadmin/`
2. Check file permissions (readable by web server)
3. Clear file abstraction layer cache:

```bash
./vendor/bin/typo3 cache:flush --group=system
```

---

## Frontend Rendering Issues

### Issue: Images Not Appearing in Frontend

**Symptoms**:
- Images visible in backend RTE
- Images missing in frontend output

**Causes**:
1. Static template not included
2. TypoScript rendering hooks missing
3. Cached content

**Solution**:

1. **Include Static Template**:
   - Go to **Template** → **Info/Modify**
   - Edit whole template record
   - Include `CKEditor Image Support` before Fluid Styled Content

2. **Verify TypoScript**:
```typoscript
lib.parseFunc_RTE {
    tags.img = TEXT
    tags.img {
        current = 1
        preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
    }
}
```

3. **Clear Caches**:
```bash
./vendor/bin/typo3 cache:flush
```

---

### Issue: Processed Images Not Generated

**Symptoms**:
- Original large images displayed
- No `_processed_/` directory created
- Slow page load due to large images

**Causes**:
1. Image processing disabled
2. ImageMagick/GraphicsMagick not configured
3. File permissions issue

**Solution**:

1. **Verify Image Processing Configuration**:
```php
// LocalConfiguration.php
$GLOBALS['TYPO3_CONF_VARS']['GFX'] = [
    'processor' => 'ImageMagick',  // or 'GraphicsMagick'
    'processor_path' => '/usr/bin/',
    'processor_enabled' => true,
];
```

2. **Test Image Processing**:
```bash
# TYPO3 CLI
./vendor/bin/typo3 backend:test:imageprocessing
```

3. **Check Directory Permissions**:
```bash
# Ensure _processed_/ is writable
chmod 775 fileadmin/_processed_/
```

---

### Issue: Data Attributes Visible in Frontend

**Symptoms**:
- `data-htmlarea-file-uid` visible in HTML
- Internal attributes exposed

**Cause**: HTMLparser configuration missing

**Solution**:

```typoscript
lib.parseFunc_RTE.nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib {
    data-htmlarea-file-uid.unset = 1
    data-htmlarea-file-table.unset = 1
    data-htmlarea-zoom.unset = 1
    data-title-override.unset = 1
    data-alt-override.unset = 1
}
```

---

## JavaScript/CKEditor Issues

### Issue: JavaScript Console Errors

**Symptoms**:
- Browser console shows errors
- Editor doesn't load properly

**Common Errors**:

#### 1. "GeneralHtmlSupport is not defined"
**Cause**: Extension version < 13.0.0

**Solution**: Update to latest version:
```bash
composer update netresearch/rte-ckeditor-image
```

#### 2. "Cannot read property 'typo3image' of undefined"
**Cause**: Plugin configuration not loaded

**Solution**: Verify `Configuration/RTE/Plugin.yaml` imported:
```yaml
imports:
  - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }
```

#### 3. jQuery Errors
**Cause**: jQuery not available in context

**Solution**: The plugin requires jQuery. Ensure TYPO3 backend context loads it (typically automatic).

---

### Issue: Double-Click on Image Does Nothing

**Symptoms**:
- Double-clicking image doesn't open dialog
- Edit functionality not working

**Causes**:
1. DoubleClickObserver not registered
2. JavaScript error blocking execution
3. Image not recognized as typo3image

**Solution**:
1. Check browser console for JavaScript errors
2. Verify image has `data-htmlarea-file-uid` attribute
3. Clear browser cache and reload
4. Check CKEditor version compatibility (requires CKEditor 5)

---

## Performance Issues

### Issue: Slow Editor Loading

**Symptoms**:
- CKEditor takes long time to initialize
- Image browser slow to open

**Solutions**:

1. **Optimize Image Processing**:
```php
$GLOBALS['TYPO3_CONF_VARS']['GFX']['jpg_quality'] = 85;
$GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_effects'] = false;  // If not needed
```

2. **Reduce Maximum Dimensions**:
```typoscript
RTE.default.buttons.image.options.magic {
    maxWidth = 1200  # Instead of 1920
    maxHeight = 800  # Instead of 9999
}
```

3. **Enable Browser Caching**: Configure web server to cache processed images

---

### Issue: Large Database Size

**Symptoms**:
- Database growing rapidly
- sys_refindex table very large

**Cause**: Excessive soft reference entries

**Solution**: Rebuild reference index:
```bash
./vendor/bin/typo3 referenceindex:update
```

---

## Upgrade Issues

### Issue: Extension Not Working After TYPO3 13 Upgrade

**Symptoms**:
- Extension installed but not functional
- Errors about missing classes

**Solution**: Ensure correct version compatibility:

```json
{
  "require": {
    "typo3/cms-core": "^13.4",
    "netresearch/rte-ckeditor-image": "^13.0"
  }
}
```

```bash
composer update
./vendor/bin/typo3 cache:flush
./vendor/bin/typo3 extension:setup
```

---

### Issue: Custom Image Styles Lost After Upgrade

**Symptoms**:
- Custom styles no longer available
- Style drop-down empty

**Cause**: RTE configuration changed

**Solution**: Re-apply custom styles in RTE configuration:

```yaml
editor:
  config:
    style:
      definitions:
        - name: 'Your Custom Style'
          element: 'img'
          classes: ['your-class']
```

---

## Debugging Techniques

### Enable RTE Debugging

```typoscript
# Page TSConfig
RTE.default.showButtons = *
RTE.default.hideButtons =
```

### Check Loaded Configuration

Browser console:
```javascript
// Check if plugin loaded
console.log(CKEDITOR.instances);

// Inspect editor config
const editor = Object.values(CKEDITOR.instances)[0];
console.log(editor.config);
```

### Verify Image Processing

```bash
# Test image processing
./vendor/bin/typo3 backend:test:imageprocessing

# Check processed files
ls -la fileadmin/_processed_/
```

### Check File References

```sql
-- Find images in RTE content
SELECT uid, bodytext
FROM tt_content
WHERE bodytext LIKE '%data-htmlarea-file-uid%';
```

### Monitor Network Requests

1. Open browser DevTools
2. Go to Network tab
3. Trigger image selection
4. Check for failed requests to:
   - `/rte/wizard/selectimage`
   - Backend image info API

---

## Getting Help

If issues persist after troubleshooting:

1. **Check GitHub Issues**: https://github.com/netresearch/t3x-rte_ckeditor_image/issues
2. **Review Changelog**: Look for breaking changes in [CHANGELOG.md](../../CHANGELOG.md)
3. **TYPO3 Slack**: Join #typo3-cms channel
4. **Stack Overflow**: Tag questions with `typo3` and `ckeditor`

When reporting issues, include:
- TYPO3 version
- Extension version
- PHP version
- Browser console errors
- RTE configuration (sanitized)
- Steps to reproduce

---

## Related Documentation

- [Configuration Guide](../Integration/Configuration.md)
- [Debugging Guide](Debugging.md)
- [Known Issues](Known-Issues.md)
- [Architecture Overview](../Architecture/Overview.md)
