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

## Secrets Management

The project follows these practices for managing secrets and credentials:

- **Storage**: All secrets (API tokens, deployment keys) are stored exclusively in [GitHub Encrypted Secrets](https://docs.github.com/en/actions/security-guides/encrypted-secrets), never in source code
- **Access**: Secrets are scoped to specific workflows and environments. Only repository administrators can create or modify secrets
- **Rotation**: Deployment tokens (CODECOV_TOKEN, TYPO3_TER_ACCESS_TOKEN) are rotated when team membership changes or upon suspected compromise
- **Prevention**: `.gitignore` excludes sensitive files. CI uses `step-security/harden-runner` to monitor for credential exfiltration
- **Audit**: GitHub's audit log tracks all secret access and modifications

## Support Policy

This extension follows the [TYPO3 release lifecycle](https://get.typo3.org/):

- **Active support**: The current major version receives bug fixes and new features
- **Security support**: The previous major version receives critical security fixes for 6 months after a new major release
- **End of life**: Versions beyond security support receive no further updates

| Extension Version | TYPO3 Version | PHP Version | Status |
|-------------------|---------------|-------------|--------|
| 13.x | 13.4 LTS, 14.x | >=8.2 | Active support |
| 12.x | 12.4 LTS | 8.1 – 8.3 | Security fixes only |
| 11.x | 11.5 LTS | 7.4 – 8.1 | End of life |

When a TYPO3 LTS version reaches end of life, the corresponding extension version will no longer receive security updates.

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
