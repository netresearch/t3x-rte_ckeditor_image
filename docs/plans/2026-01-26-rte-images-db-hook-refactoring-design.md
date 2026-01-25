# RteImagesDbHook Refactoring Design

## Problem Statement

The `RteImagesDbHook::modifyRteField()` method is a 295-line "God method" that mixes:
- Pure logic (URL parsing, attribute extraction)
- TYPO3 static calls (`GeneralUtility::getIndpEnv()`, `Environment::getPublicPath()`)
- I/O operations (file fetching, image processing)

This makes it nearly impossible to unit test, resulting in only 56% code coverage.

## Solution: Full Decomposition

Break `modifyRteField()` into specialized services with single responsibilities, wrapping TYPO3 statics behind injectable interfaces.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     RteImagesDbHook                              │
│                  (DataHandler hook - thin orchestrator)          │
│                                                                  │
│  processDatamap_postProcessFieldArray()                          │
│       │                                                          │
│       ▼                                                          │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              RteImageProcessor (NEW)                     │    │
│  │         Main orchestrator for image processing           │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
                              │
          ┌───────────────────┼───────────────────┐
          ▼                   ▼                   ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│ ImageTagParser   │ │ ImageFileResolver│ │ ImageTagBuilder  │
│                  │ │                  │ │                  │
│ • Parse HTML     │ │ • Resolve file   │ │ • Build img tag  │
│ • Extract attrs  │ │ • Process magic  │ │ • Update attrs   │
│ • Find img tags  │ │ • Fetch external │ │ • Rebuild HTML   │
└──────────────────┘ └──────────────────┘ └──────────────────┘
                              │
          ┌───────────────────┼───────────────────┐
          ▼                   ▼                   ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│ EnvironmentInfo  │ │ SecurityValidator│ │ ExternalFetcher  │
│ (Interface)      │ │                  │ │                  │
│ • getSiteUrl()   │ │ • validateUrl()  │ │ • fetch()        │
│ • getPublicPath()│ │ • validatePath() │ │ • validateMime() │
│ • isBackend()    │ │ • validateMime() │ │                  │
└──────────────────┘ └──────────────────┘ └──────────────────┘
```

## New Services

### 1. EnvironmentInfoInterface

Wraps TYPO3 static calls behind an injectable interface:

```php
namespace Netresearch\RteCKEditorImage\Service\Environment;

interface EnvironmentInfoInterface
{
    public function getSiteUrl(): string;
    public function getRequestHost(): string;
    public function getPublicPath(): string;
    public function isBackendRequest(): bool;
}
```

Production implementation calls TYPO3 statics. Test implementation returns configurable values.

### 2. SecurityValidator

Consolidates all security validation:

```php
namespace Netresearch\RteCKEditorImage\Service\Security;

final class SecurityValidator
{
    public function getValidatedIpForUrl(string $url): ?string;
    public function isAllowedImageMimeType(string $content): bool;
    public function validateLocalPath(string $path, string $publicPath): ?string;
    public function isAllowedExtension(string $extension): bool;
}
```

100% unit testable - pure functions, no external dependencies.

### 3. ImageTagParser

Handles HTML parsing and attribute extraction:

```php
namespace Netresearch\RteCKEditorImage\Service\Parser;

final class ImageTagParser
{
    public function splitByImageTags(string $html): array;
    public function extractAttributes(string $imgTag): array;
    public function getDimension(array $attributes, string $dimension): int;
    public function normalizeImageSrc(string $src, string $siteUrl, string $sitePath): string;
}
```

100% unit testable - HtmlParser has no side effects.

### 4. ExternalImageFetcher

SSRF-safe external image fetching:

```php
namespace Netresearch\RteCKEditorImage\Service\Fetcher;

final class ExternalImageFetcher
{
    public function fetch(string $url): ?array; // Returns {content, extension} or null
}
```

90% unit testable with mocked RequestFactory.

### 5. ImageFileResolver

Resolves file references:

```php
namespace Netresearch\RteCKEditorImage\Service\Resolver;

final class ImageFileResolver
{
    public function resolveByUid(int $uid): ?File;
    public function resolveLocalFile(string $relativePath): ?File;
    public function isExternalUrl(string $url, string $siteUrl): bool;
}
```

80% unit testable with mocked ResourceFactory.

### 6. ImageTagBuilder

Reconstructs img tags:

```php
namespace Netresearch\RteCKEditorImage\Service\Builder;

final class ImageTagBuilder
{
    public function build(array $attributes): string;
    public function withProcessedImage(array $attributes, int $width, int $height, string $src, ?int $fileUid = null): array;
    public function makeRelativeSrc(string $src, string $siteUrl): string;
}
```

95% unit testable.

### 7. RteImageProcessor

Main orchestrator (~150 lines):

```php
namespace Netresearch\RteCKEditorImage\Service;

