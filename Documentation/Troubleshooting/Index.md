# Troubleshooting & Support

Solutions to common issues, debugging techniques, and support resources.

## Overview

This section provides comprehensive troubleshooting guidance for common issues encountered when using the RTE CKEditor Image extension.

## Available Documentation

**[Common Issues](Common-Issues.md)**

Complete troubleshooting guide covering:

### Installation & Setup Issues
- Extension installation problems
- Dependency conflicts
- Cache-related issues
- Permission problems

### Editor Issues
- Image button not appearing
- File browser not opening
- Style dropdown disabled (critical for v13.0.0+)
- Upload failures
- Preview not working

### Frontend Rendering Issues
- Images not displaying
- Broken image links
- Incorrect dimensions
- Missing styles
- Link rendering problems

### Performance Issues
- Slow image loading
- Large file handling
- Processing timeouts
- Memory exhaustion

### Configuration Issues
- TSConfig not applying
- TypoScript conflicts
- RTE configuration errors
- Style configuration problems

## Quick Fixes

### Most Common Issues

**1. Style Dropdown Disabled (v13.0.0+)**
```javascript
// Ensure these dependencies are present:
static get requires() {
    return ['StyleUtils', 'GeneralHtmlSupport'];
}
```
→ See [Common Issues: Style Drop-down](Common-Issues.md#style-drop-down-not-working)

**2. Images Not Appearing in Frontend**
- Check TypoScript setup
- Verify file permissions
- Clear all caches
→ See [Common Issues: Frontend Rendering](Common-Issues.md#frontend-rendering-issues)

**3. File Browser Not Opening**
- Check backend user permissions
- Verify TSConfig
- Check file mount configuration
→ See [Common Issues: File Browser](Common-Issues.md#file-browser-issues)

## Debugging Techniques

### Enable Debug Mode
```php
$GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '*';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = 1;
```

### Browser Console
- Check for JavaScript errors
- Monitor network requests
- Inspect CKEditor plugin loading

### TYPO3 Logs
- Check `var/log/typo3_*.log`
- Review deprecation log
- Monitor PHP error log

### Database Queries
- Enable SQL debug mode
- Check soft references
- Verify file relations

## Getting Help

### Self-Help Resources
1. Check this troubleshooting guide
2. Review [Configuration Guide](../Integration/Configuration.md)
3. Consult [Common Use Cases](../Examples/Common-Use-Cases.md)
4. Search [GitHub Issues](https://github.com/netresearch/t3x-rte_ckeditor_image/issues)

### Community Support
- **GitHub Discussions:** [github.com/netresearch/t3x-rte_ckeditor_image/discussions](https://github.com/netresearch/t3x-rte_ckeditor_image/discussions)
- **TYPO3 Slack:** #ext-rte_ckeditor_image channel
- **TYPO3 Forum:** [https://typo3.org/community/meet/](https://typo3.org/community/meet/)

### Reporting Bugs

Before reporting, please:
1. Check if issue already exists
2. Verify you're using latest version
3. Test with minimal configuration
4. Collect debugging information

**Report bugs:** [github.com/netresearch/t3x-rte_ckeditor_image/issues](https://github.com/netresearch/t3x-rte_ckeditor_image/issues)

Include:
- TYPO3 version
- PHP version
- Extension version
- Steps to reproduce
- Error messages
- Browser console output

## Known Issues & Workarounds

See [Common Issues](Common-Issues.md) for detailed information on:
- v13.0.0 style integration changes
- Browser compatibility issues
- Performance considerations
- Edge cases and limitations

## Related Documentation

- [Configuration Guide](../Integration/Configuration.md) - Correct configuration
- [Examples](../Examples/Common-Use-Cases.md) - Working implementations
- [Architecture](../Architecture/Overview.md) - System design understanding
- [API Documentation](../API/Index.md) - Technical reference
