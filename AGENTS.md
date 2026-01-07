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
