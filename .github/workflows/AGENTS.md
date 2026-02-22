<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-02-22 -->

# AGENTS.md -- .github/workflows

## Overview

GitHub Actions CI/CD workflows for testing, releasing, and supply chain security.

## Workflow Files

| File | Purpose | Triggers |
|------|---------|----------|
| `ci.yml` | Main CI: lint, phpstan, rector, CGL, unit, functional, JS unit, E2E | push/PR to main, merge_group |
| `codeql-analysis.yml` | CodeQL static analysis (JavaScript) | push/PR, weekly schedule |
| `create-release.yml` | Automated release creation from tags | manual (workflow_dispatch) |
| `publish-to-ter.yml` | Publish to TYPO3 Extension Repository | release published |
| `slsa-provenance.yml` | SLSA Level 3 supply chain provenance | after release workflow |
| `scorecard.yml` | OpenSSF Scorecard security assessment | weekly schedule |
| `auto-merge-deps.yml` | Auto-merge Dependabot/Renovate PRs | pull_request (dependency bots) |
| `release-labeler.yml` | Label PRs with release version + create announcement discussion | release published |
| `add-to-project.yml` | Add issues to Netresearch TYPO3 project board | issues opened |

## CI Pipeline (ci.yml)

### Build Matrix

- **PHP versions**: 8.2, 8.3, 8.4, 8.5
- **TYPO3 versions**: ^13.4, ^14.0
- **Total**: 8 build combinations
- **CGL**: only on PHP 8.2 + TYPO3 ^13.4 (code style is PHP version independent)
- **Coverage**: only on PHP 8.5 + TYPO3 ^14.0
- **JS tests**: only on PHP 8.5 + TYPO3 ^14.0

### Build Steps (in order)

1. Checkout
2. TER compatibility check (`ext_emconf.php` must not have `strict_types`)
3. Setup PHP + Composer
4. Validate `composer.json`
5. Security audit (`composer audit`)
6. XLIFF validation
7. PHP lint
8. PHP-CS-Fixer (CGL, dry-run)
9. PHPStan (level 10, GitHub error format)
10. Rector (dry-run)
11. Unit tests
12. JavaScript unit tests (Vitest)
13. Functional tests (SQLite driver)
14. Coverage upload to Codecov

### E2E Job

- Depends on build job completion
- Docker-based via `Build/Scripts/runTests.sh`
- TYPO3 v13: blocking (must pass)
- TYPO3 v14: blocking (must pass, `continue-on-error` removed in #627)
- Artifacts: test results uploaded for 7 days

## Branch Protection

- **Main branch**: merge queue required
- **Required checks**: build (all 8 PHP/TYPO3 combos), e2e (v13, v14), CodeQL
- **Require up-to-date**: yes
- **Delete branch on merge**: yes
- **Merge strategy**: merge commits only (no squash, no rebase-merge)

## Conventions

- **Pin actions to full SHA** with version comment: `uses: actions/checkout@abc123 # v4.2.2`
- **Minimal permissions**: `contents: read` default, escalate per-job only
- **Caching**: Composer cache (keyed by PHP+TYPO3+timestamp), Docker layers, npm
- **Secrets**: `CODECOV_TOKEN`, `TYPO3_ORG_TOKEN` (TER publishing)
- When updating action versions, always fetch latest tag + SHA via API, never guess

## Dependency Updates

- **Dependabot**: GitHub Actions (weekly), Composer (daily)
- **Renovate**: `config:recommended` with `:automergeMinor` and `:automergePatch`
- Auto-merge workflow handles bot PRs

## SLSA Provenance

- Uses `slsa-framework/slsa-github-generator` for Level 3 provenance
- Triggered by `workflow_run` after release workflow
- Format: `sha256sum` raw output, base64-encoded (NOT JSON array)
- Uses `compile-generator: true` to build from source (avoids binary fetch issues)

## Release Announcements

The `release-labeler.yml` workflow includes an `announce-release` job that:
1. Resolves the "Announcements" discussion category ID dynamically by name
2. Checks first 100 discussions for duplicates (by exact title match)
3. Creates a discussion with the release notes, linked back to the GitHub release
4. Uses `-F body=@file` to safely pass release body content (avoids shell expansion)

**Permissions required**: `discussions: write` (in addition to existing `issues: write`, `pull-requests: write`)

## PR/Commit Checklist

- [ ] Actions pinned to full SHA with version comment
- [ ] Permissions block uses minimal required permissions
- [ ] Secrets are not exposed in logs
- [ ] Matrix strategy covers all required PHP/TYPO3 combinations
- [ ] Caching configured for all dependencies
- [ ] `continue-on-error` used only where appropriate
