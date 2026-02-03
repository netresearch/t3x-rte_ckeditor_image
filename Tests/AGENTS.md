# Tests/AGENTS.md

<!-- Managed by agent: keep sections & order; edit content, not structure. Last updated: 2026-02-03 -->

**Scope:** Testing (Unit, Functional, JavaScript, E2E, Fuzz)
**Parent:** [../AGENTS.md](../AGENTS.md)

## Overview

Comprehensive test suite for TYPO3 CKEditor Image extension.

### Test Counts (Feb 2026)
| Type | Files | Framework |
|------|-------|-----------|
| Unit | 22 | PHPUnit |
| Functional | 10 | PHPUnit + TYPO3 Testing Framework |
| JavaScript | 2 | Vitest |
| E2E | 11 | Playwright |
| Fuzz | 2 | php-fuzzer |

### Test Structure
```
Tests/
├── Unit/                          # 22 PHPUnit tests (no database)
│   ├── Backend/Preview/
│   ├── Controller/
│   ├── DataHandling/SoftReference/
│   ├── Database/
│   ├── Domain/Model/
│   ├── Listener/TCA/
│   └── Service/                   # Builder, Environment, Fetcher, Parser, Processor
├── Functional/                    # 10 PHPUnit tests (database required)
│   ├── Controller/
│   ├── DataHandling/
│   ├── Database/
│   ├── Service/
│   └── TypoScript/
├── JavaScript/                    # Vitest for CKEditor plugin
│   └── tests/
├── E2E/                          # Playwright browser tests
│   └── tests/
└── Fuzz/                         # php-fuzzer targets
    ├── ImageAttributeParserTarget.php
    └── RteImageSoftReferenceParserTarget.php
```

### Coverage Areas
- ✅ Controllers (SelectImageController, ImageRenderingAdapter)
- ✅ Services (all 10+ service classes with interfaces)
- ✅ Domain models (DTOs)
- ✅ Database hooks (RteImagesDbHook)
- ✅ Soft reference parser
- ✅ TCA listener (RteSoftrefEnforcer)
- ✅ Backend preview renderer
- ✅ CKEditor plugin (JS unit tests)
- ✅ E2E: image dialogs, styles, linking, inline images

## 🏗️ Architecture Patterns

### TYPO3 Testing Framework
```php
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class RteImageSoftReferenceParserTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    #[Test]
    public function updateReferenceIndexAddsIndexEntryForImage(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ReferenceIndex/UpdateReferenceIndexImport.csv');
        $this->get(ReferenceIndex::class)->updateIndex(false);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ReferenceIndex/UpdateReferenceIndexResult.csv');
    }
}
```

### PHPUnit Attributes (PHP 8+)
- `#[Test]` - Mark test methods
- `#[DataProvider('dataProviderName')]` - Data-driven tests
- `#[Depends('testMethodName')]` - Test dependencies

## Commands (Verified)

| Command | Purpose | ~Time |
|---------|---------|-------|
| `composer ci:test:php:unit` | Unit tests only | 10s |
| `composer ci:test:php:functional` | Functional tests (CI authoritative) | 60s |
| `composer ci:test:js:unit` | JavaScript unit tests (Vitest) | 15s |
| `composer ci:test:e2e` | E2E tests via Docker/Playwright | 3min |
| `composer ci:coverage:unit` | Unit tests with coverage | 30s |
| `composer ci:mutation` | Mutation testing | 5min |
| `composer ci:fuzz` | Fuzz testing (image-parser + softref) | 2min |

### Running Tests

**CI is authoritative** for test results. Local execution for debugging only.

```bash
# Unit tests (fast, no database)
composer ci:test:php:unit

# Functional tests (CI is authoritative; local ddev OK for debugging)
composer ci:test:php:functional

# JavaScript unit tests
composer ci:test:js:unit

# E2E tests (requires ddev running)
composer ci:test:e2e

# Mutation testing
composer ci:mutation

# Fuzz testing
composer ci:fuzz
```

### Test Configuration
- `Build/phpunit/UnitTests.xml` - Unit tests config
- `Build/phpunit/FunctionalTests.xml` - Functional tests config
- `Tests/JavaScript/vitest.config.ts` - JavaScript tests config
- `Tests/E2E/playwright.config.ts` - E2E tests config
- `infection.json5` - Mutation testing config

## 📝 Code Style

