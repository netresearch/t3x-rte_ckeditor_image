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
| **Mitigation** | `htmlspecialchars($caption, ENT_QUOTES \| ENT_HTML5, 'UTF-8')` in `ImageResolverService::sanitizeCaption()`. Fluid auto-escaping as second layer. |
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
| **Mitigation** | Protocol allowlist in `ImageResolverService::ALLOWED_LINK_PROTOCOLS` (only `http:`, `https:`, `mailto:`, `tel:`, `t3:` permitted) via `validateLinkUrl()`. SVG data URIs sanitized via TYPO3 `SvgSanitizer`. |
| **Status** | Mitigated |

### T5: Privilege Escalation via Non-Public Files

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Low-privilege editor references image from non-public storage, exposing internal files on frontend |
| **Impact** | Information disclosure |
| **Likelihood** | Low |
| **Mitigation** | `ImageResolverService::validateFileVisibility()` checks `$file->getStorage()->isPublic()` |
| **Status** | Mitigated |

### T6: Regular Expression Denial of Service (ReDoS)

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Crafted HTML content with deeply nested tags causing catastrophic regex backtracking |
| **Impact** | Server-side denial of service during content rendering |
| **Likelihood** | Low |
| **Mitigation** | Primary HTML parsing uses `DOMDocument` instead of regex, eliminating catastrophic backtracking. The single remaining regex (class extraction in `ImageRenderingAdapter`) uses a simple non-backtracking pattern. |
| **Status** | Mitigated |

### T7: SVG-Based XSS via Data URIs

| Attribute | Value |
|-----------|-------|
| **Attack Vector** | Inline `data:image/svg+xml` containing `<script>` tags or event handlers |
| **Impact** | Script execution on frontend |
| **Likelihood** | Medium |
| **Mitigation** | SVG data URIs passed through TYPO3 Core `SvgSanitizer` at render time, removing script tags, event handlers, and javascript: hrefs |
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
