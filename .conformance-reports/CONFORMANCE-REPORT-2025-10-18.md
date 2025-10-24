# TYPO3 Extension Conformance Report

**Extension:** rte_ckeditor_image (v13.0.0)
**Evaluation Date:** 2025-10-18
**TYPO3 Compatibility:** 13.4.x LTS
**PHP Compatibility:** 8.2 / 8.3 / 8.4

---

## Executive Summary

**Overall Conformance Score:** 82/100

- Extension Architecture: 19/20 ⭐
- Coding Guidelines: 18/20 ⭐
- PHP Architecture: 14/20 ⚠️
- Testing Standards: 12/20 ⚠️
- Best Practices: 19/20 ⭐

**Priority Issues:** 3 Critical
**Recommendations:** 8

**Overall Assessment:** This extension demonstrates **excellent** conformance to TYPO3 13 LTS standards with modern tooling and comprehensive quality infrastructure. The main areas for improvement are reducing deprecated pattern usage (GeneralUtility::makeInstance, global state) and expanding test coverage with unit tests.

---

## 1. Extension Architecture (19/20)

### ✅ Strengths

- **Excellent** - `composer.json` present with proper PSR-4 autoloading configuration
- **Excellent** - `ext_emconf.php` properly structured with correct metadata
- **Excellent** - Classes/ directory well-organized with proper namespace structure:
  - `Backend/` - Backend-specific components
  - `Controller/` - Request handlers
  - `DataHandling/` - Data processing logic
  - `Database/` - Database hooks
  - `Utils/` - Utility classes
- **Excellent** - Configuration/ using modern TYPO3 13 structure:
  - `Backend/Routes.php` - Modern backend routing
  - `Services.yaml` - Dependency injection configuration
  - `TCA/Overrides/` - Proper TCA extension pattern
  - `TypoScript/` - TypoScript configuration
- **Excellent** - Resources/ properly separated into Private/Public
- **Excellent** - Documentation/ complete with Index.rst and Settings.cfg
- **Excellent** - Tests/ directory present with functional tests

### ❌ Critical Issues

**None** - Architecture is modern and conformant

### ⚠️  Warnings

- **Minor** - ext_localconf.php still present using old hook registration pattern (line 17-18)
  - Location: `ext_localconf.php:17-18`
  - Issue: Using `$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']` for hooks
  - Impact: Works but deprecated pattern for TYPO3 13

### 💡 Recommendations

1. **Migrate hook to PSR-14 event** - Convert RteImagesDbHook from SC_OPTIONS hook to PSR-14 event listener
2. **Consider deprecating ext_localconf.php** - Once hook is migrated, evaluate if ext_localconf.php is still needed

---

## 2. Coding Guidelines (18/20)

### ✅ Strengths

- **Excellent** - All PHP files use `declare(strict_types=1)` (100% compliance)
- **Excellent** - Proper namespace structure matching directory organization
- **Excellent** - UpperCamelCase class naming convention followed consistently
- **Excellent** - Type declarations present on methods and parameters
- **Excellent** - PHPDoc comments present on classes and public methods
- **Excellent** - Use statements properly organized and sorted
- **Good** - Descriptive method names with clear verbs

### ❌ Violations

- **Minor** - 6 instances of old `array()` syntax (PSR-12 requires `[]`)
  - Classes/Controller/SelectImageController.php:87 - `is_array($parsedBody)`
  - Classes/Controller/ImageRenderingController.php:204 - `is_array($popupConfig)`
  - Classes/Database/RteImagesDbHook.php:108 - `is_array($parsedUrl)`
  - Classes/Database/RteImagesDbHook.php:188 - `in_array($mimeType, ...)`
  - Classes/Database/RteImagesDbHook.php:572 - `is_array($pU)`
  - Classes/Database/RteImagesDbHook.php:583 - `in_array($extension, ...)`
  - **Note:** These are function calls (`is_array()`, `in_array()`), not array declarations - **NOT a violation**

### ⚠️  Style Issues

**None detected** - Code appears PSR-12 compliant

### 💡 Recommendations

1. **Run php-cs-fixer** - Execute `composer ci:cgl` to apply automated code style fixes
2. **Enable pre-commit hooks** - Ensure code style checks run before commits

---

## 3. PHP Architecture (14/20)

### ✅ Modern Patterns

- **Excellent** - `Configuration/Services.yaml` properly configured with autowiring
- **Excellent** - Constructor injection used in multiple classes:
  - `RteImagesDbHook` - Proper DI with 6 injected dependencies
  - `RteImageSoftReferenceParser` - Constructor injection pattern
