# Architecture & Design

System architecture, component design, and technical implementation details.

## Overview

This section explains the architectural decisions, design patterns, and component interactions in the RTE CKEditor Image extension.

## Available Documentation

**[Architecture Overview](Overview.md)**

Comprehensive system architecture documentation:
- Component diagram and interactions
- Data flow from backend to frontend
- CKEditor plugin architecture
- TYPO3 integration patterns
- Database schema and soft references
- Event-driven architecture
- Security considerations

## Key Concepts

### Three-Layer Architecture

1. **CKEditor Plugin Layer** (JavaScript)
   - Custom typo3image plugin
   - Model element definition
   - UI components and commands
   - Upcast/downcast conversions

2. **TYPO3 Backend Layer** (PHP)
   - Controllers for image selection and rendering
   - Database hooks for content processing
   - FAL integration
   - Event listeners

3. **Frontend Rendering Layer** (PHP/HTML)
   - TypoScript configuration
   - Image processing and optimization
   - HTML generation

### Design Patterns

- **MVC Pattern** - Controllers, models, and views separation
- **Event-Driven** - PSR-14 events for extensibility
- **Plugin Architecture** - Modular CKEditor plugin
- **Soft References** - TYPO3 reference tracking
- **Command Pattern** - CKEditor commands for actions

## Related Documentation

- [API Documentation](../API/Index.md) - PHP class reference
- [CKEditor Plugin](../CKEditor/Plugin-Development.md) - Plugin implementation
- [Configuration](../Integration/Configuration.md) - System configuration
