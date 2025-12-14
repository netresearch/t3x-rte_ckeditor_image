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

## Reporting a security problem

* Create a new Github issue using the "bug report" template.
* Explain the problem as detailed as possible:
  - What is the type of the problem (SQL injection, cross-site scripting, etc.)?
  - Is any special configuration required to reproduce it?
  - What steps must be taken to reproduce it?
  - How could an attacker exploit the issue?
* Fill in all the remaining information in the template. The more information, the better.
* Finally, add the label "security" to the Github issue.

We look through the open issues regularly and will pick it up ASAP.
