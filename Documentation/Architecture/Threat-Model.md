<!--
Copyright (c) 2025-2026 Netresearch DTT GmbH
SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Threat Model: rte_ckeditor_image

**Date:** 2026-02-18
**Version:** 13.x
**Related:** [ADR-003: Security Responsibility Boundaries](ADR-003-Security-Responsibility-Boundaries.rst), [Security Validation Checklist](Security-Validation-Checklist.md)

## System Overview

This extension provides image handling for CKEditor in TYPO3. It operates in two contexts:

- **Backend**: Image dialog, file browser integration, image processing on save
- **Frontend**: HTML rendering of stored image markup via TypoScript/Fluid

## Trust Boundaries

```
┌─────────────────────────────────────────────┐
│ Browser (Untrusted)                         │
│  CKEditor 5 + typo3image plugin             │
│  User-supplied: captions, alt text, URLs    │
└────────────────┬────────────────────────────┘
                 │ HTTP (TYPO3 Backend)
┌────────────────▼────────────────────────────┐
│ TYPO3 Backend (Trusted)                     │
│  SelectImageController — file browser       │
│  RteImagesDbHook — save-time processing     │
│  ImageResolverService — render-time logic   │
├─────────────────────────────────────────────┤
│ TYPO3 Core (Trusted, out of scope)          │
│  FAL, GraphicalFunctions, SvgSanitizer      │
└────────────────┬────────────────────────────┘
                 │ File I/O, DB
┌────────────────▼────────────────────────────┐
│ Storage / Database (Protected)              │
│  sys_file, sys_file_reference, tt_content   │
└─────────────────────────────────────────────┘
```

## Threat Analysis

### T1: Cross-Site Scripting (XSS) via Captions

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Backend editor injects `<script>` or event handlers in image caption |
| **Impact** | Session hijacking, data theft on frontend visitors |
| **Likelihood** | Medium (requires backend access) |
| **Mitigation** | Figcaption markup is rebuilt from an inline allowlist (`br`, `strong`, `b`, `em`, `i`, `a` with `href`/`target`/`title`/`rel`) by `CaptionSanitizer` via `ImageResolverService::sanitizeCaptionHtml()`: other elements become text, other attributes (`style`, `on*`) are dropped, link hrefs must pass the protocol allowlist (`javascript:`/`data:` links become text, unresolved `t3://` links become text). Plain-text `data-caption` captions use `htmlspecialchars($caption, ENT_QUOTES \| ENT_HTML5, 'UTF-8')` in `ImageResolverService::sanitizeCaption()`. The Fluid partial prints the sanitized caption with `f:format.raw()`; with `lib.parseFunc_RTE.htmlSanitize` enabled (TYPO3 default), core's HTML sanitizer runs on the output as a second layer. |
| **Status** | Mitigated |

### T2: Server-Side Request Forgery (SSRF) via External Images

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Editor pastes image URL pointing to internal network (169.254.x.x, 10.x.x.x, cloud metadata endpoints) |
| **Impact** | Internal network scanning, cloud credential theft |
| **Likelihood** | Medium (requires backend access + fetchExternalImages enabled) |
| **Mitigation** | DNS rebinding prevention, private/reserved IP blocking, cloud metadata endpoint blocking in `SecurityValidator::getValidatedIpForUrl()` (used by `ExternalImageFetcher`) |
| **Status** | Mitigated |

### T3: CSS Injection via Style Attributes

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Crafted `style` attribute on `<img>` tag to exfiltrate data or deface pages |
| **Impact** | Data exfiltration via CSS selectors, visual defacement |
| **Likelihood** | Low |
| **Mitigation** | Style attributes explicitly excluded from `htmlAttributes` allowlist in `ImageResolverService::buildHtmlAttributes()` |
| **Status** | Mitigated |

### T4: Dangerous Protocol Injection

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Image src set to `javascript:`, `vbscript:`, `data:text/html`, or `file:` URI |
| **Impact** | Script execution, local file access |
| **Likelihood** | Medium |
| **Mitigation** | Link `href` values: protocol allowlist in `ImageResolverService::ALLOWED_LINK_PROTOCOLS` (only `http:`, `https:`, `mailto:`, `tel:`, `t3:` permitted) via `validateLinkUrl()`. The allowlist is not applied to the image `src`: an image with a file UID gets its `src` from FAL, and the adapter returns an image without a file UID unchanged; for that case the protection is TYPO3's HTML sanitizer, which parseFunc runs unless an integrator sets `htmlSanitize = 0`. In `img src` it allows local paths and only the schemes `http:`, `https:`, `cid:` and `data:` with the media type image, audio or video, and it removes event-handler attributes (`typo3/html-sanitizer`, `CommonBuilder`). |
| **Status** | Mitigated |

### T5: Privilege Escalation via Non-Public Files

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Low-privilege editor references image from non-public storage, exposing internal files on frontend |
| **Impact** | Information disclosure |
| **Likelihood** | Low |
| **Mitigation** | `ImageResolverService::validateFileVisibility()` checks `$file->getStorage()->isPublic()`; `resolve()` then throws `NonPublicFileException` and `ImageRenderingAdapter` renders nothing for the image. Paths that skip `resolve()` or output original markup check `isNonPublicFile()` for every image first. |
| **Status** | Mitigated |

### T6: Regular Expression Denial of Service (ReDoS)

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Crafted HTML content with deeply nested tags causing catastrophic regex backtracking |
| **Impact** | Server-side denial of service during content rendering |
| **Likelihood** | Low |
| **Mitigation** | Primary HTML parsing uses `DOMDocument` instead of regex. `Classes/` still contains about 20 `preg_*` calls, six of them in `ImageRenderingAdapter` (class splitting, attribute trimming, nested-link stripping, link and figure extraction). |
| **Status** | Mitigated |

### T7: SVG-Based XSS via Data URIs

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Inline `data:image/svg+xml` containing `<script>` tags or event handlers |
| **Impact** | Script execution on frontend |
| **Likelihood** | Medium |
| **Mitigation** | `ImageResolverService::sanitizeSvgDataUri()` passes SVG data URIs through TYPO3 Core `SvgSanitizer`, but it is only called from `createDtoFromExternalImage()` for images without a file UID. All `ImageRenderingAdapter` entry points return such images unchanged before calling the resolver, so on the frontend render path the protection is TYPO3's HTML sanitizer, which parseFunc runs unless an integrator sets `htmlSanitize = 0`. In `img src` it allows local paths and only the schemes `http:`, `https:`, `cid:` and `data:` with the media type image, audio or video, and it removes event-handler attributes (`typo3/html-sanitizer`, `CommonBuilder`). A `data:image/svg+xml` URI passes that sanitizer, but browsers do not run scripts in an SVG that is loaded as an image. |
| **Status** | Mitigated |

## Out-of-Scope Threats

These threats are delegated to TYPO3 Core per [ADR-003](ADR-003-Security-Responsibility-Boundaries.rst):

- SVG file upload sanitization (TYPO3 FAL)
- File extension/MIME type validation (TYPO3 FAL)
- Image processing command injection (TYPO3 GraphicalFunctions)
- General authentication and authorization (TYPO3 Core)
- TLS/network security (web server configuration)

## Review Schedule

This threat model is reviewed:
- Before each major version release
- When new attack surfaces are added (new input types, external integrations)
- When security vulnerabilities are reported and fixed
