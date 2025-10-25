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

Class Properties
----------------

fetchExternalImages
~~~~~~~~~~~~~~~~~~~

.. php:attr:: fetchExternalImages

   :Type: bool
   :Visibility: protected

   Controls whether external image URLs should be fetched and uploaded to TYPO3.

   **Configuration:**

   Set via Extension Manager or settings.php:

   .. code-block:: php

      $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image']['fetchExternalImages'] = true;

Constructor
-----------

.. php:method:: __construct(ExtensionConfiguration $extensionConfiguration, LogManager $logManager)

   Initializes hook with extension configuration and logging.

   :param ExtensionConfiguration $extensionConfiguration: TYPO3 extension configuration service
   :param LogManager $logManager: Logger manager for error logging
   :throws ExtensionConfigurationExtensionNotConfiguredException: If extension not configured
   :throws ExtensionConfigurationPathDoesNotExistException: If configuration path missing

Main Hook Methods
-----------------

processDatamap_postProcessFieldArray()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. php:method:: processDatamap_postProcessFieldArray(string $status, string $table, string $id, array &$fieldArray, DataHandler &$dataHandler): void

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
   4. Processes image tags in RTE content
   5. Updates ``$fieldArray`` with processed content

   **Example Usage** (automatic via hook):

   .. code-block:: php

      // When content is saved:
      $dataHandler->process_datamap();
      // Hook is automatically called for each RTE field

transform_rte()
~~~~~~~~~~~~~~~

.. php:method:: transform_rte(string $value, RteHtmlParser $rteHtmlParser): string

   Transforms RTE content for database storage (legacy method).

   :param string $value: HTML content from RTE
   :param RteHtmlParser $rteHtmlParser: TYPO3 RTE HTML parser
   :returns: Transformed HTML string
   :returntype: string

   **Transformation Steps:**

   1. Splits content by ``<img>`` tags
   2. Converts relative URLs to absolute
   3. Adds site URL prefix
   4. Ensures ``alt`` attribute exists

   .. note::
      This method follows TYPO3's legacy naming convention (camelCase with underscore).

Image Processing Methods
------------------------

modifyRteField()
~~~~~~~~~~~~~~~~

.. php:method:: modifyRteField(string $value): string

   Main processing method for RTE field content with images.

   :param string $value: RTE HTML content
   :returns: Processed HTML content
   :returntype: string
   :visibility: private

   **Processing Logic:**

   **1. Image Tag Splitting**

   .. code-block:: php

      $imgSplit = $rteHtmlParser->splitTags('img', $value);
      // Results in: ['text', '<img...>', 'text', '<img...>', ...]

   **2. URL Processing**

   - Converts absolute URLs to relative
   - Handles site subpath scenarios
   - Processes ``data-htmlarea-file-uid`` references

   **3. FAL Integration**

   .. code-block:: php

      if (isset($attribArray['data-htmlarea-file-uid'])) {
          $originalImageFile = $resourceFactory->getFileObject($uid);
      }

   **4. Magic Image Processing**

   .. code-block:: php

      $imageConfiguration = [
          'width' => $imageWidth,
          'height' => $imageHeight,
      ];

      $magicImage = $originalImageFile->process(
          ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
          $imageConfiguration
      );

   **5. External Image Fetching**

   - Only in backend context
   - Only if ``fetchExternalImages`` is true
   - Downloads and uploads to user's default folder

   **6. Local File Detection**

   - Checks if image is in fileadmin/
   - Attempts to find FAL reference
   - Adds ``data-htmlarea-file-uid`` if found

   **Scenarios Handled:**

   +--------------------------------------------+------------------------------------------------------+
   | Scenario                                   | Action                                               |
   +============================================+======================================================+
   | Image with ``data-htmlarea-file-uid``      | Load from FAL, process if dimensions differ          |
   +--------------------------------------------+------------------------------------------------------+
   | External URL (backend)                     | Fetch, upload, create FAL record                     |
   +--------------------------------------------+------------------------------------------------------+
   | External URL (frontend)                    | Leave as-is                                          |
   +--------------------------------------------+------------------------------------------------------+
   | Local file without UID                     | Search FAL, add UID if found                         |
   +--------------------------------------------+------------------------------------------------------+
   | Relative URL                               | Convert to site-relative path                        |
   +--------------------------------------------+------------------------------------------------------+

Helper Methods
--------------

getImageWidthFromAttributes()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. php:method:: getImageWidthFromAttributes(array $attributes): int

   Extracts width from image attributes, preferring style attribute.

   :param array $attributes: Image tag attributes
   :returns: Integer width value
   :returntype: int
   :visibility: private

   **Priority:**

   1. Style attribute: ``style="width: 800px"``
   2. Width attribute: ``width="800"``

getImageHeightFromAttributes()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. php:method:: getImageHeightFromAttributes(array $attributes): int

   Extracts height from image attributes, preferring style attribute.

   :param array $attributes: Image tag attributes
   :returns: Integer height value
   :returntype: int
   :visibility: private

   **Priority:**

   1. Style attribute: ``style="height: 600px"``
   2. Height attribute: ``height="600"``

extractFromAttributeValueOrStyle()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. php:method:: extractFromAttributeValueOrStyle(array $attributes, string $imageAttribute)

   Generic extractor for image dimension from attributes or style.

   :param array $attributes: Image tag attributes array
   :param string $imageAttribute: Attribute name ('width' or 'height')
   :returns: Attribute value (mixed type) or null
   :visibility: private

matchStyleAttribute()
~~~~~~~~~~~~~~~~~~~~~

.. php:method:: matchStyleAttribute(string styleAttribute, string imageAttribute): string|null

   Extracts dimension value from CSS style attribute.

   :param string styleAttribute: CSS style string
   :param string imageAttribute: Attribute name to extract
   :returns: Extracted value or null
   :returntype: string|null
   :visibility: private

   **Pattern:** ``/width[[:space:]]*:[[:space:]]*([0-9]*)[[:space:]]*px/i``

   **Example:**

   .. code-block:: php

      $style = "width: 800px; height: 600px;";
      $width = $this->matchStyleAttribute($style, 'width');
      // Returns: "800"

resolveFieldConfigurationAndRespectColumnsOverrides()
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. php:method:: resolveFieldConfigurationAndRespectColumnsOverrides(DataHandler $dataHandler, string $table, string $field): array

   Gets TCA field configuration with type-specific overrides applied.

   :param DataHandler $dataHandler: Data handler instance
   :param string $table: Table name
   :param string $field: Field name
   :returns: Merged TCA configuration array
   :returntype: array
   :visibility: private

   **Use Case:** Handles cases where field config varies by content type (e.g., different RTE configs for header vs. bodytext).

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
           'type' => 'file',
           'tokenID' => '...',
           'tokenValue' => 'file:123',
           'recordRef' => 'sys_file:123'
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
               $queryBuilder->createNamedParameter(123, \PDO::PARAM_INT)
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

   # Check file processing status
   ./vendor/bin/typo3 cleanup:processedfiles

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
