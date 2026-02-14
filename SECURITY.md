## Security

We take security very seriously and are always grateful for reports about potential problems or vulnerabilities.

## Security Measures

This extension implements the following security measures:

- **Caption XSS Prevention**: All user-editable captions are sanitized with `htmlspecialchars()`
- **File Visibility Validation**: Images from non-public storages are blocked
- **Dangerous Protocol Blocking**: `javascript:`, `vbscript:`, `data:text/html` URLs are rejected
- **SSRF Protection**: External image fetching includes DNS rebinding and private IP blocking
- **Style Attribute Exclusion**: CSS injection via style attributes is prevented

## Responsibility Boundaries

Some security aspects are handled by TYPO3 Core, not this extension:

- **SVG sanitization** → TYPO3 FAL responsibility
- **File extension/MIME validation** → TYPO3 FAL responsibility
- **Image processing security** → TYPO3 GraphicalFunctions responsibility

For details, see [ADR-003: Security Responsibility Boundaries](Documentation/Architecture/ADR-003-Security-Responsibility-Boundaries.rst).

## Reporting a Vulnerability

**Please do NOT report security vulnerabilities through public GitHub issues.**

Instead, use [GitHub's private vulnerability reporting](https://github.com/netresearch/t3x-rte_ckeditor_image/security/advisories/new)
to report security issues confidentially. This ensures the vulnerability can be assessed and
a fix prepared before public disclosure.

When reporting, please include:

- A description of the vulnerability and its potential impact
- Steps to reproduce the issue
- The type of the problem (e.g., SQL injection, cross-site scripting, path traversal)
- Any special configuration required to reproduce it

We will acknowledge receipt within 48 hours and aim to provide a fix within 7 days
for critical vulnerabilities.

## Coordinated Disclosure

We follow coordinated disclosure practices. After a fix is released, we will:

1. Publish a [GitHub Security Advisory](https://github.com/netresearch/t3x-rte_ckeditor_image/security/advisories)
2. Credit the reporter (unless anonymity is requested)
3. Include the fix in the next release with a CVE identifier if applicable
