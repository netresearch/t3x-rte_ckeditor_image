.. _images-examples-readme:

==============================
Screenshots Needed for Examples
==============================

The following screenshots are needed for the Linked Images documentation.
Use the TYPO3 documentation screenshot container for consistent results.

Required Screenshots
====================

ClickBehaviorDropdown.png
-------------------------

**Purpose:** Show the Click Behavior dropdown with its three options.

**Setup:**

1. Open an RTE field with an image inserted
2. Double-click the image to open the Image Properties dialog
3. Click on the "Click Behavior" dropdown to show options

**Content to capture:**

- The dropdown expanded showing "None", "Enlarge", "Link" options
- Cropped to show just the relevant portion of the dialog

**Dimensions:** Cropped screenshot, approximately 400px wide

LinkBrowserDialog.png
---------------------

**Purpose:** Show the TYPO3 link browser that opens when selecting a link target.

**Setup:**

1. Open Image Properties dialog with an image
2. Select "Link" from Click Behavior dropdown
3. Click the "Browse..." button next to Link URL field

**Content to capture:**

- The TYPO3 link browser modal
- Show the tabs: Page, File, Folder, URL, Email, Telephone

**Dimensions:** Full dialog, approximately 800px wide

LinkFieldsExpanded.png
----------------------

**Purpose:** Show all link fields visible when "Link" is selected.

**Setup:**

1. Open Image Properties dialog with an image
2. Select "Link" from Click Behavior dropdown
3. Fill in example values in all fields

**Content to capture:**

- Link URL field with a sample URL
- Link Target dropdown (showing options)
- Link Title field
- Link CSS Class field
- Additional Parameters field with example "&L=1"

**Dimensions:** Cropped to link fields section, approximately 500px wide

Screenshot Container Commands
=============================

.. code-block:: bash

   # Start the TYPO3 screenshot container
   docker run -d --name typo3-screenshots -p 8080:80 linawolf/typo3-screenshots

   # Install the extension
   docker exec -it typo3-screenshots bash
   composer require netresearch/rte-ckeditor-image
   ./vendor/bin/typo3 extension:setup
   exit

   # Access TYPO3 backend at http://localhost:8080/typo3
   # Username: j.doe

   # Stop and remove when done
   docker stop typo3-screenshots && docker rm typo3-screenshots

Screenshot Requirements
=======================

- **Format:** PNG
- **Backend mode:** Light theme (not dark mode)
- **User:** j.doe
- **CSS class:** Use ``:class: with-shadow`` in RST
- **Alt text:** Descriptive, not just "screenshot"
