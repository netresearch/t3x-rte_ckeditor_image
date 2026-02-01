:orphan:

.. _images-examples-readme:

==============================
Screenshots Needed for Examples
==============================

The following screenshots are needed for the Linked Images documentation.

Quick Start with DDEV
=====================

The easiest approach is to use the existing DDEV environment:

.. code-block:: bash

   # Start DDEV (if not running)
   ddev start

   # Access TYPO3 v13 backend
   # URL: https://v13.rte-ckeditor-image.ddev.site/typo3/
   # Login: admin / Joh316!!

   # Navigate to: Page > Home > Edit the "Regular Text Element"
   # Double-click the blue test image to open Image Properties dialog

Required Screenshots
====================

ClickBehaviorDropdown.png
-------------------------

**Purpose:** Show the Click Behavior dropdown with its three options.

**Steps:**

1. Edit the text content element on the Home page
2. Double-click the blue test image to open Image Properties
3. Click the "Click Behavior" dropdown to expand it
4. Screenshot showing "None", "Enlarge", "Link" options

**Dimensions:** Cropped to dropdown area, ~400px wide

LinkFieldsExpanded.png
----------------------

**Purpose:** Show all link fields when "Link" is selected.

**Steps:**

1. In Image Properties dialog, select "Link" from Click Behavior
2. Fill in example values:
   - Link URL: ``https://example.com/page``
   - Link Target: ``_blank``
   - Link Title: ``Click for details``
   - Link CSS Class: ``image-link``
   - Additional Parameters: ``&L=1``
3. Screenshot showing all fields populated

**Dimensions:** Cropped to link fields, ~500px wide

LinkBrowserDialog.png
---------------------

**Purpose:** Show the TYPO3 link browser modal.

**Steps:**

1. In Image Properties with "Link" selected
2. Click "Browse..." button next to Link URL
3. Screenshot the link browser showing Page/File/URL tabs

**Dimensions:** Full dialog, ~800px wide

Screenshot Requirements
=======================

- **Format:** PNG
- **Theme:** Light mode (not dark)
- **Tool:** Any screenshot tool (macOS: Cmd+Shift+4, Windows: Win+Shift+S)
- **Annotations:** Use TYPO3 orange (#FF8700) for highlights if needed
- **Alt text:** Descriptive text required for accessibility

Alternative: Screenshot Container
=================================

For consistent, clean screenshots:

.. code-block:: bash

   docker run -d --name typo3-screenshots -p 8080:80 linawolf/typo3-screenshots
   docker exec -it typo3-screenshots bash
   composer require netresearch/rte-ckeditor-image
   ./vendor/bin/typo3 extension:setup
   # Access http://localhost:8080/typo3 (user: j.doe)
