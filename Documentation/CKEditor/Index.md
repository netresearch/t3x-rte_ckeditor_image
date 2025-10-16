# CKEditor Plugin Development

Complete documentation for the CKEditor 5 plugin implementation.

## Overview

The typo3image plugin is a custom CKEditor 5 plugin that integrates TYPO3's File Abstraction Layer (FAL) with the rich text editor, enabling seamless image management within the CKEditor interface.

## Available Documentation

### Plugin Development

**[Plugin Development Guide](Plugin-Development.md)**

Complete plugin architecture and implementation:
- Plugin structure and initialization
- UI components (button, toolbar, dialog)
- Commands and command execution
- Integration with CKEditor ecosystem
- Event handling and lifecycle

### Model Element

**[Model Element Reference](Model-Element.md)**

The typo3image custom model element:
- Element schema and attributes
- Model manipulation methods
- Attribute handling and validation
- Integration with CKEditor model
- Differences from standard `imageBlock`

### Style Integration

**[Style Integration Guide](Style-Integration.md)**

Critical for v13.0.0+ - Style system integration:
- StyleUtils and GeneralHtmlSupport dependencies
- Style application to typo3image elements
- Configuration and customization
- Troubleshooting style issues
- Bug fixes in v13.0.0

### Conversions

**[Conversion System Documentation](Conversions.md)**

Upcast and downcast conversion patterns:
- HTML → Model (upcast) conversions
- Model → HTML (downcast) conversions
- Attribute conversion patterns
- Custom conversion implementations
- Debugging conversion issues

## Quick Links

### Getting Started
1. Read [Plugin Development](Plugin-Development.md) for overall structure
2. Understand [Model Element](Model-Element.md) for data handling
3. Study [Conversions](Conversions.md) for HTML transformation

### Common Tasks
- **Add custom attributes** → See [Model Element: Custom Attributes](Model-Element.md#custom-attributes)
- **Implement custom styles** → See [Style Integration](Style-Integration.md)
- **Debug conversion issues** → See [Conversions: Debugging](Conversions.md#debugging)
- **Extend plugin features** → See [Plugin Development: Extending](Plugin-Development.md#extending)

## Critical Information

### Version 13.0.0+ Requirements

The plugin **requires** these CKEditor dependencies:

```javascript
static get requires() {
    return ['StyleUtils', 'GeneralHtmlSupport'];
}
```

Missing either dependency will disable style functionality. See [Style Integration](Style-Integration.md) for details.

## Related Documentation

- [API Documentation](../API/Index.md) - PHP backend integration
- [Configuration](../Integration/Configuration.md) - Plugin configuration
- [Examples](../Examples/Common-Use-Cases.md) - Practical implementation examples
