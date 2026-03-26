<!-- Managed by agent: keep sections & order; edit content, not structure -->
<!-- Last updated: 2026-03-26 -->

# Tests/AGENTS.md

**Scope:** All test types for the TYPO3_12 branch
**Parent:** [../AGENTS.md](../AGENTS.md)

## Overview

```
Tests/
├── Functional/
│   ├── Controller/
│   │   └── SelectImageControllerTest.php       # FAL integration, DB fixtures
│   └── DataHandling/
│       └── RteImageSoftReferenceParserTest.php  # Soft reference parsing with DB
└── Unit/
    └── Controller/
        └── SelectImageControllerTest.php        # Permission logic, mocked FAL
```

## Getting Started

```bash
# Install dependencies
composer install

# Run unit tests (fast, no DB)
composer ci:test:php:unit

# Run functional tests (needs SQLite)
typo3DatabaseDriver=pdo_sqlite composer ci:test:php:functional

# Run all checks
composer ci:test
```

## Commands

| Task | Command | Notes |
|------|---------|-------|
| Unit tests | `composer ci:test:php:unit` | Fast, no DB needed |
| Functional tests | `composer ci:test:php:functional` | Needs `typo3DatabaseDriver=pdo_sqlite` |
| All tests | `composer ci:test` | Includes lint + phpstan + rector + tests |

## Conventions

### Unit Tests
- Extend `TYPO3\TestingFramework\Core\Unit\UnitTestCase`
- Mock FAL objects (`File`, `ResourceStorage`, `Folder`) with PHPUnit mocks
- Use `self::assert*()` (not `$this->assert*()` — PHPStan strict rules)
- Test one behavior per method
- Method naming: `test` prefix + descriptive name (e.g. `testNonAdminUserCanAccessPublicFile`)

### Functional Tests
- Extend `TYPO3\TestingFramework\Core\Functional\FunctionalTestCase`
- Set `$testExtensionsToLoad = ['typo3conf/ext/rte_ckeditor_image']`
- Use SQLite driver: `typo3DatabaseDriver=pdo_sqlite`
- Import fixtures for file references and sys_file records

## Security

- Never use real credentials or API keys in test fixtures
- Mock external services, don't call real endpoints
- Test permission boundaries (admin vs non-admin, public vs private storage)

## PR Checklist

1. New code paths have corresponding tests
2. All tests pass: `composer ci:test`
3. No `@group disabled` or skipped tests
4. Assertions use `self::` not `$this->`
5. Functional tests specify required extensions in `$testExtensionsToLoad`

## Good vs Bad Examples

### ✅ Good: Unit Test with Mocked FAL
```php
public function testNonAdminUserCanAccessPublicFile(): void
{
    $file = $this->createMock(File::class);
    $file->method('checkActionPermission')->with('read')->willReturn(true);

    $user = $this->createMock(BackendUserAuthentication::class);
    $user->method('isAdmin')->willReturn(false);

    self::assertTrue($this->subject->isFileAccessibleByUser($file, $user));
}
```

### ❌ Bad: Skipping Tests
```php
// Never do this to make CI pass
/** @group disabled */
public function testBrokenFeature(): void { }
```

## When Stuck

- **Functional test DB errors**: Ensure `typo3DatabaseDriver=pdo_sqlite` is set
- **Extension not loaded**: Check `$testExtensionsToLoad` includes `typo3conf/ext/rte_ckeditor_image`
- **PHPStan on test files**: Use `self::assert*()` instead of `$this->assert*()`
- **Missing fixtures**: Check `Tests/Functional/Fixtures/` for existing data sets
