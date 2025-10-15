# Architecture Overview

## System Design

The rte_ckeditor_image extension follows TYPO3's modern extension architecture with CKEditor 5 integration, providing seamless FAL (File Abstraction Layer) image management within rich text editors.

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    TYPO3 Backend                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │   CKEditor   │  │    Image     │  │     FAL      │ │
│  │   Plugin     │─▶│  Controller  │─▶│   Storage    │ │
│  └──────────────┘  └──────────────┘  └──────────────┘ │
│         │                 │                             │
│         ▼                 ▼                             │
│  ┌──────────────┐  ┌──────────────┐                   │
│  │  JavaScript  │  │  Backend     │                    │
│  │   Dialog     │  │    Route     │                    │
│  └──────────────┘  └──────────────┘                   │
└─────────────────────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│                  Content Storage                        │
│  ┌──────────────────────────────────────────────────┐  │
│  │  RTE Content with data-htmlarea-* attributes    │  │
│  └──────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│                  Frontend Rendering                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐ │
│  │  TypoScript  │─▶│   Image      │─▶│   Rendered   │ │
│  │    Hooks     │  │  Rendering   │  │     HTML     │ │
│  └──────────────┘  └──────────────┘  └──────────────┘ │
└─────────────────────────────────────────────────────────┘
```

## Core Components

### Backend Layer

#### 1. Controllers (`Classes/Controller/`)
- **SelectImageController**: Handles image selection and processing
- **ImageRenderingController**: Frontend image rendering
- **ImageLinkRenderingController**: Renders images within links

#### 2. Event Listeners (`Classes/EventListener/`)
- **RteConfigurationListener**: Customizes RTE configuration before initialization

#### 3. Database Hooks (`Classes/Database/`)
- **RteImagesDbHook**: TCEmain data processing for image references

#### 4. Data Handling (`Classes/DataHandling/SoftReference/`)
- **RteImageSoftReferenceParser**: Tracks soft references for link management

### Frontend Layer (CKEditor Plugin)

#### JavaScript Module (`Resources/Public/JavaScript/Plugins/typo3image.js`)
- **Typo3Image Plugin**: CKEditor 5 plugin class
- **Custom Model**: `typo3image` element with rich attributes
- **UI Components**: Image dialog, selection modal
- **Style Integration**: StyleUtils and GeneralHtmlSupport integration
- **Conversion System**: Upcast (HTML → Model) and Downcast (Model → HTML)

### Configuration Layer

#### YAML Configuration
- **Services.yaml**: Dependency injection container configuration
- **Plugin.yaml**: RTE plugin registration

#### TypoScript
- **setup.typoscript**: Frontend rendering configuration
- **page.tsconfig**: Backend RTE configuration

#### Backend Routes
- **Routes.php**: Backend route definitions for image selection

## Design Patterns

### Dependency Injection
All PHP classes use Symfony's dependency injection:
```php
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false
```

### Event-Driven Architecture
TYPO3 event system for loose coupling:
- `AfterPrepareConfigurationForEditorEvent` - RTE configuration
- TCEmain hooks for data processing

### MVC Pattern
Controllers handle requests, models represent data, views render output:
- Controllers process backend requests
- FAL models represent files
- TypoScript views render frontend HTML

### Plugin Pattern
CKEditor 5 plugin system:
- Custom `typo3image` model element
- Editor commands and UI components
- Conversion system for data transformation

## Integration Points

### TYPO3 Core Integration
1. **RTE CKEditor**: Extends TYPO3's CKEditor integration
2. **FAL**: Uses File Abstraction Layer for file management
3. **TCEmain**: Hooks into data processing pipeline
4. **Soft References**: Tracks file references for integrity

### CKEditor Integration
1. **Plugin Registration**: Via JavaScriptModules.php and Plugin.yaml
2. **Custom Model**: `typo3image` element with TYPO3-specific attributes
3. **Style System**: Integration with CKEditor's style drop-down
4. **Conversion**: Bidirectional HTML ↔ Model conversion

## Data Flow

### Image Selection Flow
```
User clicks insert image
    ↓
CKEditor plugin opens modal
    ↓
Backend route loads file browser
    ↓
User selects image
    ↓
JavaScript receives file UID
    ↓
Backend API returns image info
    ↓
Dialog opens with image properties
    ↓
User confirms settings
    ↓
typo3image model element created
    ↓
Content saved to database
```

### Frontend Rendering Flow
```
RTE content loaded from database
    ↓
lib.parseFunc_RTE processes content
    ↓
ImageRenderingController hook invoked
    ↓
FAL file loaded from UID
    ↓
Magic image processing applied
    ↓
Processed image URL generated
    ↓
HTML with processed URL rendered
    ↓
Internal data-* attributes removed
```

## Technology Stack

- **PHP**: 8.2-8.9 with strict types
- **TYPO3**: 13.4.x (Core, Backend, Frontend, Extbase, RTE CKEditor)
- **JavaScript**: ES6 modules
- **CKEditor**: 5.x with @typo3/ckeditor5-bundle.js
- **Dependency Injection**: Symfony service container
- **Standards**: PSR-12, PER-CS2.0

## Security Considerations

- File access through FAL security layer
- Backend routes require authentication
- Input validation on all user data
- XSS prevention through proper encoding
- Data attribute sanitization on frontend

## Performance Considerations

- Processed images cached by TYPO3
- Lazy loading support for frontend
- Minimal JavaScript footprint
- Efficient database queries with soft references

## Extension Points

Developers can extend the extension through:
1. Event listeners (PSR-14 events)
2. TypoScript configuration
3. XClasses (not recommended)
4. Custom processing hooks
5. Additional CKEditor plugins

## Related Documentation

- [Component Details](Components.md) - Detailed component breakdown
- [Data Flow](DataFlow.md) - Complete request/response flows
- [CKEditor Integration](CKEditor-Integration.md) - Editor integration details
