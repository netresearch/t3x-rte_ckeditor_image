# Project Overview: rte_ckeditor_image

## Purpose
TYPO3 CMS extension providing CKEditor 5 integration with TYPO3's File Abstraction Layer (FAL) for image management in rich text editors. Adds native TYPO3 image browser to CKEditor with "magic images" support.

## Tech Stack
- **PHP**: 8.2-8.9 (strict types, PSR-12/PER-CS2.0)
- **TYPO3**: 13.4.x (CMS Core, Backend, Frontend, Extbase, RTE CKEditor)
- **JavaScript**: ES6 modules, CKEditor 5 (@typo3/ckeditor5-bundle.js)
- **Frontend**: jQuery, TYPO3 Modal system
- **License**: AGPL-3.0-or-later

## Key Features
- TYPO3 image browser integration in CKEditor
- Magic images (automatic image processing)
- Image properties dialog (width/height with aspect ratio, alt/title with override, zoom, CSS class)
- Support for images within links
- FAL (File Abstraction Layer) integration
- Lazyload support
- Style drop-down integration for images

## Extension Information
- **Extension Key**: `rte_ckeditor_image`
- **Namespace**: `Netresearch\RteCKEditorImage`
- **Version**: 13.0.0
- **Author**: Netresearch DTT GmbH
- **Repository**: https://github.com/netresearch/t3x-rte_ckeditor_image
- **Packagist**: netresearch/rte-ckeditor-image