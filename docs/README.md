# RTE CKEditor Image - Documentation

Comprehensive documentation for the TYPO3 CKEditor Image extension (v13.0.0+).

## 📚 Documentation Structure

### Architecture & Design
- **[Architecture Overview](Architecture/Overview.md)** - System design, component architecture, and data flow diagrams

### API Documentation
- **[Controllers](API/Controllers.md)** - SelectImageController, ImageRenderingController, and ImageLinkRenderingController APIs
- **[Event Listeners](API/EventListeners.md)** - PSR-14 event system, RteConfigurationListener, and configuration injection
- **[Data Handling](API/DataHandling.md)** - RteImagesDbHook, soft references, magic images, and database processing

### CKEditor Plugin
- **[Plugin Development](CKEditor/Plugin-Development.md)** - Complete plugin architecture, UI components, and integration patterns
- **[Model Element](CKEditor/Model-Element.md)** - typo3image model element schema, attributes, and manipulation
- **[Style Integration](CKEditor/Style-Integration.md)** - StyleUtils and GeneralHtmlSupport integration (critical for v13.0.0+)
- **[Conversions](CKEditor/Conversions.md)** - Upcast/downcast system for HTML ↔ Model transformations

### Integration & Configuration
- **[Configuration Guide](Integration/Configuration.md)** - Complete RTE, TSConfig, and TypoScript configuration reference

### Troubleshooting
- **[Common Issues](Troubleshooting/Common-Issues.md)** - FAQ, solutions, and debugging techniques for frequent problems

### Examples
- **[Common Use Cases](Examples/Common-Use-Cases.md)** - 10+ practical examples: responsive images, lightbox, lazy loading, custom styles, testing

## 🚀 Quick Start

For basic installation and usage, see the main [README.md](../README.md) in the project root.

**Essential Reading for Setup**:
1. [Configuration Guide](Integration/Configuration.md) - All configuration options
2. [Architecture Overview](Architecture/Overview.md) - Understand the system
3. [Common Issues](Troubleshooting/Common-Issues.md) - Troubleshoot problems

**Essential Reading for Development**:
1. [Plugin Development](CKEditor/Plugin-Development.md) - CKEditor plugin structure
2. [API Documentation](API/Controllers.md) - Backend controllers
3. [Model Element](CKEditor/Model-Element.md) - Custom model element

## ⚠️ Critical Information

### Version 13.0.0+ Requirements

**MANDATORY Dependencies** (see [Style Integration](CKEditor/Style-Integration.md)):
```javascript
static get requires() {
    return ['StyleUtils', 'GeneralHtmlSupport'];
}
```

Both plugins are **required** for style functionality. Missing either causes style drop-down to be disabled for images.

### Recent Bug Fixes

- **v13.0.0**: Fixed style integration with `GeneralHtmlSupport` dependency
- **v13.0.0**: Improved style application to typo3image elements
- See [Common Issues](Troubleshooting/Common-Issues.md) for migration guidance

## 🎯 Documentation Use Cases

### "I want to add custom image styles"
→ Read [Configuration Guide](Integration/Configuration.md#custom-image-styles)
→ See examples in [Common Use Cases](Examples/Common-Use-Cases.md#custom-image-styles)

### "Images not appearing in frontend"
→ Check [Common Issues](Troubleshooting/Common-Issues.md#frontend-rendering-issues)
→ Verify [Configuration Guide](Integration/Configuration.md#frontend-rendering-setup)

### "Style drop-down disabled for images"
→ Read [Common Issues](Troubleshooting/Common-Issues.md#style-drop-down-not-working)
→ Understand [Style Integration](CKEditor/Style-Integration.md#the-style-system-problem)

### "Need to customize image processing"
→ Study [Data Handling API](API/DataHandling.md#image-processing-methods)
→ See [Common Use Cases](Examples/Common-Use-Cases.md#custom-backend-processing)

### "Developing custom CKEditor plugin features"
→ Start with [Plugin Development](CKEditor/Plugin-Development.md)
→ Understand [Model Element](CKEditor/Model-Element.md) and [Conversions](CKEditor/Conversions.md)

## 🔧 Development Guide

### Contributing to Extension

**Step 1**: Understand the architecture
- [Architecture Overview](Architecture/Overview.md) - System design
- [API Documentation](API/Controllers.md) - Backend components

**Step 2**: Learn the CKEditor integration
- [Plugin Development](CKEditor/Plugin-Development.md) - Plugin structure
- [Model Element](CKEditor/Model-Element.md) - Custom model
- [Style Integration](CKEditor/Style-Integration.md) - Style system

**Step 3**: Follow development patterns
- [Data Handling](API/DataHandling.md) - Backend processing patterns
- [Event Listeners](API/EventListeners.md) - Event-driven architecture

### Testing

See [Common Use Cases](Examples/Common-Use-Cases.md#testing-examples) for:
- Functional test examples
- Unit test examples
- Test execution commands

## 📖 Additional Resources

- **GitHub Repository**: https://github.com/netresearch/t3x-rte_ckeditor_image
- **TYPO3 Extension Repository**: https://extensions.typo3.org/extension/rte_ckeditor_image
- **Packagist**: https://packagist.org/packages/netresearch/rte-ckeditor-image
- **Issue Tracker**: https://github.com/netresearch/t3x-rte_ckeditor_image/issues
- **TYPO3 Documentation**: https://docs.typo3.org/

## 📝 Documentation Conventions

- **Code examples**: PHP 8.2+ features, strict types
- **Configuration examples**: TYPO3 13.4+ compatible
- **File paths**: Use `EXT:extension_key` notation
- **Class names**: Fully-qualified namespaces (FQN)
- **Code style**: PSR-12 / PER-CS 2.0 compliant

## 🗂️ Documentation Coverage

This documentation covers:
- ✅ Complete API reference for all PHP classes
- ✅ Full CKEditor plugin documentation
- ✅ Configuration options (RTE, TSConfig, TypoScript)
- ✅ 10+ practical use case examples
- ✅ Troubleshooting guide with solutions
- ✅ Architecture and design patterns
- ✅ Event system and hooks
- ✅ Model/view/conversion system

## 🤝 Contributing to Documentation

Found an error or want to improve the documentation?

1. Check existing issues: https://github.com/netresearch/t3x-rte_ckeditor_image/issues
2. Submit corrections or improvements via pull request
3. Follow documentation conventions above
4. Update cross-references when adding new sections

## 📚 Documentation History

- **v13.0.0**: Major documentation update
  - Added detailed API reference
  - Comprehensive CKEditor plugin documentation
  - Style integration bug fix documentation
  - 10+ practical examples
  - Complete troubleshooting guide
