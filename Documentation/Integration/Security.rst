.. include:: /Includes.rst.txt

.. _integration-security:

========
Security
========

.. versionadded:: 13.1.5

   Comprehensive security measures including protocol blocking, file validation,
   and XSS prevention.

Security features and best practices for the RTE CKEditor Image extension.

.. contents:: Table of contents
   :depth: 2
   :local:

Security architecture
=====================

The extension implements security at multiple layers:

..  uml::
    :caption: Security layers architecture

    skinparam componentStyle rectangle

    package "1. Input Validation" {
        component "ImageResolverService" as Resolver
        note right of Resolver
            Protocol blocking
            File visibility check
            FAL validation
        end note
    }

    package "2. XSS Prevention" {
        component "ImageRenderingDto" as DTO
        note right of DTO
            htmlspecialchars encoding
            Readonly properties
            Immutable after creation
        end note
    }

    package "3. Output Rendering" {
        component "Fluid Templates" as Templates
        note right of Templates
            Pre-validated DTOs
            Auto-escaping enabled
        end note
    }

    Resolver --> DTO : validated data
    DTO --> Templates : immutable data

Protocol blocking
=================

The :php:`ImageResolverService` blocks dangerous URL protocols:

-  `javascript:` - Prevents script execution via URLs.
-  `file:` - Prevents local file system access.
-  `data:text/html` - Prevents HTML injection via data URIs.
-  `vbscript:` - Prevents VBScript execution (legacy IE).

**Safe protocols allowed:**

-  `http://` and `https://` - Standard web URLs.
-  `/` - Relative paths.
-  `t3://` - TYPO3 link handler URLs.

File visibility validation
==========================

Before rendering, the extension validates:

#. **File exists**: FAL file reference must be valid.
#. **File accessible**: File must not be hidden or restricted.
#. **Storage accessible**: File storage must be publicly accessible.

If validation fails, the original unprocessed content is returned.

XSS prevention
==============

All user-controlled content is sanitized:

Caption text
------------

..  code-block:: php
    :caption: Caption sanitization

    // Caption is sanitized with htmlspecialchars()
    $caption = htmlspecialchars($rawCaption, ENT_QUOTES | ENT_HTML5, 'UTF-8');

Alt and title attributes
------------------------

Alt and title text are sanitized before inclusion in HTML output.

CSS classes
-----------

CSS class names are validated and encoded to prevent attribute injection.

Immutable DTOs
==============

The :php:`ImageRenderingDto` and :php:`LinkDto` are declared as :php:`readonly`:

..  code-block:: php
    :caption: Readonly DTO declaration

    final readonly class ImageRenderingDto
    {
        // Properties cannot be modified after construction
    }

This ensures:

-  **Data integrity**: Validated data cannot be corrupted.
-  **Audit trail**: Security validation happens once, at creation.
-  **Thread safety**: No race conditions on property access.

.. _integration-security-rel:

External link security (`rel="noreferrer"`)
===========================================

Automatic `rel="noreferrer"` on figure-wrapped linked images, mirroring
TYPO3 typolink semantics. Closes
`#799 <https://github.com/netresearch/t3x-rte_ckeditor_image/issues/799>`__
(see `CHANGELOG.md <https://github.com/netresearch/t3x-rte_ckeditor_image/blob/main/CHANGELOG.md>`__
for the version this shipped in).

Linked images that are wrapped in `<figure>` (e.g. when a caption is set)
are rendered through the Fluid `Link.html` partial, which constructs
the `<a>` tag directly rather than going through TYPO3's `LinkFactory`.
That means `LinkFactory::addSecurityRelValues()` — the core helper that
appends `rel="noreferrer"` to external `target="_blank"` links to prevent
referrer leakage — never ran on this code path. The extension now mirrors
the typolink semantics in PHP via the :php:`SecurityRelComputer` service.

When the rule fires
-------------------

`rel="noreferrer"` is appended automatically when **both** conditions hold:

