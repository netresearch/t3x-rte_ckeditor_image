# AI Agent Development Guide

<!-- Managed by agent: keep sections & order; edit content, not structure. Last updated: 2026-02-03 -->

**Project:** rte_ckeditor_image - TYPO3 CKEditor 5 Image Extension
**Version:** 13.5.0 | **TYPO3:** ^13.4.21 || ^14.0 | **PHP:** ^8.2

## Precedence

When instructions conflict, **nearest AGENTS.md wins**. Scoped files override this root.

## Commands (Verified)

| Command | Purpose | ~Time |
|---------|---------|-------|
| `composer ci:test` | Full CI suite | 3min |
| `composer ci:test:php:unit` | Unit tests | 10s |
| `composer ci:test:php:functional` | Functional tests | 60s |
| `composer ci:cgl` | Fix code style | 5s |

## File Map

| Directory | Purpose |
|-----------|---------|
| `Classes/` | PHP backend (controllers, services) |
| `Resources/Public/JavaScript/Plugins/` | CKEditor 5 plugin |
| `Tests/` | Unit, Functional, E2E, Fuzz tests |
| `Documentation/` | TYPO3 RST docs |

## Boundaries

**Always:** Sign commits (`-S`), run `composer ci:test`, conventional commits
**Never:** Secrets in VCS, `gh release create` without signed tag first

## Scope Index

| Scope | Focus |
|-------|-------|
| [Classes/AGENTS.md](Classes/AGENTS.md) | PHP, DI, TYPO3 patterns |
| [Tests/AGENTS.md](Tests/AGENTS.md) | PHPUnit, Vitest, Playwright |
| [Documentation/AGENTS.md](Documentation/AGENTS.md) | RST documentation |
| [Resources/AGENTS.md](Resources/AGENTS.md) | CKEditor JS plugin |

## Quick Links

- **Repository:** https://github.com/netresearch/t3x-rte_ckeditor_image
- **Docs:** https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/
