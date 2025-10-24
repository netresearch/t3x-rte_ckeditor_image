.. include:: /Includes.rst.txt

.. _troubleshooting-performance-issues:

====================
Performance Issues
====================

Solutions for performance problems, slow loading, and optimization strategies.

.. contents:: Table of Contents
   :local:
   :depth: 2

Editor Performance
==================

Issue: Slow Editor Loading
---------------------------

**Symptoms:**

* CKEditor takes long time to initialize
* Image browser slow to open

**Causes:**

1. Large number of files in file browser
2. Unoptimized image processing settings
3. Network latency
4. Browser resource constraints

**Solutions:**

1. **Optimize Image Processing:**

.. code-block:: php

   // LocalConfiguration.php
   $GLOBALS['TYPO3_CONF_VARS']['GFX']['jpg_quality'] = 85;
   $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_effects'] = false;  // If not needed

2. **Reduce Maximum Dimensions:**

.. code-block:: typoscript

   RTE.default.buttons.image.options.magic {
       maxWidth = 1200  # Instead of 1920
       maxHeight = 800  # Instead of 9999
   }

3. **Limit File Browser Results:**

Configure file mounts to show only relevant directories:

.. code-block:: typoscript

   # User TSConfig
   options.folderTree.uploadFieldsInLinkBrowser = 0
   options.pageTree.showPageIdWithTitle = 0

.. tip::
   Reducing maximum dimensions can significantly improve editor performance.

----

Issue: Image Selection Dialog Slow
-----------------------------------

**Symptoms:**

* Modal takes long time to open
* Thumbnails load slowly
* Browser becomes unresponsive

**Causes:**

1. Too many files in directory
2. Large unprocessed images
3. Missing thumbnail cache

**Solutions:**

1. **Organize Files into Subdirectories:**

   * Group images by category
   * Use year/month folder structure
   * Keep directories under 100 files

2. **Pre-generate Thumbnails:**

.. code-block:: bash

   # Generate missing thumbnails
   ./vendor/bin/typo3 cleanup:missingfiles
   ./vendor/bin/typo3 cleanup:previewlinks

3. **Optimize File Storage:**

.. code-block:: typoscript

   # Limit file selection to specific folders
   options.defaultUploadFolder = 1:fileadmin/user_upload/

----

Frontend Performance
====================

Issue: Slow Page Load Due to Images
------------------------------------

**Symptoms:**

* Pages load slowly
* Large images not processed
* High bandwidth usage

**Causes:**

1. Original large images served instead of processed versions
2. No image compression
3. Missing lazy loading
4. Too many images on page

**Solutions:**

1. **Enable Image Processing:**

.. code-block:: php

   // LocalConfiguration.php
   $GLOBALS['TYPO3_CONF_VARS']['GFX'] = [
       'processor_enabled' => true,
       'processor' => 'ImageMagick',
       'jpg_quality' => 85,
   ];

2. **Configure Reasonable Maximum Dimensions:**

.. code-block:: typoscript

   RTE.default.buttons.image.options.magic {
       maxWidth = 1920
       maxHeight = 1080
   }

3. **Implement Lazy Loading:**

.. code-block:: typoscript

   lib.parseFunc_RTE.tags.img {
       current = 1
       preUserFunc = Netresearch\RteCKEditorImage\Controller\ImageRenderingController->renderImageAttributes
       stdWrap.replacement {
           10 {
               search = <img
               replace = <img loading="lazy"
           }
       }
   }

4. **Enable Browser Caching:**

.. code-block:: apache

   # .htaccess
   <IfModule mod_expires.c>
       ExpiresActive On
       ExpiresByType image/jpeg "access plus 1 year"
       ExpiresByType image/png "access plus 1 year"
       ExpiresByType image/webp "access plus 1 year"
   </IfModule>

----

Issue: Excessive Bandwidth Usage
---------------------------------

**Symptoms:**

* High server bandwidth consumption
* Slow site on mobile devices
* Large page sizes

**Solutions:**

1. **Use WebP Format:**

.. code-block:: php

   // LocalConfiguration.php
   $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_allowUpscaling'] = false;
   $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_stripColorProfileByDefault'] = true;