final class RteImageProcessor
{
    public function process(string $content): string;
}
```

95% unit testable with injected service mocks.

### 8. Refactored RteImagesDbHook

Thin wrapper (~50 lines):

```php
namespace Netresearch\RteCKEditorImage\Database;

final class RteImagesDbHook
{
    public function processDatamap_postProcessFieldArray(...): void;
    private function isRteField(...): bool;
}
```

100% unit testable.

## File Structure

```
Classes/
├── Database/
│   └── RteImagesDbHook.php              # Thin hook (~50 lines)
└── Service/
    ├── RteImageProcessor.php            # Main orchestrator (~150 lines)
    ├── Builder/
    │   └── ImageTagBuilder.php          # Tag construction (~60 lines)
    ├── Environment/
    │   ├── EnvironmentInfoInterface.php # Interface (~20 lines)
    │   └── Typo3EnvironmentInfo.php     # Production impl (~40 lines)
    ├── Fetcher/
    │   └── ExternalImageFetcher.php     # HTTP fetching (~80 lines)
    ├── Parser/
    │   └── ImageTagParser.php           # HTML parsing (~80 lines)
    ├── Resolver/
    │   └── ImageFileResolver.php        # File resolution (~100 lines)
    └── Security/
        └── SecurityValidator.php        # All validation (~100 lines)

Tests/Unit/Service/
├── RteImageProcessorTest.php
├── Builder/ImageTagBuilderTest.php
├── Environment/Typo3EnvironmentInfoTest.php
├── Fetcher/ExternalImageFetcherTest.php
├── Parser/ImageTagParserTest.php
├── Resolver/ImageFileResolverTest.php
└── Security/SecurityValidatorTest.php
```

## Testability Summary

| Service | Lines | Dependencies | Testability |
|---------|-------|--------------|-------------|
| SecurityValidator | ~100 | None | 100% unit |
| ImageTagParser | ~80 | HtmlParser (safe) | 100% unit |
| ImageTagBuilder | ~60 | GeneralUtility (one call) | 95% unit |
| EnvironmentInfoInterface | ~20 | Interface only | Mock in tests |
| Typo3EnvironmentInfo | ~40 | TYPO3 statics | Functional only |
| ExternalImageFetcher | ~80 | RequestFactory, SecurityValidator | 90% unit (mock HTTP) |
| ImageFileResolver | ~100 | ResourceFactory, Environment | 80% unit |
| RteImageProcessor | ~150 | All services | 95% unit (inject mocks) |
| RteImagesDbHook | ~50 | RteImageProcessor | 100% unit |

**Expected coverage gain: +30-35%** (from 56% to ~85-90%)

## Services.yaml Configuration

```yaml
services:
  Netresearch\RteCKEditorImage\Service\Environment\EnvironmentInfoInterface:
    class: Netresearch\RteCKEditorImage\Service\Environment\Typo3EnvironmentInfo

  Netresearch\RteCKEditorImage\Service\Security\SecurityValidator: ~

  Netresearch\RteCKEditorImage\Service\Parser\ImageTagParser: ~

  Netresearch\RteCKEditorImage\Service\Builder\ImageTagBuilder: ~

  Netresearch\RteCKEditorImage\Service\Fetcher\ExternalImageFetcher:
    arguments:
      $logger: '@Psr\Log\LoggerInterface'

  Netresearch\RteCKEditorImage\Service\Resolver\ImageFileResolver: ~

  Netresearch\RteCKEditorImage\Service\RteImageProcessor:
    arguments:
      $fetchExternalImages: '%rte_ckeditor_image.fetchExternalImages%'

  Netresearch\RteCKEditorImage\Database\RteImagesDbHook: ~
```

## Benefits

1. **Single Responsibility Principle** - each class does one thing well
2. **Dependency Inversion** - depend on abstractions, not TYPO3 statics
3. **Testability by Design** - pure functions separated from I/O
4. **Maintainability** - smaller, focused classes are easier to understand
5. **Reusability** - services can be used elsewhere (e.g., SecurityValidator)

## Implementation Steps

1. Create `EnvironmentInfoInterface` and `Typo3EnvironmentInfo`
2. Extract `SecurityValidator` with existing tests
3. Extract `ImageTagParser` with tests
4. Extract `ImageTagBuilder` with tests
5. Extract `ExternalImageFetcher` with tests
6. Extract `ImageFileResolver` with tests
7. Create `RteImageProcessor` orchestrator with tests
8. Refactor `RteImagesDbHook` to use `RteImageProcessor`
9. Update `Services.yaml`
10. Run full test suite and verify coverage increase
