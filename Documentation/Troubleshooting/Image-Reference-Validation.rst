.. include:: /Includes.rst.txt

.. _troubleshooting-image-reference-validation:

================================
Image Reference Validation
================================

The extension ships a validator that detects and fixes stale or broken image
references in RTE content fields. It is available both as a **CLI command** and
as an **Upgrade Wizard** in the TYPO3 Install Tool.

.. contents:: Table of Contents
   :local:
   :depth: 2

Overview
========

Over time, image references stored inside RTE ``bodytext`` fields can become
stale. Common causes include TYPO3 major upgrades, bulk file operations in the
Filelist module, and manual database edits. The validator scans every ``<img>``
tag that carries a ``data-htmlarea-file-uid`` attribute, resolves the
corresponding FAL file, and compares the ``src`` attribute against the file's
current public URL.

Five categories of issues are detected:

.. list-table:: Issue types
   :header-rows: 1
   :widths: 25 50 15

   * - Type
     - Description
     - Auto-fixable
   * - ``processed_image_src``
     - ``src`` points to a ``_processed_/`` URL. Processed files are
       regenerated on demand and their paths change between TYPO3 versions,
       so storing them as ``src`` will break after an upgrade.
     - Yes
   * - ``src_mismatch``
     - ``src`` does not match the FAL file's current public URL. This happens
       when a file is moved or renamed in the Filelist module while existing
       RTE content still references the old path.
     - Yes
   * - ``broken_src``
     - ``src`` is empty or missing, but a valid ``data-htmlarea-file-uid`` is
       present. The correct URL can be resolved from FAL.
     - Yes
   * - ``orphaned_file_uid``
     - ``data-htmlarea-file-uid`` references a FAL file that no longer exists
       in ``sys_file``. The stale ``data-htmlarea-file-uid`` attribute is
       removed, but no ``src`` correction is possible because the file is gone.
     - Yes (attribute removed)
   * - ``missing_file_uid``
     - The ``<img>`` tag has no ``data-htmlarea-file-uid`` attribute at all.
       Without a file UID there is no way to determine which FAL file the
       image should reference, so this issue requires manual intervention.
     - No

For fixable issues the validator replaces the ``src`` attribute with the file's
current ``getPublicUrl()`` value. The ``orphaned_file_uid`` type is treated as
fixable in the scan (it is counted and reported), but no ``src`` update is
applied because the underlying file no longer exists.

Prerequisites
=============

The validator relies on the TYPO3 **reference index** (``sys_refindex``) to
discover which RTE fields contain image references. On a fresh installation or
after large imports, the reference index may be empty or out of date. Always
update it before running the validator:

.. code-block:: bash

   bin/typo3 referenceindex:update

If the validator reports "Scanned records: 0" despite images existing in RTE
content, this is almost certainly the cause.

CLI Command
===========

The ``rte_ckeditor_image:validate`` command scans RTE content fields and
reports (or fixes) broken image references.

Dry-run (default)
-----------------

Run the command without any flags to perform a read-only scan:

.. code-block:: bash

   bin/typo3 rte_ckeditor_image:validate

The output shows a summary of scanned records and images, followed by a table
listing every issue found, including the current ``src``, the expected ``src``,
and whether the issue is auto-fixable.

.. figure:: /Images/cli-validate-references.png
   :alt: CLI command output showing RTE image reference validation results
   :class: with-shadow

   Example output of ``bin/typo3 rte_ckeditor_image:validate`` in dry-run mode.

Apply fixes
-----------

Add the ``--fix`` flag to write corrected ``src`` attributes back to the
database:

.. code-block:: bash

   bin/typo3 rte_ckeditor_image:validate --fix

.. warning::
   ``--fix`` modifies database records directly. Always run a dry-run scan
   first and create a database backup before applying fixes in production.

Limit to a specific table
--------------------------

Use the ``--table`` (short: ``-t``) option to restrict the scan to a single
table:

.. code-block:: bash

   bin/typo3 rte_ckeditor_image:validate --table=tt_content

