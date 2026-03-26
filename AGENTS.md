<!-- FOR AI AGENTS - Human readability is a side effect, not a goal -->
<!-- Managed by agent: keep sections and order; edit content, not structure -->
<!-- Last updated: 2026-03-26 | Last verified: 2026-03-26 -->

# AGENTS.md — TYPO3_12 Branch

**Precedence:** The **closest AGENTS.md** to changed files wins. Root holds global defaults only.

## Project Overview

TYPO3 CKEditor 5 extension that adds FAL (File Abstraction Layer) image support to the rich text editor.
This is the **maintenance branch** for TYPO3 v12 LTS. Only bug fixes and security patches — no new features.

- **Branch**: `TYPO3_12` (maintenance only)
- **Package**: `netresearch/rte-ckeditor-image` (Composer) / `rte_ckeditor_image` (TER)
- **Namespace**: `Netresearch\RteCKEditorImage\`
- **Repository**: [github.com/netresearch/t3x-rte_ckeditor_image](https://github.com/netresearch/t3x-rte_ckeditor_image)
- **Tech Stack**: PHP 8.1–8.4, TYPO3 ^12.4, CKEditor 5
- **License**: AGPL-3.0-or-later

## Branch Rules

- **Maintenance only**: Bug fixes, security patches, dependency updates. No new features.
- **Do not backport** architectural changes from `main` (v13+). The v12 branch uses the legacy controller/hook architecture, not the service-based architecture on main.
- **PHPStan baseline** contains deprecated-class entries that are resolved in v13 but cannot be fixed in v12 (e.g. `AbstractPlugin`, `MagicImageService`, `getPagesTSconfig()`).

## Release Process

See [RELEASE.md](RELEASE.md) for the full release workflow including version bumping, PR creation, GitHub release, and distribution verification (Packagist + TER).

**Quick reference:**
1. Bump version in `ext_emconf.php` via PR to `TYPO3_12`
2. Wait for CI, merge PR
3. Create GitHub release: `gh release create vX.Y.Z --target TYPO3_12 --title "vX.Y.Z"`
4. Credit bug reporters and contributors in release notes
5. Verify Packagist and TER picked up the release

## Global Rules

- Conventional Commits: `type(scope): subject`
- Keep PRs small
- PHPStan with baseline — no new errors allowed
- `declare(strict_types=1)` in all PHP files except `ext_emconf.php`
- TYPO3 extensions MUST NOT commit `composer.lock`

## Boundaries

### Always Do

- Run CI checks before merging: lint, phpstan, rector, unit, functional
- Add tests for new code paths
- Use conventional commit format
- Validate all user inputs — especially HTML attributes from RTE content

### Never Do

- Push directly to `TYPO3_12` (branch protection requires PR + CI)
- Add new features (maintenance branch)
- Introduce dependencies not available for PHP 8.1
- Add `declare(strict_types=1)` to `ext_emconf.php`
- Commit `.Build/vendor/`, `node_modules/`, or generated files

## Commands

```bash
composer ci:test                # All checks: lint, phpstan, rector, unit, functional
composer ci:test:php:lint       # PHP syntax check
composer ci:test:php:phpstan    # PHPStan analysis
composer ci:test:php:rector     # Rector dry-run
composer ci:test:php:unit       # Unit tests
composer ci:test:php:functional # Functional tests (needs typo3DatabaseDriver=pdo_sqlite)
```

## CI Matrix

PHP 8.1 / 8.2 / 8.3 / 8.4 on TYPO3 ^12.4 (4 combinations).

## When Instructions Conflict

Nearest AGENTS.md wins. User prompts override files.
- For PHP patterns, follow PSR-12 + TYPO3 CGL
- This branch follows TYPO3 v12 conventions, not v13+
