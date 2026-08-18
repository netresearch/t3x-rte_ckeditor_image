<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-08-18 | Last verified: 2026-08-18 -->

# AGENTS.md

**Precedence:** The **closest AGENTS.md** to changed files wins. Root holds global defaults only.

## Project Overview

TYPO3 CKEditor 5 extension that adds FAL (File Abstraction Layer) image support to the rich text editor.
Handles image insertion, processing, rendering with captions, links, popups, quality scaling, and alignment.

- **Package**: `netresearch/rte-ckeditor-image` (Composer) / `rte_ckeditor_image` (TER)
- **Namespace**: `Netresearch\RteCKEditorImage\`
- **Repository**: [github.com/netresearch/t3x-rte_ckeditor_image](https://github.com/netresearch/t3x-rte_ckeditor_image)
- **Tech Stack**: PHP ^8.2, TYPO3 ^13.4.21 || ^14.3, CKEditor 5
- **License**: AGPL-3.0-or-later
- **Current Version**: see `version` in `ext_emconf.php` (authoritative source)

## Architecture Overview

Three-layer design for frontend image rendering (component map and dependency rules: `docs/ARCHITECTURE.md`):

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
- PHPStan level 10 with strict-rules and deprecation-rules -- zero baseline errors required (baseline must be empty)
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
- Use `is_string()` / `is_array()` / `is_numeric()` type narrowing instead of direct casts on `mixed` values (PHPStan level 10)
- Never use `@phpstan-ignore` annotations -- fix the actual type issue instead
- Never use `empty()` -- use strict comparison (`=== true`, `!== ''`, `!== []`)

### Ask First

- Adding new Composer or npm dependencies
- Modifying CI/CD workflows (`.github/workflows/` -- thin wrappers over centralized workflows, see CI/CD below)
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
- Wipe `$GLOBALS['TCA']` with a new or empty array -- modifying a local copy and writing it back is fine, but never discard existing entries
- Add `declare(strict_types=1)` to `ext_emconf.php` (breaks TER publishing)
- Hardcode environment-specific values

## Getting Started

```bash
composer install    # Install dependencies
composer ci:test    # Full local check suite (lint, phpstan, rector, cgl, unit, js, functional)
make up             # DDEV: start environment + install TYPO3 v13/v14 + render docs
```

Make targets are DDEV/docs conveniences only (`setup`, `start`, `stop`, `up`, `ddev-restart`, `docs`, `docs-lint`, `docs-fix`); quality gates run through Composer scripts. For a full local environment with TYPO3 backend, see `.ddev/AGENTS.md`.

## Development Workflow

1. Create feature branch from `main`
2. Make changes with tests
3. Pre-commit hooks run automatically: phplint -> php-cs-fixer -> phpstan
4. Commit with conventional format (commitlint validates)
5. Push and create PR
6. CI runs: lint, phpstan, rector, unit tests, functional tests, E2E (all 6 E2E contexts blocking)
7. Address review feedback (Copilot + Gemini Code Assist auto-review)
8. Merge via merge queue with `gh pr merge --merge --auto`

## Commands

Pre-commit (husky) runs automatically: `composer ci:test:php:lint`, `composer ci:test:php:cgl` (PHP-CS-Fixer dry-run), `composer ci:test:php:phpstan` (level 10).

```bash
composer ci:test                # All checks: lint, phpstan, rector, cgl, unit, js-unit, functional
composer ci:test:php:unit       # Unit tests only
composer ci:test:php:functional # Functional tests (needs typo3DatabaseDriver=pdo_sqlite)
composer ci:test:js:unit        # JavaScript unit tests (Tests/JavaScript/)
composer ci:test:php:rector     # Rector dry-run check
Build/Scripts/runTests.sh -s e2e -t 13 -p 8.5   # E2E, Docker-based (-t 14 for TYPO3 v14)
```

## CI/CD

Workflows in `.github/workflows/` are thin wrappers over centralized reusable workflows: `netresearch/typo3-ci-workflows` (ci, e2e, release, republish, security) and `netresearch/.github` (CodeQL, gitleaks, zizmor, community automation). The per-repo test matrix lives in `.github/workflows/ci.yml`; CGL and coverage cell selection are decided by the central workflow. Keep E2E job names in sync with the branch ruleset's required status checks (see the comment in `ci.yml` -- renaming silently detaches the requirement).

**CI matrix**: PHP 8.2/8.3/8.4/8.5 x TYPO3 ^13.4.21/^14.3 (8 build combinations); E2E: TYPO3 v13/v14 x setup variants `fsc`/`core-only`/`bootstrap` = 6 blocking contexts.

## Code Quality Standards

- **PHP-CS-Fixer**: @Symfony ruleset with risky rules, `binary_operator_spaces` alignment enforced
- **PHPStan**: Level 10 with `phpstan-strict-rules`, `phpstan-deprecation-rules`, `phpat` architecture rules
- **Rector**: TYPO3-specific automated modernization (`ssch/typo3-rector`)
- **Fuzz/Mutation**: `composer ci:fuzz` (parsers), `composer ci:mutation` (Infection)

## Security & Safety

Defense-in-depth: caption XSS sanitization, file-visibility validation, URL protocol allowlist (`http:`, `https:`, `mailto:`, `tel:`, `t3:`), style-attribute exclusion, SVG sanitization, SSRF protection (`SecurityValidator`). Implementation details and the Core-vs-extension responsibility split: `Classes/AGENTS.md` (Security Notes) and [ADR-003](Documentation/Architecture/ADR-003-Security-Responsibility-Boundaries.rst).

## Index of scoped AGENTS.md

- `./Classes/AGENTS.md` -- PHP source classes (service architecture, DTOs, controllers)
- `./Tests/AGENTS.md` -- All test types (unit, functional, E2E, fuzz, mutation, architecture, JS)
- `./Documentation/AGENTS.md` -- RST documentation for docs.typo3.org
- `./Resources/AGENTS.md` -- Fluid templates, XLIFF translations, CKEditor plugin, CSS
- `./.ddev/AGENTS.md` -- DDEV local development environment

CI/CD has no scoped file: workflows are centralized (see CI/CD section above).

## When Instructions Conflict

Nearest AGENTS.md wins. User prompts override files.
- For PHP patterns, follow PSR-12 + TYPO3 CGL
- For TypoScript, follow TYPO3 conventions
- For JavaScript, follow CKEditor 5 plugin patterns in this repo

## Commit Signing

Signed commits are required: `git commit -S --signoff`. The `require-signed-commits` ruleset on the default branch rejects unsigned commits at merge time, and the DCO check additionally requires the `Signed-off-by` trailer. Quickest setup is SSH signing — register your SSH key as a *signing key* on your GitHub account, then `git config --global gpg.format ssh && git config --global user.signingkey ~/.ssh/<key>.pub`.
