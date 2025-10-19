# Functional Tests

This directory contains TYPO3 functional tests that require a database.

## Requirements

**PHP Extensions Required:**
```bash
# SQLite PDO driver (for in-memory testing)
php -m | grep pdo_sqlite

# If missing, install:
sudo apt-get install php8.4-sqlite3  # Debian/Ubuntu
```

## Running Functional Tests

**With SQLite (recommended for CI):**
```bash
typo3DatabaseDriver=pdo_sqlite composer ci:test:php:functional
```

**With MySQL/MariaDB:**
```bash
export typo3DatabaseDriver=mysqli
export typo3DatabaseName=typo3_test
export typo3DatabaseUsername=root
export typo3DatabasePassword=root
export typo3DatabaseHost=127.0.0.1
composer ci:test:php:functional
```

## Test Coverage

### DataHandler Integration (Database/)
- `RteImagesDbHookTest.php` - Tests SC_OPTIONS hook integration with TYPO3 DataHandler
  - Field processing for RTE content
  - New vs update record handling
  - Multiple field processing
  - Hook registration verification

### FAL Integration (Controller/)
- `ImageRenderingControllerTest.php` - Tests File Abstraction Layer integration
  - File retrieval from storage
  - Storage accessibility
  - JSON response handling
- `SelectImageControllerTest.php` - Tests image selection and dimension handling
  - TSConfig parsing
  - Bounds enforcement
  - Security improvements

### Soft Reference (DataHandling/)
- `RteImageSoftReferenceParserTest.php` - Tests reference index integration
  - Image reference tracking
  - Database reference integrity

## Test Structure

Functional tests extend `TYPO3\TestingFramework\Core\Functional\FunctionalTestCase` and:
- Load test extensions via `$testExtensionsToLoad`
- Import CSV fixtures via `importCSVDataSet()`
- Use real TYPO3 framework services via dependency injection
- Test actual database interactions and framework integration

## Fixtures

CSV fixtures in `Fixtures/` directories provide test data:
- `pages.csv` - Test pages
- `tt_content.csv` - Test content elements
- `sys_file_storage.csv` - FAL storage configuration
- `sys_file.csv` - Test files

## Coverage Goals

**Phase 4 Target:** 80%+ code coverage
- Unit tests: Core logic, mocking dependencies
- Functional tests: Integration scenarios, real framework behavior
- Combined coverage provides comprehensive validation
