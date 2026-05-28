.. include:: /Includes.rst.txt

.. _adr-004-image-src-storage-convention:

============================================================
ADR-004: Canonical RTE Image ``src`` Storage Convention
============================================================

:Date: 2026-05-28
:Status: Accepted
:Context: Issues `#778`_, `#837`_; PRs `#779`_, `#839`_, `#840`_

.. _#778: https://github.com/netresearch/t3x-rte_ckeditor_image/issues/778
.. _#837: https://github.com/netresearch/t3x-rte_ckeditor_image/issues/837
.. _#779: https://github.com/netresearch/t3x-rte_ckeditor_image/pull/779
.. _#839: https://github.com/netresearch/t3x-rte_ckeditor_image/pull/839
.. _#840: https://github.com/netresearch/t3x-rte_ckeditor_image/pull/840

Summary
-------

The extension persists every RTE image ``src`` in **canonical
site-root-relative form with a leading slash** (``/fileadmin/x``). This
contract is enforced symmetrically on both ends of the data flow: the
storage write path (``ImageTagBuilder::makeRelativeSrc``) and the
validator's strict-equality comparison
(``RteImageReferenceValidator::srcMatchesPublicUrl``). Slashless
(``fileadmin/x``) and protocol-relative (``//cdn.example.com/x``) forms
are out-of-contract: slashless is detected as a defect and repaired;
protocol-relative is treated as an external CDN reference and passes
through unchanged.

Context
-------

A series of regressions exposed an implicit contract that had never been
written down:

- `#778`_ reported that ``getPublicUrl()`` for the Local FAL driver
  returns slashless (``fileadmin/x``) while the RTE stored leading-slash
  (``/fileadmin/x``). The validator's naïve string-compare flagged the
  stored value as ``SrcMismatch`` and "fixed" it by stripping the
  leading slash — silently breaking frontend rendering on every page.

- The first attempt at #778 (`#779`_) made the comparison tolerant: both
  slashless and leading-slash were accepted as equivalent. This stopped
  the over-correction but turned the validator blind to a different
  defect.

- `#837`_ surfaced that defect: older ``upgrade:run`` versions had
  stripped the leading slash from existing storage. ``validate --fix``
  was the intended repair tool but, after the over-correction in #779,
  it silently treated the broken value as already-correct.

- `#839`_ tightened the validator to strict equality, restoring repair.

- `#840`_ closed the loop: ``ImageTagBuilder::makeRelativeSrc`` was
  still producing slashless on save in some paths, which meant fresh
  inserts would be flagged + rewritten by the next validate run. The
  storage side now normalises to leading-slash for every local input.

The decision below codifies the convention so it does not get re-broken.

Decision Drivers
----------------

**TYPO3 v12+ does not emit ``<base href>``.**
   ``config.baseURL`` was deprecated and removed. Modern TYPO3 uses
   site configuration's ``base`` key for routing; the rendered HTML
   carries no ``<base href>`` tag. A browser therefore resolves
   slashless ``src`` against the current page URL — broken on every
   non-root page.

**Storage and rendering must be decoupled cleanly.**
   The storage convention should be independent of where the install is
   served from (root vs subpath). TYPO3's standard mechanism for this
   is ``config.absRefPrefix``: a leading-slash storage value is
   prefixed at render time. This keeps every install layout
   identical at the database level.

**Two write paths must not disagree.**
   The editor saves through ``RteImageProcessor`` ➜
   ``ImageTagBuilder::makeRelativeSrc``. The validator/listener writes
   through ``RteImageReferenceValidator::fix()`` and
   ``UpdateImageReferences``. If these two paths produce different
   canonical forms, every save-then-validate cycle creates spurious
   rewrites.

**External references must be preserved bit-for-bit.**
   Protocol-relative (``//cdn.example.com/...``), scheme URLs
   (``http://``, ``https://``, ``data:``, ``mailto:``) reference assets
   outside the site's FAL. The contract must not coerce them into a
   site-root-relative form.

Decision
--------

1. **Canonical storage form.** Every local image ``src`` is persisted
   with a leading slash: ``/fileadmin/...``. Slashless storage
   (``fileadmin/...``) is treated as a defect and repaired to the
   canonical form.

2. **External references pass through unchanged.** Scheme URLs and
   protocol-relative URLs are detected via the RFC 3986 scheme grammar
   regex ``^(?:[a-z][a-z0-9+.\-]*:|//)#i`` and returned as-is.

3. **Write paths agree.** Both ``ImageTagBuilder::makeRelativeSrc`` and
   the validator's repair path (``normalizePublicUrl`` ➜
   ``srcMatchesPublicUrl`` ➜ ``applyFixes``) produce the same canonical
   leading-slash output.

4. **Subpath installs use ``config.absRefPrefix``.** The prefix is
   prepended at render time by TYPO3 Core. Storage stays canonical
   across root and subpath installs. See
   :ref:`troubleshooting-frontend-issues` for setup.

5. **Empty ``siteUrl`` is a safety valve.** When the editor save path
   is invoked without a resolved site URL (CLI, certain test contexts),
   ``makeRelativeSrc`` returns the input unchanged. This prevents
   accidental rewrites in contexts where the site identity is unknown.

Out of Scope
------------

- **Emitting ``<base href>``.** This was a TYPO3 Core concern; v9.5+
  no longer emits it. The extension does not attempt to restore it.

- **Sub-path rendering at the HTML layer.** Delegated to
  ``config.absRefPrefix``, a standard TYPO3 mechanism. The extension
  does not duplicate this functionality.

- **Per-site validation context.** The validator does not currently
  inject ``sitePath`` to compute per-site expected forms. The contract
  above makes this unnecessary: storage is uniform across sites.

Consequences
------------

Positive
^^^^^^^^

- A single storage convention across root and subpath installs.
- The validator's strict-equality rule is correct for every layout.
- Save-then-validate cycles are stable: ``validate`` after a fresh save
  flags nothing.
- External CDN and ``data:`` URIs are explicitly preserved.

Negative
^^^^^^^^

- Subpath operators with pre-existing slashless storage (from older
  ``upgrade:run`` versions) must run
  ``rte_ckeditor_image:validate --fix --table=tt_content`` once to
  migrate. See
  :ref:`troubleshooting-image-reference-validation`.

- Operators who run a subpath install **without** ``config.absRefPrefix``
  configured will see broken images. This is the same broken state
  pre-#840, just now made explicit by the convention.

- ``makeRelativeSrc`` is named for what it used to do (strip the site
  URL); it now also normalises. The name is retained for backwards
  compatibility of the public ``ImageTagBuilderInterface``.

Related Documentation
=====================

- :ref:`troubleshooting-image-reference-validation` — the
  ``validate --fix`` command and what it repairs.
- :ref:`troubleshooting-frontend-issues` — ``config.absRefPrefix``
  setup for subpath installs.
- :ref:`adr-003-security-responsibility-boundaries` — pattern for
  contracts that delegate to TYPO3 Core.
