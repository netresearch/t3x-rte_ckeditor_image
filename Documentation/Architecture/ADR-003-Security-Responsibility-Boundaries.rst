ADR-003: Security Responsibility Boundaries
===========================================

:Date: 2025-12-14
:Status: Accepted
:Context: Code Review v13.0.1 → v13.2.x

Summary
-------

This ADR documents the security responsibility boundaries between this extension
(``netresearch/rte-ckeditor-image``) and TYPO3 Core. Clear boundaries prevent
scope creep and ensure security issues are addressed by the appropriate party.

Decision
--------

The following security responsibilities are explicitly **out of scope** for this
extension and are delegated to TYPO3 Core:

Out of Scope (TYPO3 Core Responsibility)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. **SVG Sanitization**

   - SVG files can contain embedded JavaScript (``<script>`` tags, event handlers)
   - TYPO3 FAL is responsible for validating and sanitizing uploaded SVG files
   - This extension only references files already accepted by TYPO3
   - Related issue: `#474 <https://github.com/netresearch/rte-ckeditor-image/issues/474>`_
     tracks optional additional protection, but Core sanitization is primary defense

2. **File Extension / MIME Type Validation**

   - Ensuring a file's extension matches its actual content type
   - Example: Blocking a ``.jpg`` file that contains SVG/XML content
   - TYPO3 FAL validates this during upload via ``FileNameValidator`` and MIME checks
   - This extension trusts FAL's validation when referencing ``sys_file`` records

3. **General File Upload Security**

   - Virus scanning, file size limits, allowed extensions
   - All handled by TYPO3 FAL and ``$GLOBALS['TYPO3_CONF_VARS']['BE']['fileDenyPattern']``

4. **Image Processing Security**

   - ImageMagick/GraphicsMagick command injection prevention
   - Handled by TYPO3's ``GraphicalFunctions`` and ``ImageService``

In Scope (This Extension's Responsibility)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

1. **Caption XSS Prevention**

   - User-editable caption text must be sanitized
   - Implementation: ``htmlspecialchars($caption, ENT_QUOTES | ENT_HTML5, 'UTF-8')``
   - Location: ``ImageResolverService::sanitizeCaption()``

2. **File Visibility Validation**

   - Prevent rendering images from non-public storages
   - Backend users should not expose internal files via RTE
   - Location: ``ImageResolverService::validateFileVisibility()``

3. **Dangerous Protocol Blocking**

   - Block ``javascript:``, ``vbscript:``, ``data:text/html`` in URLs
   - Tracked in `#475 <https://github.com/netresearch/rte-ckeditor-image/issues/475>`_
   - Location: ``ImageResolverService::DANGEROUS_PROTOCOLS``

4. **SSRF Protection for External Images**

   - DNS rebinding prevention
   - Private/reserved IP blocking
   - Cloud metadata endpoint blocking
   - Location: ``RteImagesDbHook::getSafeIpForExternalFetch()``

5. **Style Attribute Exclusion**

   - Prevent CSS injection via style attributes
   - Style attributes are explicitly excluded from ``htmlAttributes``
   - Location: ``ImageResolverService::buildHtmlAttributes()``

Consequences
------------

**Positive:**

- Clear accountability for security issues
- Prevents duplicate security implementations
- Reduces extension complexity by leveraging Core security

**Negative:**

- Relies on TYPO3 Core maintaining its security measures
- Sites with outdated TYPO3 versions may have gaps

**Mitigations:**

- Document minimum TYPO3 version requirements
- Optional additional protections tracked in GitHub issues (#474, #475)
- Users can enable stricter settings via extension configuration

References
----------

- GitHub Issue #474: SVG Sanitization
- GitHub Issue #475: Protocol Blocklist
- TYPO3 Security Guide: https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/Security/
- OWASP File Upload Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