2. **Implement Progressive JPEG:**

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_interlace'] = true;

3. **Compress Images:**

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['GFX']['jpg_quality'] = 80;

----

Image Processing Performance
=============================

Issue: Image Processing Timeouts
---------------------------------

**Symptoms:**

* 500 errors when uploading images
* Processing hangs
* Timeouts in backend

**Causes:**

1. Insufficient PHP memory
2. Low execution time limits
3. Slow image processor
4. Very large source images

**Solutions:**

1. **Increase PHP Limits:**

.. code-block:: php

   // php.ini or LocalConfiguration.php
   memory_limit = 512M
   max_execution_time = 300
   upload_max_filesize = 20M
   post_max_size = 20M

2. **Optimize Image Processor:**

.. code-block:: php

   // LocalConfiguration.php
   $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_path_lzw'] = '';
   $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_colorspace'] = 'RGB';

3. **Limit Upload Size:**

.. code-block:: typoscript

   # Page TSConfig
   RTE.default.buttons.image.options.magic {
       maxFileSize = 5000  # 5MB in KB
   }

----

Issue: Processed Images Directory Growing
------------------------------------------

**Symptoms:**

* Large ``_processed_/`` directory
* Disk space issues
* Duplicate processed images

**Causes:**

1. Old processed images not cleaned up
2. Multiple versions of same image
3. Unused processed files accumulating

**Solutions:**

1. **Clean Old Processed Files:**

.. code-block:: bash

   # Remove processed files older than 30 days
   find fileadmin/_processed_ -type f -mtime +30 -delete

2. **Implement Automated Cleanup:**

.. code-block:: php

   // LocalConfiguration.php
   $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_allowTemporaryMasksAsPng'] = false;

3. **Schedule Cleanup Task:**

Create a scheduler task to regularly clean old processed images:

.. code-block:: bash

   # Cron job example (runs weekly)
   0 2 * * 0 find /path/to/fileadmin/_processed_ -type f -mtime +30 -delete

----

Database Performance
====================

Issue: Large Database Size
---------------------------

**Symptoms:**

* Database growing rapidly
* sys_refindex table very large
* Slow queries

**Causes:**

1. Excessive soft reference entries
2. Orphaned records
3. Unoptimized indexes

**Solutions:**

1. **Rebuild Reference Index:**

.. code-block:: bash

   ./vendor/bin/typo3 referenceindex:update

2. **Clean Up Orphaned Records:**

.. code-block:: sql

   -- Find images in deleted content
   SELECT uid, bodytext
   FROM tt_content
   WHERE deleted = 1 AND bodytext LIKE '%data-htmlarea-file-uid%';

3. **Optimize Tables:**

.. code-block:: sql

   OPTIMIZE TABLE sys_refindex;
   OPTIMIZE TABLE tt_content;

----

Issue: Slow Reference Index Updates
------------------------------------

**Symptoms:**

* Reference index update takes long time
* High CPU usage during updates

**Solutions:**

1. **Batch Update References:**

.. code-block:: bash

   # Update in smaller batches
   ./vendor/bin/typo3 referenceindex:update --check

2. **Schedule Off-Peak Updates:**

Run reference index updates during low-traffic periods:

.. code-block:: bash

   # Cron job (runs at 3 AM)
   0 3 * * * /path/to/vendor/bin/typo3 referenceindex:update

----

Cache Performance
=================

Issue: Excessive Cache Invalidation
------------------------------------

**Symptoms:**

* Caches cleared too frequently
* Slow page regeneration
* High server load

**Causes:**

1. Aggressive cache clearing
2. Content changes trigger full cache clear
3. Misconfigured cache tags

**Solutions:**

1. **Optimize Cache Configuration:**

.. code-block:: php

   // LocalConfiguration.php
   $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pages'] = [
       'backend' => \TYPO3\CMS\Core\Cache\Backend\RedisBackend::class,
       'options' => [
           'hostname' => 'localhost',
           'database' => 0,
           'port' => 6379,
       ],
   ];

2. **Use Cache Tags Effectively:**

.. code-block:: typoscript

   config {
       cache_period = 86400
       sendCacheHeaders = 1
   }

3. **Implement Selective Cache Clearing:**