### Test Naming
```php
// ✅ Good: Descriptive test names
#[Test]
public function updateReferenceIndexAddsIndexEntryForImage(): void

#[Test]
public function getImageReturnsFileObjectWhenFileExists(): void

#[Test]
public function infoActionReturns404WhenFileNotFound(): void

// ❌ Bad: Vague test names
#[Test]
public function testImage(): void

#[Test]
public function test1(): void
```

### Test Structure (AAA Pattern)
```php
#[Test]
public function methodNameExpectedBehaviorWhenCondition(): void
{
    // Arrange: Setup test data and dependencies
    $this->importCSVDataSet(__DIR__ . '/Fixtures/TestData.csv');
    $controller = $this->get(SelectImageController::class);

    // Act: Execute the method under test
    $result = $controller->infoAction($request);

    // Assert: Verify expected outcome
    $this->assertInstanceOf(JsonResponse::class, $result);
    $this->assertEquals(200, $result->getStatusCode());
}
```

### Fixture Files
```
Tests/
└── Functional/
    └── ComponentName/
        ├── Fixtures/
        │   ├── ScenarioName/
        │   │   ├── Import.csv           # Input data
        │   │   └── Result.csv           # Expected output
        │   └── AnotherScenario/
        │       ├── Import.csv
        │       └── Result.csv
        └── ComponentNameTest.php
```

## 🔒 Security

### Test Isolation
- **Fresh database:** Each test starts with clean database
- **Extension loading:** Only load required extensions
- **No side effects:** Tests must not affect other tests
- **Fixture cleanup:** Automatic cleanup after test completion

### Test Data Safety
```php
// ✅ Good: Controlled test data
$this->importCSVDataSet(__DIR__ . '/Fixtures/TestData.csv');

// ❌ Bad: Real database access
$connection->executeQuery('SELECT * FROM tt_content WHERE uid = 1');
```

## ✅ PR/Commit Checklist

### Test-Specific Checks
1. ✅ **Test naming:** Descriptive, follows convention
2. ✅ **Test isolation:** No dependencies on other tests
3. ✅ **AAA pattern:** Arrange, Act, Assert structure
4. ✅ **Fixtures:** CSV files properly formatted
5. ✅ **Coverage:** New features have test coverage
6. ✅ **All tests pass:** Green before commit
7. ✅ **No skipped tests:** Fix or remove, don't skip
8. ✅ **Type hints:** Strict types in test classes too

## 🎓 Good vs Bad Examples

### ✅ Good: Functional Test

```php
<?php

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Netresearch\RteCKEditorImage\Controller\SelectImageController;

class SelectImageControllerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    #[Test]
    public function infoActionReturnsJsonResponseWithImageData(): void
    {
        // Arrange: Import test data (file record)
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Images/ImageData.csv');

        $controller = $this->get(SelectImageController::class);
        $request = new ServerRequest('http://localhost/', 'GET');
        $request = $request->withQueryParams([
            'fileId' => 1,
            'table' => 'sys_file',
        ]);

        // Act: Call controller action
        $response = $controller->infoAction($request);

        // Assert: Verify response structure
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('uid', $data);
        $this->assertArrayHasKey('width', $data);
        $this->assertArrayHasKey('height', $data);
    }

    #[Test]
    public function infoActionReturns404WhenFileNotFound(): void
    {
        // Arrange: Controller with no data
        $controller = $this->get(SelectImageController::class);
        $request = new ServerRequest('http://localhost/', 'GET');
        $request = $request->withQueryParams([
            'fileId' => 999,  // Non-existent file
            'table' => 'sys_file',
        ]);

        // Act & Assert: Expect exception or 404
        $this->expectException(\RuntimeException::class);
        $controller->infoAction($request);
    }
}
```

### ❌ Bad: Poor Test Structure

```php
<?php
namespace Netresearch\RteCKEditorImage\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

class BadTest extends FunctionalTestCase
{
    // ❌ Missing strict types
    // ❌ Vague class name
    // ❌ Missing extension loading config

    public function testSomething()  // ❌ Missing #[Test] attribute, no return type
    {
        // ❌ No AAA structure
        // ❌ No context/setup
        $result = someFunction();
        // ❌ Weak assertion
        $this->assertTrue(true);
    }

    // ❌ Test depends on external state
    public function testAnother()
    {
        global $GLOBALS;  // ❌ Global state
        $result = doSomething();
        // ❌ No assertion
    }
}
```

### ✅ Good: Unit Test (Example)

