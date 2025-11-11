.. include:: /Includes.rst.txt

.. _core-removal:

====================================
TYPO3 Core Removal & Design Decision
====================================

.. important::

   **Before installing this extension, please read this page carefully.**

   This extension re-implements functionality that TYPO3 core **intentionally removed**
   in version 10.0. Understanding the reasoning behind this removal will help you make
   an informed decision about whether this extension is right for your project.


.. _core-removal-what:

What TYPO3 Removed
==================

In TYPO3 v10.0 (`Breaking #88500 <https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/10.0/Breaking-88500-RTEImageHandlingFunctionalityDropped.html>`__),
the core team removed the RTE image handling functionality:

Removed Components
------------------

- **RTE processing mode** ("ts_images")
- **SoftReference Index** for inline images
- **Magic Image processing** (automatic scaling, cropping via TSConfig)
- **Image storage handling** (``RTE_imageStorageDir``)
- **CLI cleanup command** (``cleanup:rteimages``)
- **Public API methods** (``ImportExport->getRTEoriginalFilename()``, ``RteHtmlParser->TS_images_rte()``)

Later Deprecation
-----------------

In TYPO3 v12.4 (`Deprecation #99237 <https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.4/Deprecation-99237-MagicImageService.html>`__),
the **MagicImageService** class was deprecated with no direct migration path.


.. _core-removal-why:

Why TYPO3 Removed This
=======================

The TYPO3 core team removed this functionality for several architectural reasons:

1. **Obsolete Technology**

   CKEditor replaced RTEHtmlArea in TYPO3 v8, making the native RTE image handling
   unused and obsolete.

2. **Incomplete Implementation**

   The changelog explicitly states the functionality was **"very incomplete"** compared
   to modern alternatives.

3. **Architectural Philosophy**

   TYPO3 promotes **structured content** over inline mixed content. Storing images
   as relations (FAL references) provides better:

   - Content reuse across multiple elements
   - Metadata management (alt text, copyright, descriptions)
   - Image variant generation (responsive images, WebP conversion)
   - Migration and content import/export
   - Multi-language handling
   - Permission and access control
   - Asset management and organization


.. _core-removal-typo3-recommendation:

TYPO3's Official Recommendation
================================

The breaking change documentation recommends:

**Primary Approach: Structured Content**
-----------------------------------------

Move images from inline RTE fields to **proper relational fields**:

.. code-block:: php

   // TCA Configuration Example
   'columns' => [
       'bodytext' => [
           'config' => [
               'type' => 'text',
               'enableRichtext' => true,
           ],
       ],
       'images' => [
           'config' => [
               'type' => 'file',
               'allowed' => 'common-image-types',
               'maxitems' => 10,
           ],
       ],
   ],

**Benefits of Structured Content:**

- ✅ Better content reuse
- ✅ Proper metadata management
- ✅ Responsive image generation
- ✅ Clean separation of concerns
- ✅ Modern TYPO3 architecture
- ✅ Better editor experience

**Fallback Approach: Extensions**
----------------------------------

For projects that need inline image functionality, TYPO3 recommends extensions
like ``rte_ckeditor_image`` or creating custom extension implementations.


.. _core-removal-why-this-extension:

Why This Extension Exists
==========================

Despite TYPO3's architectural direction, this extension exists because:

Real-World Requirements
-----------------------

1. **Legacy Content Migration**

   Many TYPO3 installations have years of content with inline images. Migrating
   to structured content requires significant time and resources.

2. **Editorial Workflows**

   Some editorial teams are trained on inline image workflows and prefer
   WYSIWYG image placement directly in text.

3. **Content Nature**

   Certain content types (news articles, blog posts, documentation) naturally
   contain inline images that are contextually bound to surrounding text.

4. **Migration Bridge**

   Provides a transition path while planning migration to structured content.

What This Extension Provides
-----------------------------

- **Backward compatibility** with RTEHtmlArea image workflows
- **Magic Image processing** (automatic scaling via TSConfig)
- **TYPO3 FAL integration** (native file browser)
- **Modern CKEditor 5** implementation
- **Image attributes** (width, height, alt, title, quality)
- **Custom styles** via CKEditor style system
- **Event-driven architecture** for extensibility


.. _core-removal-decision-guide:

Decision Guide: Should You Use This Extension?
===============================================

Use This Extension When
-----------------------

✅ **You have legacy content** with extensive inline images that cannot be migrated immediately

✅ **Editorial workflow requires** inline image placement with WYSIWYG editing

