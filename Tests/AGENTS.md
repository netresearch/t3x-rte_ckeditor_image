<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-02-13 -->

# AGENTS.md -- Tests

## Overview

Multi-layer test suite: unit, functional, E2E (Playwright), JavaScript (Vitest), architecture (phpat), fuzz (php-fuzzer), and mutation (Infection).

## Test Structure

```
Tests/
  Unit/                         -- Fast, isolated PHPUnit tests (no DB, no TYPO3 bootstrap)
    Controller/                   ImageRenderingAdapterTest, SelectImageControllerTest
    Service/                      ImageResolverServiceTest, ImageRenderingServiceTest, ImageAttributeParserTest
    Service/Builder/              ImageTagBuilderTest
    Service/Environment/          Typo3EnvironmentInfoTest
    Service/Fetcher/              ExternalImageFetcherTest
    Service/Parser/               ImageTagParserTest
    Service/Processor/            RteImageProcessorTest, RteImageProcessorFactoryTest
    Service/Resolver/             ImageFileResolverTest
    Service/Security/             SecurityValidatorTest
    Database/                     RteImagesDbHookTest
    Domain/Model/                 ImageRenderingDtoTest, LinkDtoTest
    Backend/Preview/              RteImagePreviewRendererTest
    DataHandling/SoftReference/   RteImageSoftReferenceParserTest
    Listener/TCA/                 RteSoftrefEnforcerTest
    Utils/                        ProcessedFilesHandlerTest
  Functional/                   -- Tests with TYPO3 context + SQLite database
    Controller/                   ImageRenderingAdapterTypoScriptTest, FigureCaptionRenderingTest, etc.
    Service/                      ImageRenderingIntegrationTest, PartialPathResolutionTest
    Database/                     RteImagesDbHookTest (functional)
    DataHandling/                 RteImageSoftReferenceParserTest (with reference index)
    TypoScript/                   ParseFuncIntegrationTest
    Controller/Fixtures/          CSV fixtures (pages.csv, sys_file.csv, sys_file_storage.csv)
  E2E/                          -- Playwright browser tests against real TYPO3 instances
    tests/                        22 spec files covering image dialog, rendering, links, etc.
    tests/helpers/                typo3-backend.ts (shared login/navigation), selectors.ts
    playwright.config.ts          Configuration
    package.json                  Playwright dependency
  JavaScript/                   -- Vitest unit tests for CKEditor 5 plugin
    tests/                        JS unit test files
    mocks/                        Mock modules
    vitest.config.ts              Vitest configuration
  Architecture/                 -- phpat structural tests
    ArchitectureTest.php          Enforces layer boundaries and naming rules
  Fuzz/                         -- php-fuzzer targets
    ImageAttributeParserTarget.php
    RteImageSoftReferenceParserTarget.php
    corpus/                       Fuzz test corpus data
```

## Running Tests

| Type | Command | Notes |
|------|---------|-------|
| Unit tests | `composer ci:test:php:unit` | Fast, no DB needed |
| Functional tests | `composer ci:test:php:functional` | Needs `typo3DatabaseDriver=pdo_sqlite` env var |
| JavaScript tests | `composer ci:test:js:unit` | Runs in Tests/JavaScript/ via Vitest |
| E2E tests | `Build/Scripts/runTests.sh -s e2e -t 13 -p 8.5` | Docker-based, TYPO3 v13 or v14 |
| Fuzz tests | `composer ci:fuzz` | 10,000 runs per target |
| Mutation tests | `composer ci:mutation` | Infection, runs unit tests first |
| Unit coverage | `composer ci:coverage:unit` | Outputs to `.Build/coverage-unit/` |
| Functional coverage | `composer ci:coverage:functional` | Outputs to `.Build/coverage/` |
| All CI checks | `composer ci:test` | lint + phpstan + rector + cgl + unit + js + functional |

## Unit Test Patterns

- Extend `\TYPO3\TestingFramework\Core\Unit\UnitTestCase`
- Test class mirrors source path: `Service/ImageResolverService.php` -> `Unit/Service/ImageResolverServiceTest.php`
- Use `@test` annotation or `test` prefix on method names
- Use data providers for parameterized testing
- Mock external dependencies (ResourceFactory, LogManager, etc.)
- PHPStan level 10 applies to test files (with specific ignores in `Build/phpstan.neon`)

## Functional Test Patterns

- Extend `\TYPO3\TestingFramework\Core\Functional\FunctionalTestCase`
- Set `$testExtensionsToLoad = ['netresearch/rte-ckeditor-image']`
- Use CSV fixtures in `Fixtures/` directories (`$this->importCSVDataSet()`)
- Database driver: SQLite (`typo3DatabaseDriver=pdo_sqlite`)
- Services accessed via `$this->get(ServiceClass::class)` (services are public in Services.yaml)

## E2E Test Patterns (Playwright)

- Shared helpers in `tests/helpers/typo3-backend.ts` for login and navigation
- Selectors centralized in `tests/helpers/selectors.ts`
- Each `test()` gets a fresh page -- module-level state does not persist
- Always `waitForLoadState('networkidle')` after `page.goto()`
- Playwright strict mode: use `.first()` when selectors could match multiple elements
- Content element isolation: saving tests use dedicated CEs to avoid pollution
- CE isolation map (in `runTests.sh`): CE 26=dimensions, 27=quality, 28=overrides, 29-30=click-behavior, 31=apply-changes, 32=roundtrip, 33=insertion
- Override checkbox toggle: use vanilla JS `page.evaluate()` -- jQuery not on `window` in TYPO3 v13+
- CKEditor: bare `<p><img></p>` renders as block widget (dblclick won't open dialog). Must include surrounding text
- `clearCookies()` before frontend navigation after backend login (session interference)
- v14 E2E runs with `continue-on-error` (non-blocking while stabilizing)

## CI Environment

- CI matrix: PHP 8.2/8.3/8.4/8.5 x TYPO3 ^13.4/^14.0
- CGL runs only on PHP 8.2 (code style is PHP version independent)
- Coverage runs only on PHP 8.5 + TYPO3 ^14.0
- JavaScript tests run once (not PHP/TYPO3 version dependent)
- E2E runs after build jobs pass, on v13 (blocking) and v14 (informational)

## PR Checklist

- [ ] All unit tests pass: `composer ci:test:php:unit`
- [ ] All functional tests pass: `composer ci:test:php:functional` (with `typo3DatabaseDriver=pdo_sqlite`)
- [ ] New functionality has tests (unit tests at minimum)
- [ ] Test names describe behavior, not implementation
- [ ] Fixtures are minimal and focused
- [ ] No hardcoded credentials or paths in tests
- [ ] Coverage has not decreased
