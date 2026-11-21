.. include:: /Includes.rst.txt

.. Copyright (c) 2025-2026 Netresearch DTT GmbH
   SPDX-License-Identifier: AGPL-3.0-or-later

.. _examples-testing:

================
Testing Examples
================

Examples for writing functional and unit tests for the CKEditor Image Support extension.

.. contents:: Table of Contents
   :depth: 3
   :local:

Test Suites in the Extension
============================

The extension's own tests are the reference for writing tests against it:

- ``Tests/Functional/Controller/SelectImageControllerTest.php`` - image dialog controller
- ``Tests/Functional/Controller/ImageRenderingAdapterTypoScriptTest.php`` - frontend rendering via TypoScript
- ``Tests/Unit/Database/RteImagesDbHookTest.php`` - DataHandler hook

They use the TYPO3 testing framework with PHPUnit attributes (``#[Test]``).
Functional tests that need records load CSV fixtures with ``importCSVDataSet()``.

Run Tests
=========

Execute Test Suites
-------------------

.. code-block:: bash

   # Unit tests (Build/phpunit/UnitTests.xml)
   composer ci:test:php:unit

   # Functional tests (Build/phpunit/FunctionalTests.xml, needs typo3DatabaseDriver=pdo_sqlite)
   composer ci:test:php:functional

**Result**: Automated testing suite ✅

Related Documentation
=====================

- :ref:`api-controllers` - Controller API documentation
- :ref:`api-datahandling` - Data handling API
- :ref:`examples-custom-extensions` - Custom extensions
- :ref:`troubleshooting-index` - Common issues
