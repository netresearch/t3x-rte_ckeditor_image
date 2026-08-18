<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-08-18 -->

# AGENTS.md -- Tests

## Overview

Multi-layer test suite: unit, functional, E2E (Playwright), JavaScript (Vitest), architecture (phpat), fuzz (php-fuzzer), and mutation (Infection).

## Setup

- PHP suites: `composer install`; functional tests additionally need `typo3DatabaseDriver=pdo_sqlite` in the environment
- JS suite: `composer ci:test:js:unit` runs `npm ci` in `Tests/JavaScript/` on demand -- no manual setup
- E2E: needs Docker (`Build/Scripts/runTests.sh` pulls the images)

## Conventions

- PHPStan level 10 and PSR-12 + TYPO3 CGL apply to test code, same as source (specific ignores in `Build/phpstan.neon`)
- Test class mirrors source path; one behavior per test; data providers for parameterization

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
| E2E tests | `Build/Scripts/runTests.sh -s e2e -t 13 -p 8.5 -X fsc` | Docker-based, TYPO3 v13 or v14, variant via `-X` |
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
- E2E setup variants via `-X` flag exercise the extension under different sitepackage / FSC / Bootstrap-Package combinations:
  - `-X fsc` (default): fluid_styled_content site set, no Bootstrap. Long-standing baseline.
  - `-X core-only`: minimal install, neither FSC nor Bootstrap. Models the fresh-install evaluator scenario; surfaces the bug class in [#790](https://github.com/netresearch/t3x-rte_ckeditor_image/issues/790).
  - `-X bootstrap`: FSC + Bootstrap Package (`^15.0` for v13, `^16.0` for v14). Common real-world setup.
- CI invokes via `Build/Scripts/ci-e2e.sh` (wrapper that translates the workflow's `E2E_VARIANT`/`E2E_TYPO3_VERSION` env vars into runTests.sh CLI flags).
- Specs that fundamentally require a specific variant (e.g. Bootstrap lightbox CSS) should `test.skip(process.env.E2E_VARIANT === 'core-only', '...')` to keep the matrix clean.

## CI Environment

- CI runs via the centralized `netresearch/typo3-ci-workflows` reusable workflows; the per-repo matrix lives in `.github/workflows/ci.yml`
- CI matrix: PHP 8.2/8.3/8.4/8.5 x TYPO3 ^13.4.21/^14.3
- CGL runs once, on the first PHP version of the matrix (currently 8.2); coverage upload is handled by the central workflow
- JavaScript tests run once (not PHP/TYPO3 version dependent)
- E2E matrix: TYPO3 ^13.4.21 + ^14.3 x setup-variants `[fsc, core-only, bootstrap]` = 6 jobs, all blocking required status checks in the `default` branch ruleset -- keep job names in sync with the ruleset (see the comment in `ci.yml`)

## PR Checklist

- [ ] All unit tests pass: `composer ci:test:php:unit`
- [ ] All functional tests pass: `composer ci:test:php:functional` (with `typo3DatabaseDriver=pdo_sqlite`)
- [ ] New functionality has tests (unit tests at minimum)
- [ ] Test names describe behavior, not implementation
- [ ] Fixtures are minimal and focused
- [ ] No hardcoded credentials or paths in tests
- [ ] Coverage has not decreased

## Security

- No credentials, tokens, or real user data in tests or fixtures
- Security regressions get a dedicated test (XSS, SSRF, protocol allowlist) -- existing ones live in `Unit/Service/Security/` and `Unit/Service/`

## Examples

Golden tests to copy from: `Unit/Service/ImageResolverServiceTest.php` (mock-based service test), `Functional/Controller/FigureCaptionRenderingTest.php` (TypoScript-driven rendering assertion), `E2E/tests/helpers/typo3-backend.ts` (shared E2E helpers).

## When stuck

- Flaky E2E: see the isolation and waiting rules under "E2E Test Patterns" -- most flakes are missing waits or shared content elements
- Functional DB errors: check `typo3DatabaseDriver=pdo_sqlite` is set
- CI-only failures: reproduce with `Build/Scripts/runTests.sh` (same Docker images as CI)
