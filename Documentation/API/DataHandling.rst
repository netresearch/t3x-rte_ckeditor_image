.. include:: /Includes.rst.txt

.. Copyright (c) 2025-2026 Netresearch DTT GmbH
   SPDX-License-Identifier: AGPL-3.0-or-later

.. _api-datahandling:

==================
Data Handling API
==================

Complete API reference for data handling components including soft references and database hooks.

.. contents:: Table of Contents
   :depth: 3
   :local:

RteImagesDbHook
===============

.. _api-rteimagesdbhook:

:Namespace: ``Netresearch\RteCKEditorImage\Database``
:Purpose: TCEmain hook for processing RTE content with image references during database operations
:Hook Registration: ``$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]``
:Service Configuration: Public service (automatically registered via ext_localconf.php)

Constructor
-----------

.. php:method:: __construct(RteImageProcessorInterface $imageProcessor)

   :param RteImageProcessorInterface $imageProcessor: Processor for the
      :html:`<img>` tags of an RTE field (``RteImageProcessor``, created by
      ``RteImageProcessorFactory``)

Main Hook Methods
-----------------

processDatamap_postProcessFieldArray()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. php:method:: processDatamap_postProcessFieldArray($status, $table, $id, &$fieldArray, &$dataHandler)

   Main TCEmain hook method called after field processing, before database save.

   :param string $status: Record status ('new' or 'update')
   :param string $table: Database table name
   :param string $id: Record ID (or 'NEW...' for new records)
   :param array $fieldArray: Reference to field values array
   :param DataHandler $dataHandler: TYPO3 DataHandler instance

   **Processing Flow:**

   1. Iterates through all fields in ``$fieldArray``
   2. Identifies RTE text fields via TCA configuration
   3. Checks for ``enableRichtext`` flag
   4. Passes the field value to ``RteImageProcessorInterface::process()``
   5. Updates ``$fieldArray`` with processed content

   **Example Usage** (automatic via hook):

   .. code-block:: php

      // When content is saved:
      $dataHandler->process_datamap();
      // Hook is automatically called for each RTE field

Image Processing
----------------

The hook delegates all image handling to
``Netresearch\RteCKEditorImage\Service\Processor\RteImageProcessor::process()``.
It returns the HTML unchanged outside a backend request.
For each :html:`<img>` tag with a ``src`` it:

1. Normalizes the image source URL.
2. Loads the FAL file from ``data-htmlarea-file-uid`` if present.
3. Reads width and height from the attributes (``ImageTagParser::getDimension()``)
   and falls back to the file's dimensions.
4. Processes the image by source: an existing FAL file, an external URL,
   or a local file.
5. Converts the ``src`` to a relative URL and rebuilds the tag
   (``ImageTagBuilder``).

External images are fetched only if the extension setting
``fetchExternalImages`` is enabled.
``RteImageProcessorFactory`` reads this setting and passes it to
``RteImageProcessor``:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image']['fetchExternalImages'] = true;

RteImageSoftReferenceParser
============================

.. _api-rteimagesoftreferenceparser:

:Namespace: ``Netresearch\RteCKEditorImage\DataHandling\SoftReference``
:Purpose: Parses soft references to FAL images in RTE content for reference tracking

**Service Configuration:**

.. code-block:: yaml

   Netresearch\RteCKEditorImage\DataHandling\SoftReference\RteImageSoftReferenceParser:
     public: true
     tags:
       - name: softreference.parser
         parserKey: rtehtmlarea_images

Purpose of Soft References
---------------------------

Soft references allow TYPO3 to:

- Track where files are used
- Prevent deletion of referenced files
- Update references when files are moved
- Maintain referential integrity

Parser Key
----------

:Key: ``rtehtmlarea_images``

**TCA Registration** (automatic):

.. code-block:: php

   // RTE fields automatically use soft reference parsing
   'bodytext' => [
       'config' => [
           'type' => 'text',
           'enableRichtext' => true,
           // Soft references automatically parsed
       ]
   ]

Parsing Logic
-------------

The parser scans RTE content for:

.. code-block:: html

   <img data-htmlarea-file-uid="123" ... />

And creates soft reference entries:

.. code-block:: php

   [
       'matchString' => '<img data-htmlarea-file-uid="123" ... />',
       'subst' => [
           'type' => 'db',
           'recordRef' => 'sys_file:123',
           'tokenID' => '...',
           'tokenValue' => '123'
       ]
   ]

Reference Index Integration
---------------------------

Soft references populate ``sys_refindex`` table:

