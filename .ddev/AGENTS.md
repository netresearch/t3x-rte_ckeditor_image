<!-- Managed by agent: keep sections and order; edit content, not structure. Last updated: 2026-02-13 -->

# AGENTS.md -- .ddev

## Overview

DDEV local development environment for multi-version TYPO3 testing. Supports v13 and v14 simultaneously via separate virtual hosts.

## Configuration

- **Project name**: `rte-ckeditor-image`
- **Type**: PHP (no CMS type -- extension development)
- **PHP**: 8.4
- **Webserver**: Apache-FPM
- **no_project_mount**: true (custom mount setup)
- **Hostnames**: `rte-ckeditor-image.ddev.site`, `v13.rte-ckeditor-image.ddev.site`, `v14.rte-ckeditor-image.ddev.site`, `docs.rte-ckeditor-image.ddev.site`

## Custom Commands

### Host Commands (`.ddev/commands/host/`)

| Command | Purpose |
|---------|---------|
| `ddev setup` | Complete setup: render docs + install v13 + install v14 |
| `ddev docs` | Render extension documentation |
| `ddev test-e2e` | Run E2E tests via Docker |

### Web Commands (`.ddev/commands/web/`)

| Command | Purpose |
|---------|---------|
| `ddev install-v13` | Install TYPO3 v13.4 LTS instance |
| `ddev install-v14` | Install TYPO3 v14.0 instance |
| `ddev install-all` | Install both v13 and v14 |

## Quick Start

```bash
make up          # Start DDEV + run full setup (docs + v13 + v14)
# or manually:
ddev start       # Start DDEV environment
ddev setup       # Install both TYPO3 versions + render docs
```

## Make Targets (convenience wrappers)

| Target | Command | Description |
|--------|---------|-------------|
| `make up` | `ddev start && ddev setup` | One command to start everything |
| `make start` | `ddev start` | Start DDEV |
| `make stop` | `ddev stop` | Stop DDEV |
| `make setup` | `ddev setup` | Full setup |
| `make docs` | `ddev docs` | Render documentation |
| `make install-v13` | `ddev install-v13` | Install TYPO3 v13 only |
| `make install-v14` | `ddev install-v14` | Install TYPO3 v14 only |

## URLs (when running)

| Service | URL |
|---------|-----|
| TYPO3 v13 Backend | `https://v13.rte-ckeditor-image.ddev.site/typo3/` |
| TYPO3 v14 Backend | `https://v14.rte-ckeditor-image.ddev.site/typo3/` |
| Documentation | `https://docs.rte-ckeditor-image.ddev.site/` |

## Conventions

- Keep `config.yaml` minimal; use docker-compose overrides for complexity
- Custom commands include description headers
- `#ddev-generated` comment marks files managed by DDEV (do not edit)
- Works on macOS, Linux, and Windows (WSL2)

## PR Checklist

- [ ] `ddev start` works after changes
- [ ] Custom commands have descriptions
- [ ] No hardcoded paths or credentials
- [ ] Both v13 and v14 installations work