```php
<?php

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Netresearch\RteCKEditorImage\Utils\ProcessedFilesHandler;

class ProcessedFilesHandlerTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('validDimensionsProvider')]
    public function calculateDimensionsReturnsCorrectValues(
        int $originalWidth,
        int $originalHeight,
        int $maxWidth,
        int $expectedWidth,
        int $expectedHeight
    ): void {
        // Arrange
        $handler = new ProcessedFilesHandler();

        // Act
        $result = $handler->calculateDimensions(
            $originalWidth,
            $originalHeight,
            $maxWidth
        );

        // Assert
        $this->assertEquals($expectedWidth, $result['width']);
        $this->assertEquals($expectedHeight, $result['height']);
    }

    public static function validDimensionsProvider(): array
    {
        return [
            'landscape image scaled down' => [1920, 1080, 960, 960, 540],
            'portrait image scaled down'  => [1080, 1920, 540, 540, 960],
            'square image scaled down'    => [1000, 1000, 500, 500, 500],
            'image already smaller'       => [100, 100, 500, 100, 100],
        ];
    }
}
```

### ❌ Bad: Untestable Code

```php
// ❌ Bad: Hard dependencies, no DI
class SelectImageController
{
    public function infoAction($request)
    {
        // ❌ Direct instantiation - can't mock
        $factory = new ResourceFactory();

        // ❌ Static method call - can't mock
        $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($id);

        // ❌ Global state access
        $config = $GLOBALS['TYPO3_CONF_VARS']['GFX'];

        // Cannot write unit tests for this
    }
}

// ✅ Good: Testable with DI
class SelectImageController
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory
    ) {
    }

    public function infoAction(ServerRequestInterface $request): ResponseInterface
    {
        // Easy to mock in tests
        $file = $this->resourceFactory->getFileObject($id);
    }
}
```

## 🆘 When Stuck

### Documentation
- **Testing Guide:** [docs/Examples/Common-Use-Cases.md#testing-examples](../docs/Examples/Common-Use-Cases.md)
- **TYPO3 Testing:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Testing/Index.html
- **Testing Framework:** https://github.com/TYPO3/testing-framework

### TYPO3 Testing Resources
- **Functional Tests:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Testing/WritingFunctional.html
- **Unit Tests:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Testing/WritingUnit.html
- **Fixtures:** https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Testing/CoreTesting.html#csv-data-set

### PHPUnit Resources
- **PHPUnit Manual:** https://phpunit.de/documentation.html
- **Attributes:** https://phpunit.de/manual/current/en/attributes.html
- **Assertions:** https://phpunit.de/manual/current/en/assertions.html

### Common Issues
- **Extension not loaded:** Check `$testExtensionsToLoad` and `$coreExtensionsToLoad`
- **Database errors:** Verify CSV fixtures are properly formatted
- **Test isolation failures:** Check for global state pollution
- **FAL mocking:** Use TYPO3 testing framework FAL utilities

## 📐 House Rules

### Test Organization
- **Functional tests:** `Tests/Functional/ComponentName/`
- **Unit tests:** `Tests/Unit/ComponentName/`
- **Fixtures:** `Tests/Functional/ComponentName/Fixtures/ScenarioName/`
- **Mirror structure:** Test directory structure mirrors `Classes/`

### Test Categories
- **Functional:** Database, FAL, integration, reference index
- **Unit:** Isolated logic, calculations, utilities
- **Browser:** Not applicable (CKEditor plugin testing via manual QA)

### Naming Conventions
- **Test class:** `{ClassName}Test.php`
- **Test method:** `methodNameExpectedBehaviorWhenCondition`
- **Fixtures:** `{ScenarioName}Import.csv`, `{ScenarioName}Result.csv`

### Test Quality
- **Fast tests:** Unit tests <100ms, functional tests <5s
- **Isolated:** No dependencies between tests
- **Deterministic:** Always produce same result
- **Readable:** Self-documenting test names and structure

### Coverage Goals
- **Controllers:** 80%+ coverage
- **Event Listeners:** 90%+ coverage
- **Database Hooks:** 95%+ coverage
- **Utilities:** 90%+ coverage

### CI Integration
- **Run on commit:** All tests must pass before merge
- **Coverage reports:** Track coverage trends
- **Performance:** Flag tests >5s as slow
- **Failure notification:** Immediate feedback on broken tests

## 🔗 Related

- **[Classes/AGENTS.md](../Classes/AGENTS.md)** - Components to test
- **[Resources/AGENTS.md](../Resources/AGENTS.md)** - CKEditor plugin testing
- **[docs/Examples/Common-Use-Cases.md](../docs/Examples/Common-Use-Cases.md)** - Test examples
- **composer.json** - Testing framework dependencies
