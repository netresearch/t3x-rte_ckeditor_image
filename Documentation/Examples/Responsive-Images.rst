.. include:: /Includes.rst.txt

.. Copyright (c) 2025-2026 Netresearch DTT GmbH
   SPDX-License-Identifier: AGPL-3.0-or-later

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

   use Psr\Http\Message\ServerRequestInterface;
   use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
   use TYPO3\CMS\Core\Resource\ProcessedFile;
   use TYPO3\CMS\Core\Resource\ResourceFactory;
   use TYPO3\CMS\Core\Utility\GeneralUtility;
   use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

   class ResponsiveImageRenderer
   {
       private ?ContentObjectRenderer $cObj = null;

       // Called by TYPO3 before render()
       public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
       {
           $this->cObj = $cObj;
       }

       // TYPO3 v14 calls only methods marked with #[AsAllowedCallable]
       #[AsAllowedCallable]
       public function render(
           ?string $content,
           array $conf,
           ServerRequestInterface $request
       ): string {
           // <img> is an empty tag: parseFunc passes no content to tags.img,
           // but puts the tag's attributes into the content object's parameters.
           $attributes = $this->cObj?->parameters ?? [];
           $originalTag = '<img ' . trim((string)($attributes['allParams'] ?? '')) . '>';

           $fileUid = (int)($attributes['data-htmlarea-file-uid'] ?? 0);
           if ($fileUid <= 0) {
               return $originalTag;
           }

           try {
               $file = GeneralUtility::makeInstance(ResourceFactory::class)
                   ->getFileObject($fileUid);
           } catch (\Exception) {
               return $originalTag;
           }

           // Generate responsive variants
           $breakpoints = [480, 768, 992, 1200, 1920];

           $srcsetParts = [];
           foreach ($breakpoints as $width) {
               $processedFile = $file->process(
                   ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                   ['width' => $width]
               );
               $srcsetParts[] = $processedFile->getPublicUrl() . ' ' . $width . 'w';
           }

           $srcset = implode(', ', $srcsetParts);
           $sizes = '(max-width: 768px) 100vw, (max-width: 992px) 50vw, 33vw';

           return '<img srcset="' . htmlspecialchars($srcset) . '"'
               . ' sizes="' . $sizes . '" '
               . trim((string)($attributes['allParams'] ?? '')) . '>';
       }
   }

.. note::
   This renderer replaces the extension's ``renderImageAttributes()`` for
   ``tags.img``, so quality scaling, the non-public storage check and the
   other processing of the extension no longer run for these images.

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
