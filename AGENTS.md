<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-03-26 | Last verified: 2026-03-26 -->

# AGENTS.md — TYPO3_12 Branch

**Precedence:** the **closest `AGENTS.md`** to the files you're changing wins. Root holds global defaults only.

## Commands (verified)
> Source: composer.json

| Task | Command | ~Time |
|------|---------|-------|
| Typecheck | `composer ci:test:php:phpstan` | ~10s |
| Lint | `composer ci:test:php:lint` | ~5s |
| Format | `.Build/bin/php-cs-fixer fix` | ~5s |
| Rector | `composer ci:test:php:rector` | ~5s |
| Test (unit) | `composer ci:test:php:unit` | ~5s |
| Test (functional) | `composer ci:test:php:functional` | ~30s |
| Test (all) | `composer ci:test` | ~60s |

> If commands fail, verify against `composer.json` scripts section.

## Workflow
1. **Before coding**: Read nearest `AGENTS.md` + check Golden Samples
2. **After each change**: Run smallest relevant check (lint → phpstan → single test)
3. **Before committing**: Run `composer ci:test` if changes affect >2 files
4. **Before claiming done**: Show test output as evidence

## File Map
```
Classes/          -> PHP source (controllers, hooks, utils)
Configuration/    -> TYPO3 config (Services.yaml, TCA, RTE presets, TypoScript)
Resources/        -> Fluid templates, XLIFF translations, JS plugin, icons
Tests/            -> Unit + functional tests
Build/            -> PHPStan, PHPUnit configs, CI tooling
.github/workflows -> CI/CD pipelines
.ddev/            -> Local development environment
```

## Golden Samples (follow these patterns)

| For | Reference | Key patterns |
|-----|-----------|--------------|
| Controller | `Classes/Controller/SelectImageController.php` | PSR-7, FAL access, permission checks |
| DB Hook | `Classes/Database/RteImagesDbHook.php` | Image processing, soft references |
| Unit test | `Tests/Unit/Controller/SelectImageControllerTest.php` | Mocking FAL, permission scenarios |
| Functional test | `Tests/Functional/Controller/SelectImageControllerTest.php` | TYPO3 testing framework |
| RTE preset | `Configuration/RTE/Default.yaml` | CKEditor 5 YAML config |

## Heuristics (quick decisions)

| When | Do |
|------|-----|
| Adding PHP class | PSR-4 in `Classes/`, `declare(strict_types=1)`, `final class` |
| Committing | Conventional Commits: `type(scope): subject` |
| Merging PRs | Merge commit (squash disabled), delete branch after |
| Adding dependency | Ask first — must support PHP 8.1+ |
| Fixing a bug | Backport-safe only — no v13 patterns (no DI, no service architecture) |
| PHPStan error on deprecated API | Add to baseline if it's a v12-only deprecation, fix if possible |
| Unsure about pattern | Check Golden Samples above |

## Repository Settings
- **Branch**: `TYPO3_12` (maintenance only — bug fixes, security, dependency updates)
- **Branch protection**: PR + CI required, no direct push
- **CI matrix**: PHP 8.1 / 8.2 / 8.3 / 8.4 × TYPO3 ^12.4
- **Merge strategy**: merge commit (squash not allowed)

## Boundaries

### Always Do
- Run `composer ci:test` before merging
- Add tests for new code paths
- Use conventional commit format: `type(scope): subject`
- Validate all user inputs — especially HTML attributes from RTE content
- Follow PSR-12 + TYPO3 CGL coding standards

### Ask First
- Adding new Composer dependencies
- Modifying CI/CD workflows (`.github/workflows/`)
- Changing public API signatures on controllers or hooks
- Backporting changes from `main` branch

### Never Do
- Push directly to `TYPO3_12` (branch protection)
- Add new features (maintenance branch only)
- Introduce dependencies requiring PHP >8.4
- Add `declare(strict_types=1)` to `ext_emconf.php` (breaks TER)
- Commit `.Build/vendor/`, `node_modules/`, or `composer.lock`
- Backport service-based architecture from `main` (v13+)

## Codebase State
- **PHPStan baseline**: 9 deprecated-class entries (resolved in v13, unfixable in v12): `AbstractPlugin`, `MagicImageService`, `getPagesTSconfig()`
- **Architecture**: Legacy controller/hook pattern (v13+ uses service-based architecture — do not backport)
- **No EventListener**: v12 branch uses `ext_localconf.php` hooks, not PSR-14 events for rendering

## Terminology

| Term | Means |
|------|-------|
| FAL | File Abstraction Layer — TYPO3's file management API |
| TER | TYPO3 Extension Repository — public extension registry |
| RTE | Rich Text Editor (CKEditor 5 in TYPO3 v12+) |
| parseFunc_RTE | TypoScript function that processes RTE HTML output for frontend |
| Magic Image | Legacy TYPO3 concept for auto-processed images in RTE |

## Release Process

See [RELEASE.md](RELEASE.md): bump `ext_emconf.php` via PR → merge → `gh release create` → verify Packagist + TER.

## Index of scoped AGENTS.md

- `./Classes/AGENTS.md` — PHP source: controllers, hooks, utilities, coding patterns
- `./Tests/AGENTS.md` — Testing: unit, functional, PHPStan, test conventions

## When instructions conflict
The nearest `AGENTS.md` wins. Explicit user prompts override files.
- For PHP patterns, follow PSR-12 + TYPO3 CGL
- This branch follows TYPO3 v12 conventions, not v13+
