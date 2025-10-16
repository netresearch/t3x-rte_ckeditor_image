# API Documentation

Complete API reference for all PHP classes in the RTE CKEditor Image extension.

## Overview

This section provides detailed documentation for the PHP backend components that power the extension's functionality.

## Available Documentation

### Controllers

**[Controllers API Reference](Controllers.md)**

Frontend and backend controllers for image handling:
- `SelectImageController` - Backend image selection and preview
- `ImageRenderingController` - Frontend image rendering
- `ImageLinkRenderingController` - Linked image rendering

### Data Handling

**[Data Handling API Reference](DataHandling.md)**

Database hooks and image processing:
- `RteImagesDbHook` - Content processing and magic images
- Soft reference handling
- External image fetching
- Image transformation and storage

### Event Listeners

**[Event Listeners Reference](EventListeners.md)**

PSR-14 event system integration:
- `RteConfigurationListener` - RTE configuration injection
- Event-driven architecture patterns
- Custom event handling

## Usage Examples

See [Common Use Cases](../Examples/Common-Use-Cases.md) for practical implementation examples of these APIs.

## Related Documentation

- [Architecture Overview](../Architecture/Overview.md) - Understand how components interact
- [CKEditor Plugin](../CKEditor/Plugin-Development.md) - Frontend JavaScript components
- [Configuration Guide](../Integration/Configuration.md) - Configure PHP components
