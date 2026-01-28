# AI Agent Development Guide

<!-- Managed by agent: keep sections & order; edit content, not structure. Last updated: 2025-11-10 -->

**Project:** rte_ckeditor_image - TYPO3 CKEditor 5 Image Extension
**Version:** 13.0.0 (stable)
**License:** AGPL-3.0-or-later
**Organization:** Netresearch DTT GmbH

## 📋 Documentation Structure

This project uses a three-tier documentation system:

- **[claudedocs/](claudedocs/)** - AI session context (Markdown, gitignored, temporary)
- **[Documentation/](Documentation/)** - Official TYPO3 docs (RST, published, permanent)
- **Root** - Project essentials (README, CONTRIBUTING, SECURITY, LICENSE)

See **[claudedocs/INDEX.md](claudedocs/INDEX.md)** for AI context navigation.

## 🎯 Global Rules

- **PRs:** Keep ≤300 net LOC changed
- **Commits:** Use conventional format: `type(scope): message`
- **Security:** No secrets in VCS (use .env, LocalConfiguration.php)
- **Quality Gate:** All code must pass `composer ci:test` before commit
- **Releases:** ALWAYS create signed tags - NEVER use `gh release create` alone

## 🏷️ Release Process (MANDATORY)

### Pre-Release Checklist

1. **Update `ext_emconf.php`** - Bump version number (e.g., `13.4.1` → `13.4.2`)
2. **Update `CHANGELOG.md`** - Add new version section with changes
3. **Commit preparation** - `git commit -S -m "chore: prepare vX.Y.Z release"`
4. **Verify CI passes** - All checks must be green on main

### Creating Signed Releases

**CRITICAL:** Tags/releases created via `gh release create` are UNSIGNED. Always use this process:

```bash
# 1. Update version files
# - ext_emconf.php: 'version' => 'X.Y.Z'
# - CHANGELOG.md: Add [X.Y.Z] section

# 2. Commit release preparation
git add ext_emconf.php CHANGELOG.md
git commit -S -m "chore: prepare vX.Y.Z release"
git push origin main

# 3. Create signed tag locally
git tag -s vX.Y.Z -m "vX.Y.Z"

# 4. Push signed tag to remote
git push origin vX.Y.Z

# 5. Create release on existing tag (tag already exists, so it stays signed)
gh release create vX.Y.Z --title "vX.Y.Z" --notes "Release notes..."
```

**NEVER:** Run `gh release create` without first creating and pushing a signed tag.

### Post-Release Verification

- [ ] GitHub release visible: https://github.com/netresearch/t3x-rte_ckeditor_image/releases
- [ ] TER upload complete: https://extensions.typo3.org/extension/rte_ckeditor_image
- [ ] Packagist updated: https://packagist.org/packages/netresearch/rte-ckeditor-image

## ⚡ Pre-Commit Checklist

```bash
# Quality checks (lint + phpstan + rector + cgl)
composer ci:test

# Run FULL test suite (ALWAYS run before committing!)
composer ci:test:php:unit  # Unit tests
ddev exec "cd /var/www/rte_ckeditor_image && typo3DatabaseHost=db typo3DatabaseUsername=root typo3DatabasePassword=root typo3DatabaseName=func_test .Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml"  # Functional tests

# Auto-fix code style
composer ci:cgl
```

**IMPORTANT:** ddev/containers are ALWAYS available in this project. Never skip functional tests!

## 📂 Scoped Guides (Nearest Wins)

When working in specific areas, consult the scoped AGENTS.md in that directory:

- **[Classes/AGENTS.md](Classes/AGENTS.md)** - PHP backend development (controllers, DI, architecture)
- **[Tests/AGENTS.md](Tests/AGENTS.md)** - PHPUnit testing (functional, unit, coverage)
- **[Documentation/AGENTS.md](Documentation/AGENTS.md)** - TYPO3 RST documentation system

**Conflict Resolution:** When instructions conflict, the **nearest AGENTS.md** to your working files takes precedence.

## 🔗 Quick Links

- **Repository:** https://github.com/netresearch/t3x-rte_ckeditor_image
- **Published Manual:** https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/
- **Issues:** https://github.com/netresearch/t3x-rte_ckeditor_image/issues
