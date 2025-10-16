# RTE CKEditor Image

Image support in CKEditor for the TYPO3 ecosystem.

## Introduction

The RTE CKEditor Image extension provides comprehensive image handling capabilities for TYPO3's CKEditor Rich Text Editor. This extension enables editors to insert, configure, and style images directly within the CKEditor interface, with full integration into TYPO3's File Abstraction Layer (FAL).

**Key Features:**
- Native CKEditor 5 plugin integration
- Full TYPO3 FAL support with file browser integration
- Advanced image processing (magic images, cropping, scaling)
- Custom image style configuration
- Responsive image support
- Lazy loading and performance optimization
- Event-driven architecture for extensibility

**Version:** 13.0.x for TYPO3 13.4+
**License:** AGPL-3.0-or-later
**Repository:** [github.com/netresearch/t3x-rte_ckeditor_image](https://github.com/netresearch/t3x-rte_ckeditor_image)

## Table of Contents

```{toctree}
---
maxdepth: 2
caption: User Documentation
---
Installation <README>
Configuration <Integration/Configuration>
Examples <Examples/Common-Use-Cases>
Troubleshooting <Troubleshooting/Common-Issues>
```

```{toctree}
---
maxdepth: 2
caption: Architecture & Design
---
Architecture/Overview
```

```{toctree}
---
maxdepth: 2
caption: Developer Documentation
---
API/Controllers
API/DataHandling
API/EventListeners
CKEditor/Plugin-Development
CKEditor/Model-Element
CKEditor/Style-Integration
CKEditor/Conversions
```

## Quick Start

### Installation

Install via Composer:

```bash
composer require netresearch/rte-ckeditor-image
```

Activate the extension in the TYPO3 Extension Manager or via CLI:

```bash
vendor/bin/typo3 extension:activate rte_ckeditor_image
```

### Basic Configuration

Add the image button to your RTE configuration:

```yaml
editor:
  config:
    toolbar:
      items:
        - heading
        - '|'
        - typo3image
        - link
        - '|'
        - bold
        - italic
```

For complete configuration options, see [Configuration Guide](Integration/Configuration.md).

## Requirements

- **TYPO3:** 13.4 or later
- **PHP:** 8.2, 8.3, or 8.4
- **Extensions:** cms-rte-ckeditor (included in TYPO3 core)

### Critical Dependencies (v13.0.0+)

The CKEditor plugin requires these dependencies for style functionality:

```javascript
static get requires() {
    return ['StyleUtils', 'GeneralHtmlSupport'];
}
```

**Important:** Missing either plugin will disable the style dropdown for images. See [Style Integration](CKEditor/Style-Integration.md) for details.

## Quick Navigation by Role

| Role | Start Here | Then Read | Advanced |
|------|-----------|-----------|----------|
| **Integrator** | [Configuration Guide](Integration/Configuration.md) | [Examples](Examples/Common-Use-Cases.md) | [Troubleshooting](Troubleshooting/Common-Issues.md) |
| **PHP Developer** | [Architecture Overview](Architecture/Overview.md) | [API Reference](API/Controllers.md) | [../AGENTS.md](../AGENTS.md) + [../Classes/AGENTS.md](../Classes/AGENTS.md) |
| **JS Developer** | [CKEditor Plugin](CKEditor/Plugin-Development.md) | [Style Integration](CKEditor/Style-Integration.md) | [../Resources/AGENTS.md](../Resources/AGENTS.md) |
| **Contributor** | [../CONTRIBUTING.md](../CONTRIBUTING.md) | [../AGENTS.md](../AGENTS.md) | [Testing](../Tests/AGENTS.md) |

---

## Documentation Use Cases

### For Integrators

- **Add custom image styles** → [Configuration Guide](Integration/Configuration.md#custom-image-styles)
- **Configure image processing** → [Configuration Guide](Integration/Configuration.md#image-processing)
- **Set up frontend rendering** → [Configuration Guide](Integration/Configuration.md#frontend-rendering-setup)
- **Enable lazy loading** → [Examples: Lazy Loading](Examples/Common-Use-Cases.md#lazy-loading)

### For Developers

#### PHP Backend Development
- **Understand the architecture** → [Architecture Overview](Architecture/Overview.md)
- **Controller APIs** → [Controllers API](API/Controllers.md)
- **Customize image processing** → [Data Handling API](API/DataHandling.md)
- **Listen to extension events** → [Event Listeners](API/EventListeners.md)
- **Code standards & patterns** → [../Classes/AGENTS.md](../Classes/AGENTS.md)

#### JavaScript/CKEditor Development
- **Extend the CKEditor plugin** → [Plugin Development](CKEditor/Plugin-Development.md)
- **Style system integration** → [Style Integration](CKEditor/Style-Integration.md)
- **Custom model element** → [Model Element](CKEditor/Model-Element.md)
- **Conversion system** → [Conversions](CKEditor/Conversions.md)
- **Code standards & patterns** → [../Resources/AGENTS.md](../Resources/AGENTS.md)

### For Troubleshooters

- **Images not appearing** → [Frontend Rendering Issues](Troubleshooting/Common-Issues.md#frontend-rendering-issues)
- **Style dropdown disabled** → [Style Drop-down Not Working](Troubleshooting/Common-Issues.md#style-drop-down-not-working)
- **File browser not opening** → [File Browser Issues](Troubleshooting/Common-Issues.md)
- **Performance problems** → [Performance Optimization](Troubleshooting/Common-Issues.md)

## Support & Contributing

**Get Help:**
- [GitHub Issues](https://github.com/netresearch/t3x-rte_ckeditor_image/issues)
- [GitHub Discussions](https://github.com/netresearch/t3x-rte_ckeditor_image/discussions)
- [TYPO3 Slack](https://typo3.org/community/meet/chat-slack) - #ext-rte_ckeditor_image

**Contribute:**
- Report bugs or request features via [GitHub Issues](https://github.com/netresearch/t3x-rte_ckeditor_image/issues)
- Submit pull requests for code improvements
- Improve documentation via pull requests
- Follow [TYPO3 Contribution Guidelines](https://docs.typo3.org/m/typo3/guide-contributionworkflow/main/en-us/)

## License

This extension is licensed under [AGPL-3.0-or-later](https://www.gnu.org/licenses/agpl-3.0.html).

## Credits

**Development & Maintenance:**
- Netresearch - Gesellschaft für neue Netzwerke mbH
- Sebastian Koschel
- Sebastian Mendel
- Rico Sonntag

**Community Contributors:**
- See [GitHub Contributors](https://github.com/netresearch/t3x-rte_ckeditor_image/graphs/contributors)

## Additional Resources

- **TYPO3 Extension Repository:** [extensions.typo3.org/extension/rte_ckeditor_image](https://extensions.typo3.org/extension/rte_ckeditor_image)
- **Packagist:** [packagist.org/packages/netresearch/rte-ckeditor-image](https://packagist.org/packages/netresearch/rte-ckeditor-image)
- **TYPO3 CKEditor Documentation:** [docs.typo3.org - RTE CKEditor](https://docs.typo3.org/c/typo3/cms-rte-ckeditor/13.4/en-us/)
- **CKEditor 5 Documentation:** [ckeditor.com/docs/ckeditor5](https://ckeditor.com/docs/ckeditor5/latest/)