#. The link target opens a new browsing context — i.e. `target` is set
   and not one of `_self`, `_parent`, `_top` (case-insensitive,
   whitespace-tolerant per the HTML living standard).
#. The URL is **external** — defined as either:

   -  An absolute `http://` or `https://` URL.
   -  A protocol-relative URL (e.g. `//example.com/image.jpg`,
      RFC 3986 §4.2 network-path reference) that inherits the page
      scheme but resolves to a different host.

Relative paths (`/fileadmin/...`), fragment links (`#section`),
`mailto:` / `tel:` schemes, and `t3://` URIs (already resolved before
this point) are treated as internal and don't trigger the addition.

Token preservation
------------------

Pre-existing rel tokens from the source `<a>` tag — `nofollow`,
`sponsored`, `noopener`, custom values — are preserved through
`SecurityRelComputer::parseTokens()`, which lowercases, deduplicates,
and collapses whitespace. `noreferrer` is added at most once; if the
source already declares it, no duplicate is appended.

Example
-------

..  code-block:: html
    :caption: Editor-set link with target="_blank" and an external URL

    <a href="https://example.com" target="_blank">
      <img src="/fileadmin/_processed_/image.jpg" alt="..." />
    </a>

After rendering through the figure-wrapped path:

..  code-block:: html
    :caption: Output — `rel="noreferrer"` injected automatically

    <figure>
      <a href="https://example.com" target="_blank" rel="noreferrer">
        <img src="/fileadmin/_processed_/image.jpg" alt="..." />
      </a>
      <figcaption>...</figcaption>
    </figure>

Internal links (e.g. `/about` or `t3://page?uid=42`) and links without
a `target` continue to render without `rel`, matching typolink behavior.

SVG security
============

.. warning::

   SVG files can contain embedded JavaScript and are potential XSS vectors.
   The extension does not sanitize SVG content.

**Recommendations:**

#. **Sanitize before upload**: Use server-side SVG sanitization libraries.
#. **Restrict uploads**: Consider limiting SVG uploads to trusted users.
#. **Content Security Policy**: Implement CSP headers to mitigate XSS risks.

.. note::

   The `allowSvgImages` option was removed in v13.1.5 due to security
   concerns. SVG files are now handled via the standard image workflow
   with automatic noScale mode.

Best practices
==============

File upload restrictions
------------------------

Configure allowed file extensions in TYPO3:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'] = 'gif,jpg,jpeg,png,webp';

Restrict in RTE configuration:

..  code-block:: yaml
    :caption: EXT:my_extension/Configuration/RTE/Custom.yaml

    editor:
      externalPlugins:
        typo3image:
          allowedExtensions: "jpg,jpeg,png,gif,webp"

Backend user permissions
------------------------

-  Configure appropriate file mounts for backend users.
-  Restrict upload folder access to necessary directories.
-  Use TYPO3 backend user groups for granular control.

Content Security Policy
-----------------------

Implement CSP headers for additional protection:

..  code-block:: apache
    :caption: .htaccess CSP configuration

    Header set Content-Security-Policy "default-src 'self'; img-src 'self' data: https:;"

External image fetching
-----------------------

The `fetchExternalImages` option downloads external images to FAL:

..  code-block:: php
    :caption: config/system/settings.php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'] = [
        'fetchExternalImages' => true,  // Recommended: download to local storage
    ];

This prevents:

-  Hotlinking to external resources.
-  Privacy leaks via external image loading.
-  Broken images when external sources change.

Regular updates
---------------

Keep the extension and TYPO3 core updated to receive security patches:

..  code-block:: bash
    :caption: Update extension via Composer

    composer update netresearch/rte-ckeditor-image

Security reporting
==================

Report security vulnerabilities to:

-  **TYPO3 Security Team**: security@typo3.org
-  **Extension maintainer**: Via GitHub issues (for non-critical issues)

Related documentation
=====================

-  :ref:`api-imageresolverservice` - Security validation implementation.
-  :ref:`api-dtos` - Immutable data transfer objects.
-  :ref:`integration-configuration-advanced` - Extension configuration.
