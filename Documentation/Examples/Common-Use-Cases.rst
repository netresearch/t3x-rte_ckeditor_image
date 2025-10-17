.. include:: /Includes.rst.txt

.. _examples-common-use-cases:

============================
Common Use Cases & Examples
============================

Practical, ready-to-use examples for common implementation scenarios with rte_ckeditor_image.

.. contents:: Table of Contents
   :depth: 3
   :local:

Basic Integration
=================

Minimal Setup
-------------

**Objective**: Get basic image functionality working

Step 1: Install Extension
~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: bash

   composer require netresearch/rte-ckeditor-image:^13.0

Step 2: Create RTE Configuration
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: yaml
   :caption: EXT:my_site/Configuration/RTE/Default.yaml

   imports:
     - { resource: "EXT:rte_ckeditor/Configuration/RTE/Default.yaml" }
     - { resource: "EXT:rte_ckeditor_image/Configuration/RTE/Plugin.yaml" }

   editor:
     config:
       removePlugins: null
       toolbar:
         items:
           - heading
           - '|'
           - bold
           - italic
           - '|'
           - insertimage
           - link

Step 3: Register Preset
~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: php
   :caption: EXT:my_site/ext_localconf.php

   $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['default']
       = 'EXT:my_site/Configuration/RTE/Default.yaml';

Step 4: Enable in Page TSConfig
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: typoscript
   :caption: Configuration/page.tsconfig

   RTE.default.preset = default

Step 5: Include Static Template
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

- Backend → Template → Edit Template
- Include static: "CKEditor Image Support"

**Result**: Basic image insertion working ✅

Custom Image Styles
===================

Bootstrap-Style Images
----------------------

**Objective**: Add Bootstrap image utility classes

Configuration
~~~~~~~~~~~~~

.. code-block:: yaml
   :caption: EXT:my_site/Configuration/RTE/Default.yaml

   editor:
     config:
       style:
         definitions:
           # Alignment Styles
           - name: 'Float Left'
             element: 'img'
             classes: ['float-start', 'me-3', 'mb-3']
           - name: 'Float Right'
             element: 'img'
             classes: ['float-end', 'ms-3', 'mb-3']
           - name: 'Center'
             element: 'img'
             classes: ['d-block', 'mx-auto']

           # Size Styles
           - name: 'Thumbnail'
             element: 'img'
             classes: ['img-thumbnail']
           - name: 'Rounded'
             element: 'img'
             classes: ['rounded']
           - name: 'Circle'
             element: 'img'
             classes: ['rounded-circle']

           # Responsive
           - name: 'Responsive'
             element: 'img'
             classes: ['img-fluid']

         groupDefinitions:
           - name: 'Image Alignment'
             styles: ['Float Left', 'Float Right', 'Center']
           - name: 'Image Style'
             styles: ['Thumbnail', 'Rounded', 'Circle', 'Responsive']

CSS (if not using Bootstrap)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: css
   :caption: EXT:my_site/Resources/Public/Css/rte-images.css

   .float-start { float: left; }
   .float-end { float: right; }
   .me-3 { margin-right: 1rem; }
   .ms-3 { margin-left: 1rem; }
   .mb-3 { margin-bottom: 1rem; }
   .d-block { display: block; }
   .mx-auto { margin-left: auto; margin-right: auto; }

   .img-thumbnail {
       padding: 0.25rem;
       background-color: #fff;
       border: 1px solid #dee2e6;
       border-radius: 0.25rem;
       max-width: 100%;
       height: auto;
   }

   .rounded { border-radius: 0.25rem; }
   .rounded-circle { border-radius: 50%; }
   .img-fluid { max-width: 100%; height: auto; }

**Result**: Professional image styling options ✅

Responsive Images
=================

Automatic srcset Generation
----------------------------

**Objective**: Generate responsive images with srcset

TypoScript Setup
~~~~~~~~~~~~~~~~

.. code-block:: typoscript
   :caption: EXT:my_site/Configuration/TypoScript/setup.typoscript

   lib.parseFunc_RTE {
       tags.img = TEXT
       tags.img {
           current = 1
           preUserFunc = MyVendor\MySite\UserFunc\ResponsiveImageRenderer->render
       }
   }