+--------------+----------------------------------------------------+
| Field        | Value                                              |
+==============+====================================================+
| tablename    | tt_content                                         |
+--------------+----------------------------------------------------+
| recuid       | 123 (content element ID)                           |
+--------------+----------------------------------------------------+
| field        | bodytext                                           |
+--------------+----------------------------------------------------+
| ref_table    | sys_file                                           |
+--------------+----------------------------------------------------+
| ref_uid      | 456 (file UID)                                     |
+--------------+----------------------------------------------------+
| softref_key  | rtehtmlarea_images                                 |
+--------------+----------------------------------------------------+

Usage Examples
==============

Custom Hook Extension
---------------------

If you need to extend image processing:

.. code-block:: php

   // EXT:my_ext/Classes/Hooks/CustomImageHook.php
   namespace MyVendor\MyExt\Hooks;

   class CustomImageHook
   {
       public function processDatamap_postProcessFieldArray(
           string $status,
           string $table,
           string $id,
           array &$fieldArray,
           \TYPO3\CMS\Core\DataHandling\DataHandler &$dataHandler
       ): void {
           // Your custom processing
           foreach ($fieldArray as $field => &$value) {
               if ($this->isRteField($table, $field)) {
                   $value = $this->customImageProcessing($value);
               }
           }
       }
   }

Register in ext_localconf.php:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
       = \MyVendor\MyExt\Hooks\CustomImageHook::class;

Querying Soft References
-------------------------

Find all content using a specific file:

.. code-block:: php

   use TYPO3\CMS\Core\Database\Connection;
   use TYPO3\CMS\Core\Database\ConnectionPool;
   use TYPO3\CMS\Core\Utility\GeneralUtility;

   $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
       ->getQueryBuilderForTable('sys_refindex');

   $references = $queryBuilder
       ->select('*')
       ->from('sys_refindex')
       ->where(
           $queryBuilder->expr()->eq(
               'ref_table',
               $queryBuilder->createNamedParameter('sys_file')
           ),
           $queryBuilder->expr()->eq(
               'ref_uid',
               $queryBuilder->createNamedParameter(123, Connection::PARAM_INT)
           ),
           $queryBuilder->expr()->eq(
               'softref_key',
               $queryBuilder->createNamedParameter('rtehtmlarea_images')
           )
       )
       ->executeQuery()
       ->fetchAllAssociative();

Rebuilding Reference Index
---------------------------

If references become out of sync:

.. code-block:: bash

   # CLI command
   ./vendor/bin/typo3 referenceindex:update

   # Or programmatically
   use TYPO3\CMS\Core\Database\ReferenceIndex;

   $referenceIndex = GeneralUtility::makeInstance(ReferenceIndex::class);
   $referenceIndex->updateRefIndexTable('tt_content', 123);

Magic Images Explained
======================

What are Magic Images?
----------------------

Magic images are TYPO3's automatic image processing system that creates optimized variants of images based on constraints.

How It Works
------------

1. **Original Image:** Stored in FAL (e.g., 4000x3000px)
2. **Constraints:** Specified in RTE (e.g., 800x600px)
3. **Processing:** TYPO3 creates processed variant
4. **Storage:** ``fileadmin/_processed_/a/b/csm_image_hash.jpg``
5. **URL:** Points to processed variant, not original

Configuration
-------------

.. code-block:: typoscript

   RTE.default.buttons.image.options.magic {
       maxWidth = 1920
       maxHeight = 9999
   }

Processing Context
------------------

.. code-block:: php

   ProcessedFile::CONTEXT_IMAGECROPSCALEMASK

Supported operations:

- **Crop:** ``crop`` parameter
- **Scale:** ``width``, ``height`` parameters
- **Mask:** Alpha channel operations

Debugging
=========

Enable Detailed Logging
------------------------

.. code-block:: php

   // LocalConfiguration.php
   $GLOBALS['TYPO3_CONF_VARS']['LOG']['Netresearch']['RteCKEditorImage']['writerConfiguration'] = [
       \Psr\Log\LogLevel::DEBUG => [
           \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
               'logFile' => 'typo3temp/var/log/rte_ckeditor_image.log'
           ]
       ]
   ];

Check Processed Files
---------------------

.. code-block:: bash

   # List processed images
   ls -la fileadmin/_processed_/

Verify Soft References
----------------------

.. code-block:: sql

   -- Check soft references for content element
   SELECT * FROM sys_refindex
   WHERE tablename = 'tt_content'
   AND recuid = 123
   AND softref_key = 'rtehtmlarea_images';

Related Documentation
=====================

- :ref:`Controllers API <api-controllers>`
- :ref:`Event Listeners <api-eventlisteners>`
- :ref:`Architecture Overview <architecture-overview>`
- :ref:`Troubleshooting <troubleshooting-index>`