.. code-block:: bash

   # Clear only specific cache groups
   ./vendor/bin/typo3 cache:flush --group=pages

----

Network Performance
===================

Issue: Slow Image Loading from CDN
-----------------------------------

**Symptoms:**

* Images load slowly despite CDN
* Inconsistent loading times
* Failed CDN requests

**Solutions:**

1. **Configure Proper CDN:**

.. code-block:: typoscript

   config {
       absRefPrefix = /
       baseURL = https://cdn.yourdomain.com/
   }

2. **Use HTTP/2:**

Ensure your server supports HTTP/2 for multiplexing

3. **Implement Preconnect:**

.. code-block:: html

   <link rel="preconnect" href="https://cdn.yourdomain.com">
   <link rel="dns-prefetch" href="https://cdn.yourdomain.com">

----

Monitoring and Profiling
=========================

Performance Monitoring
----------------------

1. **Enable TYPO3 Admin Panel:**

.. code-block:: typoscript

   config.admPanel = 1

2. **Monitor Image Processing:**

.. code-block:: bash

   # Check processing time
   ./vendor/bin/typo3 backend:test:imageprocessing

3. **Track Page Load Times:**

Use browser DevTools Network tab to monitor:
   * Image load times
   * TTFB (Time to First Byte)
   * Total page load time

Database Query Profiling
-------------------------

.. code-block:: sql

   -- Find slow queries
   SHOW PROCESSLIST;

   -- Analyze reference index queries
   EXPLAIN SELECT * FROM sys_refindex WHERE tablename='tt_content';

Image Processing Profiling
---------------------------

.. code-block:: bash

   # Time image processing
   time ./vendor/bin/typo3 backend:test:imageprocessing

   # Monitor processed files
   watch -n 5 "ls -lh fileadmin/_processed_/ | wc -l"

----

Optimization Best Practices
============================

Image Optimization Checklist
-----------------------------

✓ **Configure reasonable maximum dimensions:**

.. code-block:: typoscript

   RTE.default.buttons.image.options.magic {
       maxWidth = 1920
       maxHeight = 1080
   }

✓ **Enable image processing:**

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['GFX']['processor_enabled'] = true;

✓ **Set appropriate JPEG quality:**

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['GFX']['jpg_quality'] = 85;

✓ **Implement lazy loading:**

.. code-block:: html

   <img loading="lazy" src="..." />

✓ **Use browser caching:**

.. code-block:: apache

   ExpiresActive On
   ExpiresByType image/jpeg "access plus 1 year"

✓ **Regular cleanup of processed files:**

.. code-block:: bash

   find fileadmin/_processed_ -type f -mtime +30 -delete

✓ **Monitor disk space and database size**

✓ **Optimize images before upload** (recommend to editors)

Server Optimization Checklist
------------------------------

✓ **Adequate PHP memory:** 512M minimum

✓ **Fast image processor:** ImageMagick or GraphicsMagick

✓ **Redis/Memcached for caching**

✓ **HTTP/2 support enabled**

✓ **CDN for static assets**

✓ **Regular database optimization**

✓ **Scheduled cleanup tasks**

----

Related Documentation
=====================

**Other Troubleshooting Topics:**

* :ref:`troubleshooting-installation-issues` - Installation and setup problems
* :ref:`troubleshooting-editor-issues` - Editor and backend problems
* :ref:`troubleshooting-frontend-issues` - Frontend rendering issues

**Additional Resources:**

* :ref:`integration-configuration` - Configuration guide
* :ref:`architecture-system-components` - Image processing details
* :ref:`best-practices` - Performance best practices

Getting Help
============

If issues persist after troubleshooting:

1. **Check GitHub Issues:** https://github.com/netresearch/t3x-rte_ckeditor_image/issues
2. **Review Changelog:** Look for performance improvements in CHANGELOG.md
3. **TYPO3 Slack:** Join `#typo3-cms <https://typo3.slack.com/archives/typo3-cms>`__
4. **Stack Overflow:** Tag questions with ``typo3`` and ``performance``

.. important::
   When reporting performance issues, include:

   * TYPO3 version
   * Extension version
   * PHP version and limits
   * Server specifications
   * Database size
   * Number of images
   * Performance measurements
   * Profiling data
