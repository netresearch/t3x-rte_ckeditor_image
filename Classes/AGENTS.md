<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-02-13 -->

# AGENTS.md -- Classes

## Overview

PHP source code for the TYPO3 CKEditor image extension. PSR-4 autoloaded under `Netresearch\RteCKEditorImage\`.
All files use `declare(strict_types=1)`. PHPStan level 10 with strict-rules.

## Architecture

Three-layer frontend rendering pipeline:

```
TypoScript parseFunc_RTE
    |
    v
ImageRenderingAdapter (Controller)     <-- TypoScript entry points
    |                                       renderImageAttributes() -> standalone <img>
    |                                       renderInlineLink()      -> <a> (resolve t3://, validate protocol)
    |                                       renderFigure()          -> <figure> with caption
    v
ImageResolverService (Service)         <-- Business logic + security
    |                                       File resolution via ResourceFactory
    |                                       Security validation (visibility, protocols, XSS)
    |                                       Quality multipliers (none/low/standard/retina/ultra/print)
    |                                       Builds ImageRenderingDto
    v
ImageRenderingService (Service)        <-- Presentation layer
                                            Template selection via match(true):
                                              Popup > Link > Caption > Standalone
                                            Fluid rendering via ViewFactoryInterface
```

Backend save pipeline (DataHandler hook):

```
RteImagesDbHook -> RteImageProcessor -> ImageTagParser + ImageFileResolver + ImageTagBuilder
                                        + ExternalImageFetcher + SecurityValidator
```

## Key Files

| File | Purpose |
|------|---------|
| `Controller/ImageRenderingAdapter.php` | TypoScript adapter: `renderImageAttributes()`, `renderInlineLink()`, `renderFigure()` entry points |
| `Controller/SelectImageController.php` | Backend AJAX controller for image select wizard and file browser |
| `Service/ImageResolverService.php` | Core business logic: file resolution, security validation, quality, DTO building |
| `Service/ImageRenderingService.php` | Presentation: template selection (`selectTemplate()`), Fluid rendering, whitespace normalization |
| `Service/ImageAttributeParser.php` | Pure HTML parser using DOMDocument -- extracts attributes from img/figure/link HTML |
| `Domain/Model/ImageRenderingDto.php` | Immutable `final readonly class` -- type-safe data contract for rendering |
| `Domain/Model/LinkDto.php` | Immutable DTO for link/popup configuration with `getUrlWithParams()` |
| `Database/RteImagesDbHook.php` | DataHandler hook: processes img tags on RTE field save |
| `Service/Processor/RteImageProcessor.php` | Orchestrates backend image processing (parse -> resolve -> build) |
| `Service/Processor/RteImageProcessorFactory.php` | Factory: reads extension config, creates RteImageProcessor |
| `Service/Security/SecurityValidator.php` | SSRF protection, MIME validation, path traversal checks |
| `Service/Fetcher/ExternalImageFetcher.php` | Downloads external images with security checks |
| `Service/Resolver/ImageFileResolver.php` | Resolves file UIDs to TYPO3 File objects |
| `Service/Builder/ImageTagBuilder.php` | Reconstructs `<img>` HTML tags from processed data |
| `Service/Parser/ImageTagParser.php` | Parses `<img>` tags from RTE HTML via HtmlParser |
| `Service/Environment/Typo3EnvironmentInfo.php` | Wraps TYPO3 statics for testability |
| `Backend/Preview/RteImagePreviewRenderer.php` | Backend content element preview with image rendering |
| `DataHandling/SoftReference/RteImageSoftReferenceParser.php` | Soft reference parser for `data-htmlarea-file-uid` attributes |
| `Listener/TCA/RteSoftrefEnforcer.php` | Event listener: auto-enforces RTE softref config on TCA fields |
| `Utils/ProcessedFilesHandler.php` | Wrapper around TYPO3 ImageService for processed file creation |

## Golden Samples (follow these patterns)

| Pattern | Reference |
|---------|-----------|
| Service with DI + security validation | `Service/ImageResolverService.php` |
| Immutable readonly DTO | `Domain/Model/ImageRenderingDto.php` |
| Interface + implementation + factory | `Service/Processor/RteImageProcessor*.php` |
| TypoScript adapter bridging to services | `Controller/ImageRenderingAdapter.php` |

## Directory Structure

```
Classes/
  Backend/Preview/          -- Backend content element preview
  Controller/               -- TypoScript adapter + backend AJAX controller
  DataHandling/SoftReference/ -- Soft reference parser for FAL relations
  Database/                 -- DataHandler hook for RTE field processing
  Domain/Model/             -- Immutable DTOs (ImageRenderingDto, LinkDto)
  Listener/TCA/             -- Event listener for TCA configuration
  Service/
    Builder/                -- HTML tag reconstruction (ImageTagBuilder)
    Environment/            -- TYPO3 environment abstraction
    Fetcher/                -- External image download with security
    Parser/                 -- HTML parsing for img tags
    Processor/              -- Backend image processing orchestration
    Resolver/               -- File UID to File object resolution
    Security/               -- SSRF/MIME/path traversal validation
    ImageAttributeParser    -- DOMDocument-based HTML attribute extraction
    ImageRenderingService   -- Fluid template rendering
    ImageResolverService    -- Core business logic + security
  Utils/                    -- ProcessedFilesHandler utility
```

## Code Style & Conventions

- **PSR-12** + TYPO3 CGL (Coding Guidelines)
- `declare(strict_types=1)` in every PHP file
- Namespace: `Netresearch\RteCKEditorImage\` (PSR-4 from Classes/)
- Constructor promotion with `readonly` for immutable services
- Dependency injection via `Configuration/Services.yaml` -- never `GeneralUtility::makeInstance()`
- Use `is_string()` / `is_array()` type narrowing, not `(string)` casts on `mixed` (PHPStan level 10)
- `array<string, mixed>` array access returns `mixed` -- always narrow before string ops
- Interface + implementation pattern for all backend services (testability)
- Factory pattern for runtime configuration (`RteImageProcessorFactory`)

### Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Namespace | `Netresearch\RteCKEditorImage\` | `Service\ImageResolverService` |
| DTO | `*Dto` (final readonly) | `ImageRenderingDto` |
| Interface | `*Interface` | `SecurityValidatorInterface` |
| Factory | `*Factory` | `RteImageProcessorFactory` |
| Event Listener | Located in `Listener/` | `RteSoftrefEnforcer` |
| Test | `*Test` mirroring source path | `Service/ImageResolverServiceTest` |

## Security Notes

When modifying Classes/ code:

- **Never** add `style` attribute to allowed HTML attributes (CSS injection)
- **Always** validate URLs against protocol allowlist before rendering
- **Always** sanitize captions with `htmlspecialchars()` before output
- **Always** check file visibility (`isPublic()`) before rendering in frontend
- **Always** use `SvgSanitizer` for SVG data URIs
- Security validation happens in `ImageResolverService` BEFORE DTO construction

## DI Configuration

All services configured in `Configuration/Services.yaml`:
- Autowire + autoconfigure enabled globally
- Controllers and services are `public: true` (for testing framework `$this->get()`)
- `RteImageProcessor` uses factory pattern (`autowire: false`, created by `RteImageProcessorFactory`)
- Interface aliases map to concrete implementations

## PR/Commit Checklist

- [ ] `composer ci:test:php:lint` passes
- [ ] `composer ci:test:php:cgl` passes (or run `composer ci:cgl` to auto-fix)
- [ ] `composer ci:test:php:phpstan` passes at level 10 (zero errors)
- [ ] `composer ci:test:php:unit` passes
- [ ] New code has unit tests with meaningful assertions
- [ ] Security-sensitive changes reviewed for XSS, SSRF, protocol injection
- [ ] `ext_emconf.php` version updated if releasing
- [ ] No deprecated TYPO3 APIs
