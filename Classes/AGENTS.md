<!-- Managed by agent: keep sections & order; edit content, not structure -->
<!-- Last updated: 2026-03-26 -->

# Classes/AGENTS.md

**Scope:** PHP backend source code
**Parent:** [../AGENTS.md](../AGENTS.md)

## Overview

```
Classes/
├── Backend/Preview/
│   └── RteImagePreviewRenderer.php     # Backend list module preview
├── Controller/
│   ├── ImageLinkRenderingController.php # Frontend: link-wrapped image rendering
│   ├── ImageRenderingController.php     # Frontend: standalone image rendering
│   └── SelectImageController.php        # Backend: image browser wizard + info API
├── DataHandling/SoftReference/
│   └── RteImageSoftReferenceParser.php  # Soft reference parsing for sys_refindex
├── Database/
│   └── RteImagesDbHook.php             # DB hooks: image magic, copy/paste handling
└── Utils/
    └── ProcessedFilesHandler.php        # File processing utilities
```

## Prerequisites

- PHP 8.1–8.4
- TYPO3 ^12.4 with `rte_ckeditor`
- Composer dependencies installed: `composer install`

## Build & Tests

```bash
composer ci:test:php:phpstan   # PHPStan analysis (level with baseline)
composer ci:test:php:lint      # PHP syntax check
composer ci:test:php:unit      # Unit tests
composer ci:test:php:functional # Functional tests
```

## Architecture (v12-specific)

This branch uses the **legacy controller/hook architecture**. Do NOT introduce patterns from `main` (v13+):

| v12 pattern (this branch) | v13+ pattern (main branch) | Do NOT backport |
|---------------------------|---------------------------|-----------------|
| `AbstractPlugin` subclass | `ImageRenderingAdapter` | Service-based rendering |
| `ext_localconf.php` hooks | PSR-14 EventListeners | Event-driven config |
| `GeneralUtility::makeInstance()` | Constructor DI | Full DI container |
| `MagicImageService` | `ImageResolverService` | Service layer |
| `getPagesTSconfig()` | `PageTsConfigFactory` | New TSconfig API |

## Code Style

- `declare(strict_types=1)` in all files
- `final class` unless inheritance required
- All parameters and return types declared
- Type narrowing (`is_string()`, `is_numeric()`) before casting `mixed` values — PHPStan level requires this
- Import order: PHP core → PSR interfaces → TYPO3 Core → Extension classes

## Security

### FAL Access
```php
// ✅ Permission check (v12 pattern)
$file->checkActionPermission('read')

// ❌ Removed in v12.4 — do not use
$backendUser->getFileStorageRecords()
```

### Input Validation
- Type cast query params: `(int)($request->getQueryParams()['id'] ?? 0)`
- Validate before use: check ranges, formats, existence
- HTML attributes from RTE content must be sanitized

## PR Checklist

1. `declare(strict_types=1)` in all files
2. All parameters and return types declared
3. PHPStan: zero new errors (`composer ci:test:php:phpstan`)
4. Code style: PSR-12/TYPO3 CGL compliant
5. Rector: no suggestions (`composer ci:test:php:rector`)
6. FAL usage: no direct file system access
7. Tests added for new/changed code paths

## Good vs Bad Examples

### ✅ Good: FAL Permission Check
```php
protected function isFileAccessibleByUser(File $file, BackendUserAuthentication $user): bool
{
    if ($user->isAdmin()) {
        return true;
    }
    return $file->checkActionPermission('read');
}
```

### ❌ Bad: Removed API Usage
```php
// This method was removed in TYPO3 12.4
$storageRecords = $backendUser->getFileStorageRecords();
```

### ✅ Good: Type-safe Mixed Handling
```php
$width = $request->getQueryParams()['width'] ?? null;
if (is_numeric($width)) {
    $imageWidth = (int) $width;
}
```

### ❌ Bad: Unsafe Cast
```php
// PHPStan will reject this — mixed cannot be cast directly
$imageWidth = (int) $request->getQueryParams()['width'];
```

## When Stuck

- **PHPStan errors on deprecated class**: Add to baseline with `composer ci:test:php:phpstan:baseline`
- **Type errors on `mixed`**: Use `is_string()` / `is_numeric()` / `is_array()` guards before casts
- **FAL permissions**: Use `File::checkActionPermission('read')` — see `SelectImageController`
- **DI not working**: Check `Configuration/Services.yaml` registration
