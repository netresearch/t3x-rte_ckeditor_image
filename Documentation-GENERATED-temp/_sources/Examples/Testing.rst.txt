.. include:: /Includes.rst.txt

.. _examples-testing:

================
Testing Examples
================

Examples for writing functional and unit tests for the RTE CKEditor Image extension.

.. contents:: Table of Contents
   :depth: 3
   :local:

Functional Test
===============

Controller Test Example
-----------------------

.. code-block:: php
   :caption: Tests/Functional/Controller/SelectImageControllerTest.php

   namespace Netresearch\RteCKEditorImage\Tests\Functional\Controller;

   use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

   class SelectImageControllerTest extends FunctionalTestCase
   {
       protected $testExtensionsToLoad = [
           'typo3conf/ext/rte_ckeditor_image'
       ];

       /**
        * @test
        */
       public function infoActionReturnsJsonForValidFile(): void
       {
           // Import test data
           $this->importDataSet(__DIR__ . '/Fixtures/sys_file.xml');

           // Create request
           $request = $this->createRequest('/typo3/rte/wizard/selectimage')
               ->withQueryParams([
                   'action' => 'info',
                   'fileId' => 1,
                   'table' => 'sys_file'
               ]);

           // Execute
           $response = $this->executeFrontendRequest($request);

           // Assert
           self::assertEquals(200, $response->getStatusCode());

           $json = json_decode((string)$response->getBody(), true);
           self::assertArrayHasKey('uid', $json);
           self::assertArrayHasKey('url', $json);
           self::assertEquals(1, $json['uid']);
       }
   }

Unit Test
=========

Database Hook Test Example
---------------------------

.. code-block:: php
   :caption: Tests/Unit/Database/RteImagesDbHookTest.php

   namespace Netresearch\RteCKEditorImage\Tests\Unit\Database;

   use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;
   use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

   class RteImagesDbHookTest extends UnitTestCase
   {
       /**
        * @test
        */
       public function transformRteAddsAltAttributeIfMissing(): void
       {
           $hook = new RteImagesDbHook(/* dependencies */);

           $input = '<img src="/fileadmin/image.jpg" data-htmlarea-file-uid="123" />';
           $output = $hook->transform_rte($input, $rteHtmlParser);

           self::assertStringContainsString('alt=', $output);
       }
   }

Run Tests
=========

Execute Test Suites
-------------------

.. code-block:: bash

   # Functional tests
   ./vendor/bin/phpunit -c Build/phpunit-functional.xml

   # Unit tests
   ./vendor/bin/phpunit -c Build/phpunit-unit.xml

**Result**: Automated testing suite ✅

Related Documentation
=====================

- :ref:`api-controllers` - Controller API documentation
- :ref:`api-datahandling` - Data handling API
- :ref:`examples-custom-extensions` - Custom extensions
- :ref:`troubleshooting-index` - Common issues