- **Excellent** - Services registered as public only when necessary
- **Good** - Soft reference parser registered via service tags
- **Good** - Backend controller tagged appropriately

### ❌ Architecture Issues

- **High Priority** - **13 instances of `GeneralUtility::makeInstance()`** (deprecated pattern)
  - Classes/Controller/SelectImageController.php:71 - `ResourceFactory::class`
  - Classes/Controller/ImageRenderingController.php:87 - `ResourceFactory::class`
  - Classes/Controller/ImageRenderingController.php:113 - `ProcessedFilesHandler::class`
  - Classes/Controller/ImageRenderingController.php:280 - `LogManager::class`
  - Classes/Controller/ImageLinkRenderingController.php:108 - `ResourceFactory::class`
  - *...and 8 more instances*

- **High Priority** - **8 instances of `$GLOBALS` access** (global state dependency)
  - Classes/Controller/SelectImageController.php:96 - `$GLOBALS['TYPO3_CONF_VARS']`
  - Classes/Controller/SelectImageController.php:205 - `$GLOBALS['BE_USER']`
  - Classes/Database/RteImagesDbHook.php:350 - `$GLOBALS['TCA']`
  - Classes/Database/RteImagesDbHook.php:461 - `$GLOBALS['TYPO3_REQUEST']`
  - Classes/Database/RteImagesDbHook.php:465 - `ApplicationType::fromRequest($GLOBALS['TYPO3_REQUEST'])`
  - *...and 3 more instances*

- **Critical** - **Using deprecated hook system instead of PSR-14 events**
  - ext_localconf.php:17-18 - `SC_OPTIONS` hook registration
  - Should migrate to PSR-14 event system

### 💡 Recommendations

1. **Refactor SelectImageController** - Inject ResourceFactory via constructor instead of makeInstance
2. **Refactor rendering controllers** - Use dependency injection for all services
3. **Replace $GLOBALS access** - Use proper service injection for configuration and context
4. **Migrate to PSR-14 events** - Convert RteImagesDbHook to event listener pattern
5. **Add readonly properties** - Use `private readonly` for injected dependencies (PHP 8.1+)

---

## 4. Testing Standards (12/20)

### ✅ Test Infrastructure

- **Good** - PHPUnit configuration files present in Build/phpunit/
  - FunctionalTests.xml
  - FunctionalTestsBootstrap.php
- **Good** - Tests/ directory structure mirrors Classes/ organization
- **Good** - Functional tests present with proper fixtures
- **Good** - Test fixtures well-organized in dedicated directory

### ❌ Testing Gaps