This is useful on large installations where you want to process one table at a
time or only care about a particular table.

Combining options
-----------------

Options can be combined freely:

.. code-block:: bash

   # Fix issues in tt_content only
   bin/typo3 rte_ckeditor_image:validate --fix --table=tt_content

Exit codes
----------

.. list-table::
   :header-rows: 1
   :widths: 15 85

   * - Code
     - Meaning
   * - ``0``
     - No issues found, or all fixable issues were repaired successfully.
   * - ``1``
     - Issues were found (dry-run mode), or no fixable issues exist while
       unfixable issues remain.

Upgrade Wizard
==============

The same validation logic is exposed as a TYPO3 Upgrade Wizard named
**Validate RTE image references**.

To run it:

1. Open **Admin Tools** > **Upgrade** > **Upgrade Wizard**.
2. Locate **Validate RTE image references** in the list of available wizards.
3. Click **Execute**.

The wizard scans all RTE fields, and if fixable issues are found it
automatically applies corrections. It implements ``RepeatableInterface``, so it
can be executed multiple times safely.

.. figure:: /Images/upgrade-wizard-validate-references.png
   :alt: TYPO3 Upgrade Wizard showing the Validate RTE Image References wizard
   :class: with-shadow

   The Upgrade Wizard panel with detected issues and the Execute button.

.. tip::
   The wizard requires the database to be up-to-date (``DatabaseUpdatedPrerequisite``).
   Run all database schema migrations before executing this wizard.

Page Module Preview Warning
===========================

.. versionadded:: 13.5.0

In addition to the CLI command and upgrade wizard, the extension now detects broken
image references directly in the **TYPO3 page module** preview. When a content element
contains images with validation issues, a yellow warning callout is shown above the
content preview:

.. code-block:: text

   ┌─────────────────────────────────────────────┐
   │ ⚠ Image reference issues detected           │
   │ 2 orphaned file reference(s),               │
   │ 1 outdated src path(s).                     │
   │ Run the upgrade wizard                      │
   │ rteImageReferenceValidation or CLI command   │
   │ bin/typo3 rte_ckeditor_image:validate --fix  │
   │ to repair.                                   │
   └─────────────────────────────────────────────┘

This warning appears automatically for all CTypes that use the
``RteImagePreviewRenderer`` (see :ref:`api-rtepreviewrendererregistrar`).
The detection happens during page module rendering and requires no additional
configuration.

The same five issue types detected by the CLI command are shown in the warning:
``orphaned_file_uid``, ``src_mismatch``, ``processed_image_src``,
``missing_file_uid``, and ``broken_src``.

.. tip::
   The warning is purely informational and does not block editing. Editors can
   continue working with the content element while an administrator runs the
   upgrade wizard or CLI command to fix the references.

When to Use
===========

Run the validator in the following situations:

After a TYPO3 major upgrade
----------------------------

Especially when upgrading from **TYPO3 v10, v11, or v12 to v13+**. Older
versions of TYPO3 and of this extension sometimes stored ``_processed_/``
URLs in ``bodytext`` instead of the original file path. These processed paths
break after an upgrade because processed files are regenerated with different
names.

After bulk file operations
--------------------------

When files are **moved or renamed** in the Filelist module, the extension
updates references in RTE content automatically (via the
``UpdateImageReferences`` listener). However, if files were moved by
other means (direct filesystem operations, TYPO3 CLI, third-party
tools), references may become stale.

As a periodic maintenance check
--------------------------------

Run the dry-run scan periodically to detect drift before it causes
broken images in the frontend. The scan is read-only and safe to run at
any time.

----

Related Documentation
=====================

**Other Troubleshooting Topics:**

* :ref:`troubleshooting-installation-issues` - Extension installation problems
* :ref:`troubleshooting-frontend-issues` - Frontend rendering issues

**Additional Resources:**

* :ref:`integration-configuration` - Configuration guide
* :ref:`architecture-overview` - System architecture
