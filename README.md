[![Latest GitHub release](https://img.shields.io/github/v/release/netresearch/t3x-rte_ckeditor_image?sort=semver&logo=github)](https://github.com/netresearch/t3x-rte_ckeditor_image/releases/latest)
[![License](https://img.shields.io/github/license/netresearch/t3x-rte_ckeditor_image)](https://github.com/netresearch/t3x-rte_ckeditor_image/blob/main/LICENSE)
[![CI](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/ci.yml/badge.svg)](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/ci.yml)
[![CodeQL](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/codeql.yml/badge.svg)](https://github.com/netresearch/t3x-rte_ckeditor_image/actions/workflows/codeql.yml)
[![OpenSSF Scorecard](https://img.shields.io/ossf-scorecard/github.com/netresearch/t3x-rte_ckeditor_image?label=openssf+scorecard&style=flat)](https://securityscorecards.dev/viewer/?uri=github.com/netresearch/t3x-rte_ckeditor_image)
[![OpenSSF Best Practices](https://www.bestpractices.dev/projects/11718/badge)](https://www.bestpractices.dev/projects/11718)
[![OpenSSF Baseline](https://www.bestpractices.dev/projects/11718/baseline)](https://www.bestpractices.dev/en/projects/11718#openssf_security_baseline)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%2010-brightgreen.svg?logo=php)](https://phpstan.org/)
[![Contributor Covenant](https://img.shields.io/badge/Contributor%20Covenant-3.0-4baaaa.svg)](CODE_OF_CONDUCT.md)
[![codecov](https://codecov.io/gh/netresearch/t3x-rte_ckeditor_image/graph/badge.svg)](https://codecov.io/gh/netresearch/t3x-rte_ckeditor_image)
[![SLSA 3](https://slsa.dev/images/gh-badge-level3.svg)](https://github.com/netresearch/t3x-rte_ckeditor_image/attestations)
[![Documentation](https://img.shields.io/badge/docs-docs.typo3.org-orange.svg?logo=typo3)](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/)

[![Composer](https://typo3-badges.dev/badge/rte_ckeditor_image/composer/shields.svg)](https://packagist.org/packages/netresearch/rte-ckeditor-image)
[![Total downloads](https://typo3-badges.dev/badge/rte_ckeditor_image/downloads/shields.svg)](https://extensions.typo3.org/extension/rte_ckeditor_image)
[![TYPO3 extension](https://typo3-badges.dev/badge/rte_ckeditor_image/extension/shields.svg)](https://extensions.typo3.org/extension/rte_ckeditor_image)
[![Stability](https://typo3-badges.dev/badge/rte_ckeditor_image/stability/shields.svg)](https://extensions.typo3.org/extension/rte_ckeditor_image)
[![TYPO3 versions](https://img.shields.io/badge/TYPO3-13%20%7C%2014-orange.svg?logo=typo3)](https://extensions.typo3.org/extension/rte_ckeditor_image)
[![Latest TER version](https://typo3-badges.dev/badge/rte_ckeditor_image/version/shields.svg)](https://extensions.typo3.org/extension/rte_ckeditor_image)
<!-- Generated with care at typo3-badges.dev -->

# RTE CKEditor Image — Image Support for CKEditor 5 in TYPO3

> A TYPO3 extension that restores and modernises rich-text image handling for **TYPO3 v13.4 LTS** and **v14.3 LTS**, built on **CKEditor 5** with full **File Abstraction Layer (FAL)** integration, image processing, accessibility metadata, and content security in mind.

<kbd>![Screenshot of the TYPO3 backend image properties dialog provided by the rte_ckeditor_image extension, showing FAL-backed image selection with width, height, alternative text, title, and quality controls](Documentation/Images/backend-image-properties-dialog.png?raw=true)</kbd>

---

## Table of Contents

- [Why this extension exists](#why-this-extension-exists)
- [Features at a glance](#features-at-a-glance)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage recipes](#usage-recipes)
- [Documentation](#documentation)
- [Security](#security)
- [Development](#development)
- [Verifying releases](#verifying-releases)
- [Contributing](#contributing)
- [License & credits](#license--credits)

---

## Why this extension exists

TYPO3 intentionally [removed rich-text image handling from the core in TYPO3 v10](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/Introduction/CoreRemoval.html). Editors lost the ability to insert FAL-backed images into bodytext via the rich-text editor — the recommended path became dedicated image content elements, which is not always what an editorial workflow needs.

This extension fills that gap **without recreating the legacy `rtehtmlarea` behaviour wholesale**. Images inserted from the rich-text editor go through the same **Magic Images** processing pipeline integrators already know from TSConfig: cropping, scaling, quality multipliers, lazy-loading, captions, and inline links. The CKEditor 5 plugin handles upcast/downcast cleanly, the backend uses TYPO3's modern file browser, and the rendered frontend output passes through TYPO3's parseFunc / Fluid template chain so site themes (Bootstrap Package, custom sitepackages, Content Blocks) can override it.

If your editorial workflow needs **inline images inside paragraphs** — with alt text, captions, links, popups, alignment, and high-DPI rendering — and you do not want to give up FAL or content security, this is the extension for you.

---

## Features at a glance

### Editor experience

- **TYPO3 FAL integration** — native file browser with full File Abstraction Layer support and the standard CKEditor 5 dialog.
- **Image properties dialog** — width, height, alt, title, with automatic aspect-ratio locking.
- **Quality selector** — choose **No Scaling**, **Standard (1.0x)**, **Retina (2.0x)**, **Ultra (3.0x)**, or **Print (6.0x)** per image; persisted as `data-quality` so behaviour is reproducible across re-edits.
- **True inline images** — cursor positioning before/after the image, mid-paragraph insertion, and round-trip-safe save/load.
- **SVG support** — intelligent dimension extraction from `viewBox` and width/height attributes; original vectors used as-is.

### Frontend rendering

- **Magic Images pipeline** — the same cropping, scaling, and processing as `rtehtmlarea`, configurable via Page TSConfig.
- **Lazy loading** — honours TYPO3's native `loading="lazy"` / `eager` / `auto` configuration.
- **Custom styles** — image styles integrated with the CKEditor 5 style system and `GeneralHtmlSupport`.
- **Fluid template overrides** — every fragment (figure, link, popup, caption) is a Fluid partial that themes can override; `figcaption` width is constrained to the image automatically.
- **Table images** — images inside CKEditor 5 tables get the full processing chain (max-width, zoom, `t3://` link resolution).

### Integrity & maintenance

- **Automatic softref tracking** — RTE image references are registered with the reference index across all RTE-enabled fields, so file moves, renames, and deletions stay consistent.
- **Image validation CLI** — `rte_ckeditor_image:validate` detects broken references, nested link wrappers, and stale paths; `--fix` repairs them.
- **Upgrade wizard** — runs the same validation as an Install Tool wizard for one-shot repair after major upgrades.
- **Preview renderer** — images stay visible in the page module preview, with explicit warnings when a reference is broken.
- **Content Blocks support** — a ViewHelper renders RTE image previews inside Content Blocks backend templates.

### Quality & security

- **PSR-14 events** for extensibility at every rendering decision point.
- **Defense-in-depth security**: caption XSS sanitization, file-visibility validation, URL protocol allowlist (`http`, `https`, `mailto`, `tel`, `t3`), SVG sanitization, SSRF protection on external image fetch, and `style`-attribute exclusion.
- **PHPStan level 10**, strict-rules, deprecation-rules, [phpat](https://github.com/carlosas/phpat) architecture rules — **zero baseline errors**.
- **SLSA Level 3** provenance attestation, signed release tags, [OpenSSF Best Practices](https://www.bestpractices.dev/projects/11718) participation.

---

## Requirements

| Component | Supported versions |
|-----------|--------------------|
| TYPO3 | **13.4.21+ LTS** or **14.3+ LTS** |
| PHP | **8.2**, 8.3, 8.4, 8.5 |
| Required TYPO3 extensions | `typo3/cms-rte-ckeditor` (extension key `rte_ckeditor`, shipped with TYPO3 core) |
| Required PHP extensions | `ext-dom`, `ext-libxml` |

> The CKEditor 5 plugin auto-integrates with **`GeneralHtmlSupport`** for image styles — no extra config required.

### Version compatibility

The `main` branch targets the current TYPO3 LTS releases. Earlier TYPO3 versions are served by dedicated, version-pinned branches. Composer selects the right series automatically from your installed TYPO3 version, or you can pin it explicitly (e.g. `composer require netresearch/rte-ckeditor-image:^12.0`).

| TYPO3 | Extension branch | Latest release | PHP | Status |
|-------|------------------|----------------|-----|--------|
| **13.4 LTS / 14.3 LTS** | [`main`](https://github.com/netresearch/t3x-rte_ckeditor_image/tree/main) | 13.10.0 | 8.2 – 8.5 | **Actively maintained** (CKEditor 5) |
| **12.4 LTS** | [`TYPO3_12`](https://github.com/netresearch/t3x-rte_ckeditor_image/tree/TYPO3_12) | 12.0.12 | 8.1+ | Maintained (bugfixes) |
| **11.5 LTS** | [`TYPO3_11`](https://github.com/netresearch/t3x-rte_ckeditor_image/tree/TYPO3_11) | 11.0.17 | 7.4+ | End of life — available, no further updates |
| **10.4 LTS** | [`TYPO3_10`](https://github.com/netresearch/t3x-rte_ckeditor_image/tree/TYPO3_10) | 10.2.5 | — | End of life — available, no further updates |

> The `TYPO3_12` and `main` branches use **CKEditor 5** (matching TYPO3 core's CKEditor 5 integration from v12 onward); the `TYPO3_11` and `TYPO3_10` branches use the legacy **CKEditor 4**. The features described below target `main` (TYPO3 v13/v14).

---

## Installation

### 1. Install via Composer

```shell
composer require netresearch/rte-ckeditor-image
```

### 2. Enable the Site Set

Activating the shipped **Site Set** wires up both the backend RTE preset (with the `insertimage` button) and the frontend TypoScript for image processing in one step. Add the extension to your site's `config.yaml`:

```yaml
# config/sites/<site>/config.yaml
dependencies:
  # This is the Site Set name (declared in the extension's
  # Configuration/Sets/RteCKEditorImage/config.yaml), not the extension key.
  - netresearch/rte-ckeditor-image
```

> **Using Bootstrap Package or another theme extension?** List `netresearch/rte-ckeditor-image` **after** them in your dependencies, so the RTE preset wins the load order.

### 3. (Alternative) Manual TypoScript import

If you prefer to manage TypoScript load order yourself rather than rely on Site Sets:

```typoscript
@import 'EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript'
```

### 4. (Optional) Custom RTE preset

If you maintain your own RTE preset and want to inject the image plugin into it:

```php
<?php
// EXT:my_ext/ext_localconf.php
$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['my_custom_preset']
    = 'EXT:my_ext/Configuration/RTE/Default.yaml';
```

```yaml
# EXT:my_ext/Configuration/RTE/Default.yaml
imports:
  - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
  - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

editor:
  config:
    toolbar:
      items:
        - heading
        - '|'
        - insertimage
        - link
        - '|'
        - bold
        - italic
```

```typoscript
# Page TSConfig
RTE.default.preset = my_custom_preset
```

---

## Configuration

The extension ships with sensible defaults — most installations need zero configuration. The four most commonly tuned settings live in **Extension Configuration** (Admin Tools → Settings → Extension Configuration → `rte_ckeditor_image`):

| Setting | Default | Purpose |
|---------|---------|---------|
| `fetchExternalImages` | on | When set, pasted external image URLs are downloaded into the current BE user's upload folder rather than left as cross-origin `<img>` tags. |
| `enableAutomaticRteSoftref` | on | Registers the `rtehtmlarea_images` soft reference on every RTE-enabled text field so images are tracked in the reference index automatically. |
| `enableAutomaticPreviewRenderer` | on | Registers an image-aware preview renderer for all records with RTE bodytext; warns about broken references in the page module. |
| `excludedTables` / `includedTablesOnly` | empty | Comma-separated table lists to scope the two automatic features above. `includedTablesOnly` is whitelist mode and overrides `excludedTables`. |

The full reference — every option, every edge case — lives in the manual: see [Advanced Configuration](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/Integration/Advanced-Configuration.html).

### Image `src` storage convention

RTE image `src` is persisted in **canonical site-root-relative form** (`/fileadmin/image.jpg`, with a leading slash). Slashless storage (`fileadmin/image.jpg`) is treated as a defect and repaired by the validator — modern TYPO3 does not emit `<base href>`, so a slashless `src` resolves against the current page URL in the browser and breaks on every non-root page. External references (`https://…`, `data:…`, `//cdn.example.com/…`) pass through unchanged.

**Subpath installs** (TYPO3 served from `/~user/`, `/subsite/`, etc.) must set `config.absRefPrefix = /subsite/`. TYPO3's render chain prepends it to leading-slash paths at output time, so storage stays identical to a site-root install.

See [ADR-004: Image `src` Storage Convention](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/Architecture/ADR-004-Image-Src-Storage-Convention.html) for the full rationale.

---

## Usage recipes

The snippets below cover the configuration questions that arrive most often. The [manual](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/) covers each topic in depth.

### Set maximum width / height

Magic Images obey the limits configured in Page TSConfig. Defaults are conservative — raise them for editorial sites with large hero images:

```typoscript
# Page TSConfig
RTE.default.buttons.image.options.magic {
    # Default: 300
    maxWidth = 1020
    # Default: 1000
    maxHeight = 800
}
```

> If TSConfig from a custom template extension does not take effect, place the settings directly on the root page record.

### Lightbox via fluid_styled_content

```typoscript
# Template Constants
styles.content.textmedia.linkWrap.lightboxEnabled = 1
```

### Default CSS class for every RTE image

```typoscript
# TypoScript Setup
lib.parseFunc_RTE {
    nonTypoTagStdWrap.HTMLparser.tags.img.fixAttrib.class {
        default = my-custom-class
    }
}
```

### Native browser lazy loading

```typoscript
# Template Constants — type=options[lazy,eager,auto]
styles.content.image.lazyLoading = lazy
```

See [Feature 90426 (Native lazy loading)](https://docs.typo3.org/c/typo3/cms-core/master/en-us/Changelog/10.3/Feature-90426-Browser-nativeLazyLoadingForImages.html) for the underlying TYPO3 behaviour.

### Image Quality selector

The image dialog includes a **Quality** dropdown that lets editors trade file size against pixel density per image:

| Option | Multiplier | Use case |
|--------|------------|----------|
| No Scaling | 1.0x (no processing) | Newsletters, PDFs, SVGs — keep original file |
| Standard | 1.0x | Match display dimensions exactly |
| Retina | 2.0x | High-DPI displays (default) |
| Ultra | 3.0x | Hero images, key visuals |
| Print | 6.0x | Print-quality output, professional photography |

The choice is stored in the `data-quality` HTML attribute, so it survives re-edits and is reproducible across environments. See the [Image Quality Selector documentation](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/CKEditor/Image-Quality-Selector.html) for technical details, use cases, and the migration guide from legacy `noScale`.

### Skip processing globally (legacy `noScale`)

For all-original-images integrations (newsletter exports, PDF-bound content), keep originals globally:

```typoscript
# TypoScript Setup — enable globally for all RTE images
lib.parseFunc_RTE.tags.img.noScale = 1

# Optional safety net: do not serve very large originals silently
lib.parseFunc_RTE.tags.img.noScale {
    maxFileSizeForAuto = 2000000  # 2 MB
}
```

The modern, per-image equivalent is the Quality selector set to **No Scaling** — better UX, same effect (`data-quality="none"`). Processing is skipped automatically when requested dimensions equal the original, when the file is an SVG, or when Quality is set to **No Scaling**.

### Restrict allowed file extensions

By default the extensions listed in `$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']` are allowed. Override per RTE preset:

```yaml
editor:
  externalPlugins:
    typo3image:
      allowedExtensions: "jpg,jpeg,png"
```

---

## Documentation

This project maintains a single source of truth — the manual published on docs.typo3.org — supplemented by AI-readable context inside the repository.

### Official manual — docs.typo3.org

The published manual is the canonical reference for integrators, administrators, and editors. It covers installation, configuration, integration, troubleshooting, the developer API, the CKEditor 5 plugin internals, and the architecture decision records.

📘 **[docs.typo3.org/p/netresearch/rte-ckeditor-image](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/)**

Source: [`Documentation/`](Documentation/) (reStructuredText, automatically built and published).

### In-repository guides

- **[AGENTS.md](AGENTS.md)** — development guide, build commands, code standards, and PR checklist (audience: humans and AI agents).
- **[CONTRIBUTING.md](CONTRIBUTING.md)** — contribution workflow, commit conventions, review process.
- **[SECURITY.md](SECURITY.md)** — security policy and coordinated disclosure.
- **[Documentation/AGENTS.md](Documentation/AGENTS.md)** — TYPO3 documentation system guide.
- **`claudedocs/`** *(gitignored)* — AI development context generated per session.

---

## Security

This extension implements defense-in-depth security at every boundary where untrusted RTE content reaches a renderer or a storage path:

- **Caption XSS prevention** — all editor-supplied captions are sanitized via `ImageResolverService::sanitizeCaption()`.
- **File visibility validation** — files in non-public storages are blocked at render time.
- **URL protocol allowlist** — only `http:`, `https:`, `mailto:`, `tel:`, `t3:` pass; `javascript:`, `vbscript:`, `data:text/html` are rejected.
- **SVG sanitization** — SVG data URIs are routed through TYPO3 Core's `SvgSanitizer`.
- **SSRF protection** — the external image fetcher (when `fetchExternalImages` is on) refuses private IPs and DNS-rebinding payloads.
- **Style attribute exclusion** — `style` is excluded from the allowed HTML attribute list to prevent CSS injection.
- **Whitespace-smuggling hardening** — `<img src>` values are stripped of leading ASCII whitespace before protocol-relative checks, blocking `" //evil.com/x.jpg"`-style payloads (CWE-20 / CWE-176, defense-in-depth).

Responsibilities delegated to TYPO3 Core (and **not** re-implemented here): SVG file upload sanitization, file extension and MIME validation, and image-processing security inside `GraphicalFunctions`. See [ADR-003: Security Responsibility Boundaries](https://docs.typo3.org/p/netresearch/rte-ckeditor-image/main/en-us/Architecture/ADR-003-Security-Responsibility-Boundaries.html) for the full division.

To report a vulnerability, please follow [SECURITY.md](SECURITY.md). **Do not open public issues for security findings.**

---

## Development

### DDEV environment (recommended for full backend testing)

The repository ships a complete DDEV setup with TYPO3 v13 and v14 side-by-side, Bootstrap Package preinstalled, and rendered documentation served locally:

```bash
git clone https://github.com/netresearch/t3x-rte_ckeditor_image.git
cd t3x-rte_ckeditor_image
make up                      # Start DDEV + complete setup (one command)
```

| URL | Purpose |
|-----|---------|
| `https://rte-ckeditor-image.ddev.site/` | Overview landing page |
| `https://docs.rte-ckeditor-image.ddev.site/` | Rendered documentation |
| `https://v13.rte-ckeditor-image.ddev.site/` | TYPO3 v13 instance |
| `https://v14.rte-ckeditor-image.ddev.site/` | TYPO3 v14 instance |
| `https://v13.rte-ckeditor-image.ddev.site/typo3/` | v13 backend (login `admin` / `Joh316!!`) |
| `https://v14.rte-ckeditor-image.ddev.site/typo3/` | v14 backend (same credentials) |

```bash
make start                   # Start DDEV environment
make setup                   # Complete setup (docs + install)
make docs                    # Render extension documentation
```

The DDEV environment is preconfigured for testing image insertion in the RTE, click-to-enlarge behaviour, caption WYSIWYG editing, image alignment, and image-style application.

### Local development (no DDEV)

```bash
composer install
make help                    # Show all available targets

make lint                    # Run all linters (phplint + phpstan + rector + cgl + docs)
make format                  # Auto-fix code style (php-cs-fixer)
make test                    # Run tests
make ci                      # Full CI pipeline (pre-commit equivalent)
```

The full development guide — code standards, CI matrix, PR checklist, security boundaries — lives in [AGENTS.md](AGENTS.md).

### Quality gates

- **PHPStan level 10** with `phpstan-strict-rules`, `phpstan-deprecation-rules`, and `phpat` architecture rules — zero baseline errors.
- **PHP-CS-Fixer** (`@Symfony` ruleset, risky rules enabled).
- **Rector** for automated TYPO3 modernization (`ssch/typo3-rector`).
- **PHPUnit** unit and functional suites with SQLite-backed functional tests.
- **Playwright** E2E suite running against both v13 and v14 in CI.
- **Infection** mutation testing.
- **php-fuzzer** fuzz harnesses for `ImageAttributeParser` and `RteImageSoftReferenceParser`.
- **CI matrix**: PHP 8.2 / 8.3 / 8.4 / 8.5 × TYPO3 ^13.4 / ^14.3 — 8 combinations for build, plus blocking E2E on v13 and v14.

---

## Verifying releases

Every release tag is **GPG-signed** and ships with **SLSA Level 3** provenance attestation for supply-chain integrity.

### Verify tag signature

```bash
git tag -v <release-tag>
```

### Verify SLSA provenance

```bash
gh attestation verify <downloaded-file> \
  --repo netresearch/t3x-rte_ckeditor_image
```

See [SLSA](https://slsa.dev) for the framework overview and [the attestations index](https://github.com/netresearch/t3x-rte_ckeditor_image/attestations) for this project's published provenance.

### Distribution channels

- **Composer** — [packagist.org/packages/netresearch/rte-ckeditor-image](https://packagist.org/packages/netresearch/rte-ckeditor-image) (recommended)
- **TYPO3 Extension Repository (TER)** — [extensions.typo3.org/extension/rte_ckeditor_image](https://extensions.typo3.org/extension/rte_ckeditor_image) (auto-published via GitHub Actions on tag)
- **GitHub Releases** — [releases](https://github.com/netresearch/t3x-rte_ckeditor_image/releases) (source archives + provenance attestations)

---

## Contributing

Contributions of code, documentation, translations, and bug reports are welcome. The extension is currently translated into **31 languages** via [Crowdin](https://crowdin.com).

Before opening a PR, please:

1. Read [CONTRIBUTING.md](CONTRIBUTING.md) for the workflow, branch model, and commit conventions ([Conventional Commits](https://www.conventionalcommits.org/), validated by commitlint).
2. Run `make ci` locally to mirror the pre-commit and CI checks.
3. Add tests for new code paths — unit, functional, or E2E as appropriate.
4. Keep PRs focused (~300 net LOC is a good target).

This project follows the [Contributor Covenant 3.0](CODE_OF_CONDUCT.md). For security findings, see [SECURITY.md](SECURITY.md).

---

## License & credits

Licensed under the [**GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later)**](LICENSE).

Developed and maintained by [**Netresearch DTT GmbH**](https://www.netresearch.de/).

Repository home: [github.com/netresearch/t3x-rte_ckeditor_image](https://github.com/netresearch/t3x-rte_ckeditor_image).
