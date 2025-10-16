# Integration & Configuration

Complete configuration reference and integration guide for the RTE CKEditor Image extension.

## Overview

This section provides comprehensive configuration documentation covering all aspects of integrating and configuring the extension in your TYPO3 installation.

## Available Documentation

**[Configuration Guide](Configuration.md)**

Complete configuration reference covering:

### RTE Configuration (YAML)
- Editor configuration
- Toolbar button placement
- Image plugin options
- Style definitions
- Processing rules

### Page TSConfig
- Backend user permissions
- File browser configuration
- Maximum image dimensions
- Allowed file types
- Upload folder settings

### TypoScript Configuration
- Frontend rendering setup
- Image processing options
- Link rendering configuration
- Lazy loading settings
- Custom attributes

### Extension Configuration
- Global extension settings
- Security settings
- Performance options
- Feature toggles

## Configuration Quick Reference

### Essential Configurations

**Minimum Setup:**
```yaml
editor:
  config:
    toolbar:
      items:
        - typo3image
```

**Recommended Setup:**
```yaml
editor:
  config:
    toolbar:
      items:
        - typo3image
    typo3image:
      maxWidth: 1920
      maxHeight: 1080
```

**Full-Featured Setup:**
See [Configuration Guide](Configuration.md#complete-configuration-example)

## Configuration Patterns

### By Use Case
- **Basic editor** → [Minimal Configuration](Configuration.md#minimal-configuration)
- **Style-aware images** → [Custom Styles](Configuration.md#custom-image-styles)
- **Responsive images** → [Responsive Configuration](Configuration.md#responsive-images)
- **Restricted access** → [Permissions](Configuration.md#backend-permissions)

### By Component
- **CKEditor plugin** → [Plugin Configuration](Configuration.md#plugin-configuration)
- **File browser** → [Browser Configuration](Configuration.md#file-browser-configuration)
- **Frontend rendering** → [Rendering Configuration](Configuration.md#frontend-rendering-setup)
- **Image processing** → [Processing Configuration](Configuration.md#image-processing)

## Integration Guides

### Fresh Installation
1. Install extension via Composer
2. Configure RTE (YAML)
3. Set up TSConfig
4. Configure TypoScript
5. Clear caches

### Existing Installation
1. Review current RTE configuration
2. Merge typo3image configuration
3. Update user permissions
4. Test in staging environment
5. Deploy to production

### Migration from Other Solutions
- From native TYPO3 image handling
- From third-party extensions
- Configuration migration patterns

## Related Documentation

- [Examples](../Examples/Common-Use-Cases.md) - Practical configuration examples
- [API Documentation](../API/Index.md) - Backend integration
- [CKEditor Plugin](../CKEditor/Index.md) - Frontend plugin
- [Troubleshooting](../Troubleshooting/Common-Issues.md) - Configuration issues
