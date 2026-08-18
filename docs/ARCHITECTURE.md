# Architecture

<!-- Keep this document current. It's referenced from AGENTS.md and read by agents
     when they need to understand the system before making changes.
     Human-facing architecture docs live in Documentation/Architecture/ (rendered
     on docs.typo3.org); this file is the agent-facing map and points there. -->

## System Overview

TYPO3 CKEditor 5 extension adding FAL (File Abstraction Layer) image support to the rich text editor. Two independent pipelines: a frontend rendering pipeline (TypoScript `parseFunc_RTE` → adapter → services → Fluid templates) and a backend save pipeline (DataHandler hook → processor → parser/builder/resolver/fetcher services), plus a CKEditor 5 browser plugin.

## Component Map

| Component | Responsibility | Key Files |
|---|---|---|
| Rendering adapter | TypoScript entry points (`renderImageAttributes()`, `renderInlineLink()`, `renderFigure()`) | `Classes/Controller/ImageRenderingAdapter.php` |
| Resolver service | Business logic: file resolution, security validation, quality multipliers, builds DTO | `Classes/Service/ImageResolverService.php` |
| Rendering service | Presentation: template selection via `match(true)`, Fluid rendering | `Classes/Service/ImageRenderingService.php` |
| DTOs | Immutable data contracts between resolver and renderer | `Classes/Domain/Model/ImageRenderingDto.php`, `LinkDto.php` |
| Backend save hook | Processes `<img>` tags on RTE field save | `Classes/Database/RteImagesDbHook.php`, `Classes/Service/Processor/RteImageProcessor.php` |
| Security | SSRF/MIME/path-traversal validation, external image fetching | `Classes/Service/Security/SecurityValidator.php`, `Classes/Service/Fetcher/ExternalImageFetcher.php` |
| CKEditor 5 plugin | Image dialog, FAL integration, upcast/downcast converters | `Resources/Public/JavaScript/Plugins/typo3image.js` |
| Fluid templates | 6 rendering contexts (Standalone/Caption/Link/Popup combinations) | `Resources/Private/Templates/Image/`, `Resources/Private/Partials/Image/` |

## Dependency Rules

Enforced by phpat in `Tests/Architecture/ArchitectureTest.php` (part of the unit suite):

- `Domain\Model` DTOs MUST be `final` and `readonly`
- `Service\*` MUST NOT depend on `Controller\*` or `Backend\*`
- `Domain\*` MUST NOT depend on `Controller`, `Backend`, `Database`, `DataHandling`, or `Service`
- `Database\*` and `DataHandling\*` MUST NOT depend on `Controller\*`

## Data Flow

- **Frontend render**: `parseFunc_RTE` → `ImageRenderingAdapter` → `ImageResolverService` (resolve + validate + build `ImageRenderingDto`) → `ImageRenderingService` (template selection: Popup > Link > Caption > Standalone) → Fluid
- **Backend save**: `RteImagesDbHook` → `RteImageProcessor` → `ImageTagParser` + `ImageFileResolver` + `ImageTagBuilder` (+ `ExternalImageFetcher`/`SecurityValidator` for external images)

## Key Decisions

Detailed ADRs live in `Documentation/Architecture/` (rendered on docs.typo3.org):

| Decision | Record |
|---|---|
| Image scaling strategy | `Documentation/Architecture/ADR-001-Image-Scaling.rst` |
| CKEditor plugin integration | `Documentation/Architecture/ADR-002-CKEditor-Integration.rst` |
| Security responsibility split (Core vs. extension) | `Documentation/Architecture/ADR-003-Security-Responsibility-Boundaries.rst` |
| Overall system design | `Documentation/Architecture/System-Architecture.rst` |