PHP Implementation
~~~~~~~~~~~~~~~~~~

.. code-block:: php
   :caption: EXT:my_site/Classes/UserFunc/ResponsiveImageRenderer.php

   namespace MyVendor\MySite\UserFunc;

   use TYPO3\CMS\Core\Resource\FileRepository;
   use TYPO3\CMS\Core\Resource\ProcessedFile;
   use TYPO3\CMS\Core\Utility\GeneralUtility;
   use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

   class ResponsiveImageRenderer
   {
       public function render(
           string $content,
           array $conf,
           ContentObjectRenderer $cObj
       ): string {
           // Parse img tag
           if (!preg_match('/<img([^>]*)>/i', $content, $imgMatch)) {
               return $content;
           }

           // Extract data-htmlarea-file-uid
           if (!preg_match('/data-htmlarea-file-uid="(\d+)"/', $imgMatch[1], $uidMatch)) {
               return $content;
           }

           $fileUid = (int)$uidMatch[1];

           // Get FAL file
           $fileRepository = GeneralUtility::makeInstance(FileRepository::class);
           try {
               $file = $fileRepository->findByUid($fileUid);
           } catch (\Exception $e) {
               return $content;
           }

           // Generate responsive variants
           $breakpoints = [
               'xs' => 480,
               'sm' => 768,
               'md' => 992,
               'lg' => 1200,
               'xl' => 1920
           ];

           $srcsetParts = [];
           foreach ($breakpoints as $name => $width) {
               $processedFile = $file->process(
                   ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                   ['width' => $width]
               );
               $srcsetParts[] = $processedFile->getPublicUrl() . ' ' . $width . 'w';
           }

           $srcset = implode(', ', $srcsetParts);
           $sizes = '(max-width: 768px) 100vw, (max-width: 992px) 50vw, 33vw';

           // Replace img tag with srcset version
           $newImg = str_replace(
               '<img',
               '<img srcset="' . htmlspecialchars($srcset) . '" sizes="' . $sizes . '"',
               $imgMatch[0]
           );

           return str_replace($imgMatch[0], $newImg, $content);
       }
   }

Result HTML
~~~~~~~~~~~

.. code-block:: html

   <img
       src="/fileadmin/image.jpg"
       srcset="/fileadmin/_processed_/image_480.jpg 480w,
               /fileadmin/_processed_/image_768.jpg 768w,
               /fileadmin/_processed_/image_992.jpg 992w,
               /fileadmin/_processed_/image_1200.jpg 1200w,
               /fileadmin/_processed_/image_1920.jpg 1920w"
       sizes="(max-width: 768px) 100vw, (max-width: 992px) 50vw, 33vw"
       alt="Image description"
   />

**Result**: Automatic responsive images ✅

Lightbox Integration
====================

PhotoSwipe Lightbox
-------------------

**Objective**: Add lightbox functionality to images

Install PhotoSwipe
~~~~~~~~~~~~~~~~~~

.. code-block:: bash

   npm install photoswipe

TypoScript Setup
~~~~~~~~~~~~~~~~

.. code-block:: typoscript

   page {
       includeJSFooterlibs {
           photoswipe = EXT:my_site/Resources/Public/JavaScript/photoswipe.min.js
           photoswipe_init = EXT:my_site/Resources/Public/JavaScript/lightbox-init.js
       }

       includeCSS {
           photoswipe = EXT:my_site/Resources/Public/Css/photoswipe.css
       }
   }

   lib.parseFunc_RTE {
       tags.img = TEXT
       tags.img {
           current = 1
           preUserFunc = MyVendor\MySite\UserFunc\LightboxImageRenderer->render
       }
   }

PHP Wrapper
~~~~~~~~~~~

