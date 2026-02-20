<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-02-13 | Last verified: 2026-02-13 -->

# AGENTS.md

**Precedence:** The **closest AGENTS.md** to changed files wins. Root holds global defaults only.

## Project Overview

TYPO3 CKEditor 5 extension that adds FAL (File Abstraction Layer) image support to the rich text editor.
Handles image insertion, processing, rendering with captions, links, popups, quality scaling, and alignment.

- **Package**: `netresearch/rte-ckeditor-image` (Composer) / `rte_ckeditor_image` (TER)
- **Namespace**: `Netresearch\RteCKEditorImage\`
- **Repository**: [github.com/netresearch/t3x-rte_ckeditor_image](https://github.com/netresearch/t3x-rte_ckeditor_image)
- **Tech Stack**: PHP ^8.2, TYPO3 ^13.4.21 || ^14.0, CKEditor 5
- **License**: AGPL-3.0-or-later
- **Current Version**: 13.5.0

## Architecture Overview

Three-layer design for frontend image rendering:

1. **ImageRenderingAdapter** (Controller) -- TypoScript entry points: `renderImageAttributes()`, `renderInlineLink()`, `renderFigure()`
2. **ImageResolverService** -- Business logic: file resolution, security validation, quality multipliers, builds `ImageRenderingDto`
3. **ImageRenderingService** -- Presentation: template selection via `match(true)` (Popup > Link > Caption > Standalone), Fluid rendering

Backend save path: `RteImagesDbHook` -> `RteImageProcessor` -> parser/builder/resolver/fetcher services.

CKEditor 5 plugin: `Resources/Public/JavaScript/Plugins/typo3image.js`

## Global Rules

- Conventional Commits enforced by commitlint: `type(scope): subject`
- Pre-commit hooks (husky): phplint, php-cs-fixer, phpstan
- Commit-msg hook: commitlint validates conventional commit format
- Keep PRs small (~300 net LOC)
- PHPStan level 10 with strict-rules and deprecation-rules -- zero errors required
- `declare(strict_types=1)` in all PHP files (except `ext_emconf.php` -- TER cannot parse it)
- TYPO3 extensions MUST NOT commit `composer.lock`

## Boundaries

### Always Do

- Run pre-commit checks before committing (phplint, php-cs-fixer, phpstan)
- Add tests for new code paths (unit, functional, or E2E as appropriate)
- Use conventional commit format: `type(scope): subject`
- Validate all user inputs -- especially HTML attributes from RTE content
- Show test output as evidence before claiming work is complete
- Use dependency injection via `Configuration/Services.yaml`, not `GeneralUtility::makeInstance()`
- Use `is_string()` / `is_array()` type narrowing instead of `(string)` cast on `mixed` values (PHPStan level 10)

### Ask First

- Adding new Composer or npm dependencies
- Modifying CI/CD workflows (`.github/workflows/`)
- Changing public API signatures on services or DTOs
- Running full E2E test suites (resource-intensive)
- Modifying security-sensitive code (security validators, URL allowlists, caption sanitization)
- Changing TypoScript setup that affects parseFunc_RTE behavior
- Changing CKEditor plugin JavaScript

### Never Do

- Commit secrets, credentials, API keys, or PII
- Modify `.Build/vendor/`, `node_modules/`, or generated files
- Push directly to main branch (merge queue required)
- Disable security features (URL protocol allowlist, file visibility validation, caption XSS sanitization)
- Add `style` attribute handling to HTML output (CSS injection prevention)
- Use `$GLOBALS['TYPO3_DB']` (deprecated since TYPO3 v8)
- Add `declare(strict_types=1)` to `ext_emconf.php` (breaks TER publishing)
- Hardcode environment-specific values

## Development Workflow

1. Create feature branch from `main`
2. Make changes with tests
3. Pre-commit hooks run automatically: phplint -> php-cs-fixer -> phpstan
4. Commit with conventional format (commitlint validates)
5. Push and create PR
6. CI runs: lint, phpstan, rector, unit tests, functional tests, E2E (v13 blocking, v14 informational)
7. Address review feedback (Copilot + Gemini Code Assist auto-review)
8. Merge via merge queue with `gh pr merge --merge --auto`

## Pre-commit Checks

**Automatic (husky pre-commit hook):**
```bash
composer ci:test:php:lint       # PHP syntax check
composer ci:test:php:cgl        # PHP-CS-Fixer dry-run (@Symfony rules)
composer ci:test:php:phpstan    # PHPStan level 10 analysis
```

**Manual (full CI pipeline):**
```bash
composer ci:test                # All checks: lint, phpstan, rector, cgl, unit, js-unit, functional
composer ci:test:php:unit       # Unit tests only
composer ci:test:php:functional # Functional tests (needs typo3DatabaseDriver=pdo_sqlite)
composer ci:test:js:unit        # JavaScript unit tests (Tests/JavaScript/)
composer ci:test:php:rector     # Rector dry-run check
```

**E2E tests (Docker-based):**
```bash
Build/Scripts/runTests.sh -s e2e -t 13 -p 8.5   # TYPO3 v13 E2E
Build/Scripts/runTests.sh -s e2e -t 14 -p 8.5   # TYPO3 v14 E2E (non-blocking)
```

**Make targets (convenience):**
```bash
make lint       # All linters (phplint + phpstan + rector + cgl + docs)
make format     # Auto-fix code style (php-cs-fixer)
make typecheck  # PHPStan only
make ci         # Full CI pipeline
make test       # Run tests
```

## Code Quality Standards

- **PHP-CS-Fixer**: @Symfony ruleset with risky rules, `binary_operator_spaces` alignment enforced
- **PHPStan**: Level 10 with `phpstan-strict-rules`, `phpstan-deprecation-rules`, `phpat` architecture rules
- **Rector**: TYPO3-specific automated modernization (`ssch/typo3-rector`)
- **Architecture tests**: `Tests/Architecture/ArchitectureTest.php` (phpat)
- **Fuzz testing**: `composer ci:fuzz` for ImageAttributeParser and SoftReferenceParser
- **Mutation testing**: `composer ci:mutation` via Infection

## Security & Safety

This extension implements defense-in-depth security:

- **Caption XSS Prevention**: All user-editable captions sanitized with `htmlspecialchars()` in `ImageResolverService::sanitizeCaption()`
- **File Visibility Validation**: Non-public storage files blocked in `ImageResolverService::validateFileVisibility()`
- **URL Protocol Allowlist**: Only `http:`, `https:`, `mailto:`, `tel:`, `t3:` allowed; `javascript:`, `vbscript:`, `data:text/html` blocked
- **Style Attribute Exclusion**: CSS injection prevented by excluding `style` from allowed HTML attributes
- **SVG Sanitization**: SVG data URIs sanitized via TYPO3's `SvgSanitizer` in `sanitizeSvgDataUri()`
- **SSRF Protection**: External image fetching in `SecurityValidator` includes DNS rebinding and private IP blocking

Security handled by TYPO3 Core (not this extension):
- SVG file upload sanitization (FAL), file extension/MIME validation (FAL), image processing security (GraphicalFunctions)

See [ADR-003: Security Responsibility Boundaries](Documentation/Architecture/ADR-003-Security-Responsibility-Boundaries.rst).

## Testing Requirements

| Type | Command | Environment |
|------|---------|-------------|
| Unit | `composer ci:test:php:unit` | Local or CI |
| Functional | `composer ci:test:php:functional` | CI (needs `typo3DatabaseDriver=pdo_sqlite`) |
| JavaScript | `composer ci:test:js:unit` | Local or CI |
| E2E | `Build/Scripts/runTests.sh -s e2e -t 13 -p 8.5` | CI (Docker) |
| Fuzz | `composer ci:fuzz` | Local or CI |
| Mutation | `composer ci:mutation` | Local or CI |
| Architecture | Part of unit suite via phpat | Local or CI |

**CI matrix**: PHP 8.2/8.3/8.4/8.5 x TYPO3 ^13.4/^14.0 (8 combinations for build, E2E on v13+v14)

## Index of Scoped AGENTS.md

- `./Classes/AGENTS.md` -- PHP source classes (service architecture, DTOs, controllers)
- `./Tests/AGENTS.md` -- All test types (unit, functional, E2E, fuzz, mutation, architecture, JS)
- `./Documentation/AGENTS.md` -- RST documentation for docs.typo3.org
- `./Resources/AGENTS.md` -- Fluid templates, XLIFF translations, CKEditor plugin, CSS
- `./.ddev/AGENTS.md` -- DDEV local development environment
- `./.github/workflows/AGENTS.md` -- GitHub Actions CI/CD workflows

## When Instructions Conflict

Nearest AGENTS.md wins. User prompts override files.
- For PHP patterns, follow PSR-12 + TYPO3 CGL
- For TypoScript, follow TYPO3 conventions
- For JavaScript, follow CKEditor 5 plugin patterns in this repo