✅ **Content is tightly coupled** to surrounding text (inline diagrams, screenshots, examples)

✅ **Migration timeline** is long and you need a working solution now

✅ **Small to medium projects** where structured content overhead isn't justified

Follow TYPO3 Guidelines Instead When
-------------------------------------

❌ **Starting a new project** - Build with structured content from the beginning

❌ **Images are reusable** - Same images appear across multiple content elements

❌ **Need advanced features** - Responsive images, WebP conversion, image variants

❌ **Multi-language sites** - Image metadata needs proper translation workflows

❌ **Large editorial teams** - Structured content provides better governance

❌ **Long-term maintainability** - Align with TYPO3's architectural direction


.. _core-removal-hybrid-approach:

Hybrid Approach
===============

You can use **both approaches** in the same TYPO3 installation:

- **Structured content** for main images, galleries, and reusable assets
- **Inline images** (this extension) for contextual images in rich text

Example TCA configuration:

.. code-block:: php

   'columns' => [
       'header_image' => [
           // Structured: Main article image
           'config' => [
               'type' => 'file',
               'allowed' => 'common-image-types',
               'maxitems' => 1,
           ],
       ],
       'bodytext' => [
           // Inline: Contextual images within text
           'config' => [
               'type' => 'text',
               'enableRichtext' => true,
               // rte_ckeditor_image provides inline functionality
           ],
       ],
       'gallery' => [
           // Structured: Image gallery
           'config' => [
               'type' => 'file',
               'allowed' => 'common-image-types',
               'maxitems' => 20,
           ],
       ],
   ],


.. _core-removal-migration-path:

Future Migration Path
=====================

If you use this extension now but plan to migrate to structured content later:

Planning Migration
------------------

1. **Audit content** - Identify all RTE fields with inline images
2. **Create TCA fields** - Add proper FAL reference fields
3. **Write migration script** - Extract inline images to relations
4. **Update templates** - Adjust Fluid templates for structured content
5. **Train editors** - Update editorial workflows and documentation

Migration Tools
---------------

TYPO3 provides tools for content migration:

- **Data Handler API** for programmatic content updates
- **TypoScript** processors for rendering
- **CLI commands** for batch processing

This extension can coexist during the migration period.


.. _core-removal-best-practices:

Best Practices If Using This Extension
=======================================

1. **Document the Decision**

   Add notes to your project documentation explaining why inline images
   are used and what the long-term plan is.

2. **Set Editor Guidelines**

   Define when editors should use inline images vs. structured image fields.

3. **Configure Processing**

   Use magic image configuration (TSConfig) to control automatic scaling:

   .. code-block:: typoscript

      RTE.default.buttons.image.options.magic {
          maxWidth = 1920
          maxHeight = 9999
      }

4. **Monitor TYPO3 Updates**

   Stay informed about TYPO3's direction regarding RTE and CKEditor.

5. **Plan Migration**

   If project lifespan is long, plan eventual migration to structured content.


.. _core-removal-conclusion:

Conclusion
==========

This extension serves as a **pragmatic bridge** between TYPO3's architectural
direction (structured content) and real-world editorial needs (inline images).

**Key Takeaways:**

- TYPO3 intentionally removed RTE image handling for good architectural reasons
- Structured content is the recommended modern approach
- This extension provides backward compatibility when needed
- Consider your project's specific requirements, timeline, and resources
- A hybrid approach (both structured and inline) is valid
- Plan for eventual migration if project lifespan is long

**Questions to ask:**

1. Do we have time/budget to migrate existing content?
2. Does our editorial team need inline image placement?
3. Are our images contextually bound to text or reusable assets?
4. What is our project's expected lifespan?
5. Can we align with TYPO3's architectural direction?

The "right" choice depends on your specific context. There is no universal answer.


.. _core-removal-resources:

Additional Resources
====================

**TYPO3 Core Documentation:**

- `Breaking #88500 - RTE Image Handling Removed <https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/10.0/Breaking-88500-RTEImageHandlingFunctionalityDropped.html>`__
- `Deprecation #99237 - MagicImageService <https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.4/Deprecation-99237-MagicImageService.html>`__
- `File Abstraction Layer (FAL) <https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Fal/Index.html>`__
- `Content Elements <https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/ContentElements/Index.html>`__

**This Extension:**

- :ref:`Quick Start <quick-start>`
- :ref:`Configuration <integration-configuration>`
- :ref:`Troubleshooting <troubleshooting-index>`