.. code-block:: php
   :caption: EXT:my_site/Classes/UserFunc/LightboxImageRenderer.php

   namespace MyVendor\MySite\UserFunc;

   use TYPO3\CMS\Core\Resource\FileRepository;
   use TYPO3\CMS\Core\Utility\GeneralUtility;
   use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

   class LightboxImageRenderer
   {
       public function render(
           string $content,
           array $conf,
           ContentObjectRenderer $cObj
       ): string {
           // Check if zoom enabled
           if (strpos($content, 'data-htmlarea-zoom') === false) {
               return $content;
           }

           // Extract file UID
           if (!preg_match('/data-htmlarea-file-uid="(\d+)"/', $content, $match)) {
               return $content;
           }

           $fileUid = (int)$match[1];
           $fileRepository = GeneralUtility::makeInstance(FileRepository::class);

           try {
               $file = $fileRepository->findByUid($fileUid);
           } catch (\Exception $e) {
               return $content;
           }

           // Remove data attributes for frontend
           $content = preg_replace('/\s*data-htmlarea-[^=]+="[^"]*"/', '', $content);

           // Wrap in lightbox link
           $lightboxLink = sprintf(
               '<a href="%s" data-pswp-width="%d" data-pswp-height="%d" target="_blank">%s</a>',
               $file->getPublicUrl(),
               $file->getProperty('width'),
               $file->getProperty('height'),
               $content
           );

           return $lightboxLink;
       }
   }

JavaScript Initialization
~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block:: javascript
   :caption: EXT:my_site/Resources/Public/JavaScript/lightbox-init.js

   import PhotoSwipeLightbox from 'photoswipe/lightbox';
   import 'photoswipe/style.css';

   const lightbox = new PhotoSwipeLightbox({
       gallery: '.ce-bodytext',
       children: 'a[data-pswp-width]',
       pswpModule: () => import('photoswipe'),
   });

   lightbox.init();

**Result**: Click-to-enlarge images with lightbox ✅

Lazy Loading
============

Native Lazy Loading
-------------------

**Objective**: Improve page load performance with native lazy loading

TypoScript Setup
~~~~~~~~~~~~~~~~

.. code-block:: typoscript

   lib.parseFunc_RTE {
       nonTypoTagStdWrap.HTMLparser.tags.img {
           fixAttrib {
               loading {
                   set = lazy
               }
               # Remove internal attributes
               data-htmlarea-file-uid.unset = 1
               data-htmlarea-file-table.unset = 1
               data-htmlarea-zoom.unset = 1
           }
       }
   }

Result HTML
~~~~~~~~~~~

.. code-block:: html

   <img src="..." loading="lazy" alt="..." />

Intersection Observer Fallback
-------------------------------

**For older browsers**:

TypoScript
~~~~~~~~~~

.. code-block:: typoscript

   page.includeJSFooterlibs.lazyload = EXT:my_site/Resources/Public/JavaScript/lazyload.js

   lib.parseFunc_RTE {
       nonTypoTagStdWrap.HTMLparser.tags.img {
           fixAttrib {
               class {
                   list = lazyload
               }
               data-src {
                   # Copy src to data-src
                   stdWrap.field = src
               }
               src {
                   # Set placeholder
                   set = data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 3 2'%3E%3C/svg%3E
               }
           }
       }
   }

JavaScript
~~~~~~~~~~

.. code-block:: javascript
   :caption: EXT:my_site/Resources/Public/JavaScript/lazyload.js

   document.addEventListener('DOMContentLoaded', function() {
       const imageObserver = new IntersectionObserver((entries, observer) => {
           entries.forEach(entry => {
               if (entry.isIntersecting) {
                   const img = entry.target;
                   img.src = img.dataset.src;
                   img.classList.remove('lazyload');
                   imageObserver.unobserve(img);
               }
           });
       });

       document.querySelectorAll('img.lazyload').forEach(img => {
           imageObserver.observe(img);
       });
   });

**Result**: Progressive image loading ✅

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

Testing Examples
================

Functional Test
---------------

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
---------

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
~~~~~~~~~

.. code-block:: bash

   # Functional tests
   ./vendor/bin/phpunit -c Build/phpunit-functional.xml

   # Unit tests
   ./vendor/bin/phpunit -c Build/phpunit-unit.xml

**Result**: Automated testing suite ✅

Related Documentation
=====================

- :ref:`integration-configuration`
- :ref:`ckeditor-plugin-development`
- :ref:`api-controllers`
- :ref:`troubleshooting-common-issues`
