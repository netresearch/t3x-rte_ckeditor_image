.. include:: /Includes.rst.txt

.. _examples-custom-extensions:

==================
Custom Extensions
==================

Advanced examples for extending the image plugin with custom fields, external image handling, multi-language support, and backend processing.

.. contents:: Table of Contents
   :depth: 3
   :local:

Custom Image Dialog
===================

Extended Image Properties
--------------------------

**Objective**: Add custom fields to image dialog

CKEditor Plugin Extension
~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: javascript
   :caption: EXT:my_site/Resources/Public/JavaScript/Plugins/extended-typo3image.js

   import { Plugin } from '@ckeditor/ckeditor5-core';

   export default class ExtendedTypo3Image extends Plugin {
       static get pluginName() {
           return 'ExtendedTypo3Image';
       }

       init() {
           const editor = this.editor;

           // Extend schema with custom attributes
           editor.model.schema.extend('typo3image', {
               allowAttributes: ['customCaption', 'customCopyright']
           });

           // Add upcast conversion
           editor.conversion.for('upcast').attributeToAttribute({
               view: 'data-custom-caption',
               model: 'customCaption'
           });

           // Add downcast conversion
           editor.conversion.for('downcast').attributeToAttribute({
               model: 'customCaption',
               view: 'data-custom-caption'
           });

           // Modify image dialog
           editor.on('typo3image:dialog', (evt, { dialog, modelElement }) => {
               // Add custom fields to dialog
               const customFields = $(`
                   <div class="form-group">
                       <label>Custom Caption</label>
                       <input type="text" class="form-control" name="customCaption"
                              value="${modelElement.getAttribute('customCaption') || ''}" />
                   </div>
                   <div class="form-group">
                       <label>Copyright</label>
                       <input type="text" class="form-control" name="customCopyright"
                              value="${modelElement.getAttribute('customCopyright') || ''}" />
                   </div>
               `);

               dialog.$el.append(customFields);

               // Override dialog.get() to include custom fields
               const originalGet = dialog.get;
               dialog.get = function() {
                   const attrs = originalGet.call(this);
                   attrs.customCaption = customFields.find('[name="customCaption"]').val();
                   attrs.customCopyright = customFields.find('[name="customCopyright"]').val();
                   return attrs;
               };
           });
       }
   }

Register Plugin
~~~~~~~~~~~~~~~

.. code-block:: yaml
   :caption: Configuration/RTE/Extended.yaml

   editor:
     config:
       importModules:
         - '@my-vendor/my-site/extended-typo3image.js'

**Result**: Custom image metadata fields ✅

External Image Handling
=======================

Fetch and Upload External Images
---------------------------------

Extension Configuration
~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php
   :caption: settings.php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'] = [
       'fetchExternalImages' => true,
   ];

Custom Upload Folder
~~~~~~~~~~~~~~~~~~~~

.. code-block:: typoscript
   :caption: User TSConfig

   options.defaultUploadFolder = 1:user_upload/rte_images/

Custom Fetch Handler
~~~~~~~~~~~~~~~~~~~~

.. code-block:: php
   :caption: EXT:my_site/Classes/Hooks/CustomImageFetchHook.php

   namespace MyVendor\MySite\Hooks;

   use TYPO3\CMS\Core\Resource\File;
   use TYPO3\CMS\Core\Resource\Folder;

   class CustomImageFetchHook
   {
       public function postProcessExternalImage(
           string $externalUrl,
           File $uploadedFile,
           Folder $targetFolder
       ): void {
           // Add custom metadata
           $uploadedFile->updateProperties([
               'title' => 'Imported from ' . parse_url($externalUrl, PHP_URL_HOST),
               'description' => 'Automatically fetched external image',
           ]);

           // Trigger image optimization
           $this->optimizeImage($uploadedFile);
       }

       protected function optimizeImage(File $file): void
       {
           // Custom optimization logic
           // e.g., compress, resize, convert format
       }
   }