- **Critical** - **No Tests/Unit/ directory found** - Missing unit test coverage
- **Critical** - **No UnitTests.xml configuration** - No unit test runner configured
- **High** - **Only 2 functional test files for 7 class files** (~29% coverage)
  - Tests present:
    - Tests/Functional/Controller/SelectImageControllerTest.php
    - Tests/Functional/DataHandling/RteImageSoftReferenceParserTest.php
  - Classes without tests (5 files):
    - Classes/Controller/ImageRenderingController.php
    - Classes/Controller/ImageLinkRenderingController.php
    - Classes/Backend/Preview/RteImagePreviewRenderer.php
    - Classes/Database/RteImagesDbHook.php
    - Classes/Utils/* (utility classes)

### 📊 Coverage Estimate

- **Functional test files:** 2
- **Unit test files:** 0
- **Total class files:** 7
- **Classes with tests:** 2 (~29%)
- **Estimated coverage:** ~30% (below 70% recommendation)

### 💡 Recommendations

1. **Create Tests/Unit/ directory** - Establish unit testing infrastructure
2. **Add Build/phpunit/UnitTests.xml** - Configure unit test runner
3. **Write unit tests for utilities** - Start with Utils/ classes (easiest to test)
4. **Add controller unit tests** - Test rendering controllers in isolation
5. **Add coverage reporting** - Use `composer ci:coverage:functional` as template for unit tests
6. **Target 70% coverage** - Prioritize testing business logic and data handling

---

## 5. Best Practices (19/20)

### ✅ Project Infrastructure

- **Excellent** - `.editorconfig` present for consistent coding style
- **Excellent** - `.gitignore` properly configured
- **Excellent** - `README.md` present with clear documentation
- **Excellent** - `LICENSE` file present (AGPL-3.0-or-later)
- **Excellent** - GitHub Actions CI/CD pipeline configured:
  - `.github/workflows/ci.yml` - Continuous integration
  - `.github/workflows/codeql-analysis.yml` - Security scanning
  - `.github/workflows/publish-to-ter.yml` - TER publishing
  - `.github/workflows/add-to-project.yml` - Project automation

### ✅ Code Quality Tools

- **Excellent** - PHPStan configured at **level 10** (maximum strictness)
  - Build/phpstan.neon
  - Build/phpstan-baseline.neon
  - Includes strict rules, deprecation rules, TYPO3-specific rules
- **Excellent** - PHP CS Fixer configured
  - Build/.php-cs-fixer.dist.php
  - Composer script: `composer ci:cgl`
- **Excellent** - Rector configured for automated refactoring
  - Build/rector.php
  - Composer script: `composer ci:rector`
- **Excellent** - PHPLint configured
  - Build/.phplint.yml
  - Composer script: `composer ci:test:php:lint`
- **Excellent** - Comprehensive composer scripts for CI:
  - `composer ci:test` - Runs all quality checks
  - `composer ci:security` - Security audit
  - `composer ci:coverage:functional` - Coverage reporting

### ✅ Security Review

- **Excellent** - Composer audit configured (`composer ci:security`)
- **Good** - CodeQL security scanning in GitHub Actions
- **Good** - Strict type declarations prevent type juggling vulnerabilities
- **Good** - PHPStan level 10 catches many security issues

### ❌ Missing Components

**None** - All recommended infrastructure is present

### 💡 Recommendations

1. **Enable pre-commit hooks** - Husky configuration exists (`.husky/`) - ensure developers use it
2. **Document testing procedures** - Add testing section to README.md
3. **Add badge shields** - Consider adding CI status badges to README.md

---

## Priority Action Items

### 🔴 High Priority (Fix Soon)

1. **Add unit testing infrastructure**
   - Create Tests/Unit/ directory
   - Add Build/phpunit/UnitTests.xml
   - Write unit tests for at least 50% of classes
   - Target: 70% total coverage

2. **Reduce GeneralUtility::makeInstance usage**
   - Refactor controllers to use constructor injection
   - Priority: SelectImageController, ImageRenderingController
   - Target: <5 instances (down from 13)

3. **Migrate from hooks to PSR-14 events**
   - Convert RteImagesDbHook to event listener
   - Remove SC_OPTIONS registration from ext_localconf.php
   - Update documentation for new event system

### 🟡 Medium Priority (Improve When Possible)

4. **Reduce global state dependencies**
   - Inject Context service instead of accessing $GLOBALS['BE_USER']
   - Use ExtensionConfiguration service for TYPO3_CONF_VARS
   - Inject ServerRequestInterface where needed
   - Target: <3 instances (down from 8)

5. **Expand test coverage**
   - Add tests for ImageRenderingController
   - Add tests for ImageLinkRenderingController
   - Add tests for RteImagePreviewRenderer
   - Add tests for utility classes

### 🟢 Low Priority (Optional Improvements)

6. **Documentation enhancements**
   - Add testing section to README.md
   - Add architecture diagram to documentation
   - Add migration guide for event system changes

---

## Detailed Issue List

| Category | Severity | File | Line | Issue | Recommendation |
|----------|----------|------|------|-------|----------------|
| Architecture | High | ext_localconf.php | 17-18 | Using deprecated SC_OPTIONS hook | Migrate to PSR-14 event system |
| Architecture | High | SelectImageController.php | 71 | Using GeneralUtility::makeInstance() | Switch to constructor injection |
| Architecture | High | ImageRenderingController.php | 87, 113, 280 | Using GeneralUtility::makeInstance() (3×) | Switch to constructor injection |
| Architecture | High | ImageLinkRenderingController.php | 108 | Using GeneralUtility::makeInstance() | Switch to constructor injection |
| Architecture | Medium | SelectImageController.php | 96, 205 | Using $GLOBALS (2×) | Inject ExtensionConfiguration, Context services |
| Architecture | Medium | RteImagesDbHook.php | 350, 461, 465 | Using $GLOBALS (3×) | Inject services via constructor |
| Testing | Critical | Tests/ | - | No unit tests directory | Create Tests/Unit/ with UnitTests.xml |
| Testing | Critical | - | - | Only 29% test coverage | Write tests for remaining 5 classes |
| Testing | Medium | Tests/Unit/ | - | No unit test configuration | Add Build/phpunit/UnitTests.xml |

---

## Migration Guides

### 1. Converting from Hook to PSR-14 Event

**Before (ext_localconf.php) - DEPRECATED:**
```php
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
    = RteImagesDbHook::class;
```

**After (Configuration/Services.yaml) - MODERN:**
```yaml
services:
  Netresearch\RteCKEditorImage\EventListener\RteImageDataHandler:
    tags:
      - name: event.listener
        identifier: 'rte-ckeditor-image/data-handler'
        event: TYPO3\CMS\Core\DataHandling\Event\BeforeRecordUpdatedEvent
```

**Event Listener Class:**
```php
<?php
declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\EventListener;

use TYPO3\CMS\Core\DataHandling\Event\BeforeRecordUpdatedEvent;

final class RteImageDataHandler
{
    public function __invoke(BeforeRecordUpdatedEvent $event): void
    {
        // Migration logic from processDatamap_afterDatabaseOperations
    }
}
```

### 2. Converting to Constructor Injection

**Before - DEPRECATED:**
```php
<?php
class SelectImageController extends ElementBrowserController
{
    private ResourceFactory $resourceFactory;

    public function __construct()
    {
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
    }
}
```

**After - MODERN:**
```php
<?php
class SelectImageController extends ElementBrowserController
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory
    ) {}
}
```

**Update Services.yaml (if not using autowire):**
```yaml
services:
  Netresearch\RteCKEditorImage\Controller\SelectImageController:
    arguments:
      $resourceFactory: '@TYPO3\CMS\Core\Resource\ResourceFactory'
    tags: ['backend.controller']
```

### 3. Replacing $GLOBALS Access

**Before - DEPRECATED:**
```php
$backendUser = $GLOBALS['BE_USER'] ?? null;
$imageExt = $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'];
```

**After - MODERN:**
```php
<?php
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

class SelectImageController
{
    public function __construct(
        private readonly Context $context,
        private readonly ExtensionConfiguration $extensionConfiguration
    ) {}

    public function someMethod(): void
    {
        $backendUser = $this->context->getAspect('backend.user');
        $imageExt = $this->extensionConfiguration->get('GFX', 'imagefile_ext');
    }
}
```

---

## Conformance Checklist

### File Structure
- [x] composer.json with PSR-4 autoloading
- [x] Classes/ directory properly organized
- [x] Configuration/ using modern structure
- [x] Resources/ separated Private/Public
- [x] Tests/ directory present
- [x] Documentation/ complete

### Coding Standards
- [x] declare(strict_types=1) in all PHP files
- [x] Type declarations on methods
- [x] PHPDoc on all public methods
- [x] PSR-12 compliant formatting
- [x] Proper naming conventions

### PHP Architecture
- [x] Configuration/Services.yaml configured
- [ ] Constructor injection used everywhere (14/20 - needs improvement)
- [ ] PSR-14 events instead of hooks (using deprecated hooks)
- [ ] No GeneralUtility::makeInstance() (13 instances found)
- [ ] No $GLOBALS access (8 instances found)

### Testing
- [x] Functional tests present and passing
- [ ] Unit tests present (missing)
- [ ] Test coverage >70% (~30% current)
- [x] PHPUnit configuration files

### Best Practices
- [x] Code quality tools configured (PHPStan, CS Fixer, Rector)
- [x] CI/CD pipeline setup (GitHub Actions)
- [x] Security scanning enabled (CodeQL)
- [x] Complete documentation
- [x] README and LICENSE present

---

## Resources

- **TYPO3 Core API:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/
- **Extension Architecture:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ExtensionArchitecture/
- **Coding Guidelines:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/CodingGuidelines/
- **Testing Documentation:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Testing/
- **PSR-14 Events:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Events/
- **Dependency Injection:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/DependencyInjection/

---

## Conclusion

The **rte_ckeditor_image** extension demonstrates **strong conformance** to TYPO3 13 LTS standards with an overall score of **82/100**. The extension benefits from:

✅ **Excellent modern tooling** - PHPStan level 10, comprehensive CI/CD, security scanning
✅ **Solid architecture** - Proper directory structure, PSR-4 autoloading, modern configuration
✅ **Good code quality** - Strict types, proper documentation, PSR-12 compliance

**Key areas for improvement:**

⚠️ **Architecture modernization** (14/20) - Reduce makeInstance usage, migrate to PSR-14 events
⚠️ **Testing expansion** (12/20) - Add unit tests, increase coverage to 70%+

**Recommended next steps:**

1. Prioritize adding unit testing infrastructure (highest impact)
2. Refactor controllers to use constructor injection
3. Migrate from hooks to PSR-14 event system
4. Reduce global state dependencies

With these improvements, the extension would achieve **95/100** conformance and serve as a best-practice reference implementation.
