.. include:: /Includes.rst.txt

.. _examples-responsive-images:

=================
Responsive Images
=================

Examples for implementing responsive images with srcset and automatic generation.

.. contents:: Table of Contents
   :depth: 3
   :local:

Automatic srcset Generation
============================

**Objective**: Generate responsive images with srcset

TypoScript Setup
----------------

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
------------------

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
-----------

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

Related Documentation
=====================

- :ref:`examples-advanced-features` - Lightbox and lazy loading
- :ref:`integration-configuration` - Configuration guide
- :ref:`api-controllers` - Controller APIs