Register Hook
~~~~~~~~~~~~~

.. code-block:: php
   :caption: ext_localconf.php

   $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['rte_ckeditor_image']['postProcessExternalImage'][]
       = \MyVendor\MySite\Hooks\CustomImageFetchHook::class . '->postProcessExternalImage';

**Result**: Automatic external image import ✅

Multi-Language Setup
====================

Language-Specific Image Variants
---------------------------------

Page TSConfig
~~~~~~~~~~~~~

.. code-block:: typoscript

   [siteLanguage("languageId") == 0]
       # Default language (English)
       RTE.default.preset = default
   [END]

   [siteLanguage("languageId") == 1]
       # German
       RTE.default.preset = german
       RTE.default.buttons.image.options.defaultUploadFolder = 1:user_upload/de/
   [END]

   [siteLanguage("languageId") == 2]
       # French
       RTE.default.preset = french
       RTE.default.buttons.image.options.defaultUploadFolder = 1:user_upload/fr/
   [END]

RTE Configuration
~~~~~~~~~~~~~~~~~

.. code-block:: yaml
   :caption: Configuration/RTE/German.yaml

   imports:
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

   editor:
     config:
       language: de
       style:
         definitions:
           - name: 'Bild Links'
             element: 'img'
             classes: ['float-left']
           - name: 'Bild Rechts'
             element: 'img'
             classes: ['float-right']

**Result**: Language-specific configurations ✅

Custom Backend Processing
==========================

Automatic Image Optimization
-----------------------------

Custom Hook
~~~~~~~~~~~

.. code-block:: php
   :caption: EXT:my_site/Classes/Hooks/ImageOptimizationHook.php

   namespace MyVendor\MySite\Hooks;

   use TYPO3\CMS\Core\DataHandling\DataHandler;
   use TYPO3\CMS\Core\Resource\FileRepository;
   use TYPO3\CMS\Core\Utility\GeneralUtility;

   class ImageOptimizationHook
   {
       public function processDatamap_afterDatabaseOperations(
           string $status,
           string $table,
           string $id,
           array $fieldArray,
           DataHandler $dataHandler
       ): void {
           if ($table !== 'tt_content') {
               return;
           }

           foreach ($fieldArray as $field => $value) {
               if (!$this->isRteField($table, $field)) {
                   continue;
               }

               // Find images in RTE content
               preg_match_all('/data-htmlarea-file-uid="(\d+)"/', $value, $matches);

               foreach ($matches[1] as $fileUid) {
                   $this->optimizeImage((int)$fileUid);
               }
           }
       }

       protected function isRteField(string $table, string $field): bool
       {
           $tcaConfig = $GLOBALS['TCA'][$table]['columns'][$field]['config'] ?? [];
           return ($tcaConfig['enableRichtext'] ?? false) === true;
       }

       protected function optimizeImage(int $fileUid): void
       {
           $fileRepository = GeneralUtility::makeInstance(FileRepository::class);

           try {
               $file = $fileRepository->findByUid($fileUid);

               // Generate optimized variants
               $file->process(
                   \TYPO3\CMS\Core\Resource\ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                   ['width' => 1920, 'height' => 1080]
               );

               // Generate WebP variant
               $file->process(
                   \TYPO3\CMS\Core\Resource\ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                   ['width' => 1920, 'height' => 1080, 'fileExtension' => 'webp']
               );
           } catch (\Exception $e) {
               // Log error
           }
       }
   }

Register Hook
~~~~~~~~~~~~~

.. code-block:: php
   :caption: ext_localconf.php

   $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
       = \MyVendor\MySite\Hooks\ImageOptimizationHook::class;

**Result**: Automatic optimization on save ✅

Related Documentation
=====================

- :ref:`ckeditor-plugin-development` - Plugin development guide
- :ref:`api-eventlisteners` - Event listener API
- :ref:`examples-testing` - Testing examples
- :ref:`integration-configuration` - Configuration guide
