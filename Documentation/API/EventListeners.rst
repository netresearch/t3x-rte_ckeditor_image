.. include:: /Includes.rst.txt

.. Copyright (c) 2025-2026 Netresearch DTT GmbH
   SPDX-License-Identifier: AGPL-3.0-or-later

.. _api-eventlisteners:

======================
Event Listeners API
======================

Complete API reference for PSR-14 event listeners in the rte_ckeditor_image extension.

.. contents:: Table of Contents
   :depth: 3
   :local:

PSR-14 Event System
===================

What is PSR-14?
---------------

PSR-14 is a standardized event dispatcher interface that allows decoupled components to communicate through events:

- **Events:** Objects containing data about what happened
- **Listeners:** Callables that respond to specific events
- **Dispatcher:** Routes events to registered listeners

Why Use Events Over Hooks?
---------------------------

+----------------+------------------------+---------------------------+
| Feature        | PSR-14 Events          | Traditional Hooks         |
+================+========================+===========================+
| Standard       | Yes (PSR standard)     | No (TYPO3-specific)       |
+----------------+------------------------+---------------------------+
| Type Safety    | Strong (typed events)  | Weak (array parameters)   |
+----------------+------------------------+---------------------------+
| Discoverability| IDE autocomplete       | Manual documentation      |
+----------------+------------------------+---------------------------+
| Testing        | Easy (mock events)     | Difficult (DataHandler)   |
+----------------+------------------------+---------------------------+
| Modern         | PHP 7.4+ features      | Legacy patterns           |
+----------------+------------------------+---------------------------+

Event Flow
----------

.. code-block:: text

   Backend Form Rendering
       ↓
   RteCKEditor prepares configuration
       ↓
   AfterPrepareConfigurationForEditorEvent dispatched
       ↓
   Custom listeners can modify configuration
       ↓
   CKEditor loads with final config

.. note::
   The image plugin route URL is configured declaratively via
   ``Configuration/RTE/Plugin.yaml`` (``externalPlugins.typo3image.route``),
   not through a PHP event listener.

RtePreviewRendererRegistrar
============================

.. _api-rtepreviewrendererregistrar:

.. versionadded:: 13.5.0

:Namespace: ``Netresearch\RteCKEditorImage\Listener\TCA``
:Purpose: Automatically registers ``RteImagePreviewRenderer`` for all CTypes with RTE-enabled bodytext fields
:Event: ``TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent``

Without this listener, CTypes like ``textmedia`` and ``textpic`` use TYPO3's
``StandardContentPreviewRenderer``, which strips ``<img>`` tags via ``strip_tags()``.
This listener ensures images inserted via CKEditor are visible in the page module
preview for all record types.

**Service Configuration:**

.. code-block:: yaml

   Netresearch\RteCKEditorImage\Listener\TCA\RtePreviewRendererRegistrar:
     tags:
       - name: event.listener
         identifier: 'nr-rte-preview-renderer-registrar'
         event: TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent
         after: '*'

**Processing logic:**

1. Check if ``enableAutomaticPreviewRenderer`` is enabled (default: ``true``)
2. Parse inclusion/exclusion table lists from extension configuration
3. Iterate all TCA tables and types
4. For each type with RTE-enabled ``bodytext`` (via ``columnsOverrides`` or base column):
   - Skip if a custom ``previewRenderer`` is already set
   - Register ``RteImagePreviewRenderer``

**Configuration options:**

The listener respects the same ``excludedTables`` and ``includedTablesOnly`` settings
as ``RteSoftrefEnforcer``. See :ref:`Extension Configuration <integration-configuration-extension-settings>`.

RteSoftrefEnforcer
===================

.. _api-rtesoftrefenforcer:

:Namespace: ``Netresearch\RteCKEditorImage\Listener\TCA``
:Purpose: Automatically adds ``rtehtmlarea_images`` soft reference to all RTE-enabled text fields
:Event: ``TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent``

Without soft references, images inserted via CKEditor are not tracked in TYPO3's
reference index. This means images could be lost when records are moved, copied,
or deleted. The listener scans all TCA tables, removes the obsolete ``images``
softref, and adds the ``rtehtmlarea_images`` softref parser key to any
``type=text`` column with ``enableRichtext`` enabled.

**Service Configuration:**

.. code-block:: yaml

   Netresearch\RteCKEditorImage\Listener\TCA\RteSoftrefEnforcer:
     tags:
       - name: event.listener
         identifier: 'nr-rte-softref-enforcer'
         event: TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent
         after: '*'

**Configuration options:**

Controlled via :confval:`enableAutomaticRteSoftref <confval-enableautomaticrtesoftref>`,
:confval:`excludedTables <confval-excludedtables>`, and
:confval:`includedTablesOnly <confval-includedtablesonly>`.

UpdateImageReferences
======================

.. _api-updateimagereferences:

:Namespace: ``Netresearch\RteCKEditorImage\Listener\FileOperation``
:Purpose: Updates stale ``src`` attributes when FAL files are moved or renamed
:Events: ``AfterFileMovedEvent``, ``AfterFileRenamedEvent``

When a file is moved or renamed in TYPO3's Filelist module, the ``src`` attribute
stored in RTE HTML content becomes stale. The ``data-htmlarea-file-uid`` attribute
still points to the correct FAL file, but the ``src`` path no longer matches.
This listener detects affected records via the reference index and updates their
``src`` attributes to the file's current public URL.

**Service Configuration:**

.. code-block:: yaml

   Netresearch\RteCKEditorImage\Listener\FileOperation\UpdateImageReferences:
     tags:
       - name: event.listener
         identifier: 'nr-rte-update-image-references-moved'
         event: TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent
         method: handleFileMoved
       - name: event.listener
         identifier: 'nr-rte-update-image-references-renamed'
         event: TYPO3\CMS\Core\Resource\Event\AfterFileRenamedEvent
         method: handleFileRenamed

.. note::
   This listener requires the reference index to be up to date. Run
   ``bin/typo3 referenceindex:update`` if file operations are not detected.

AfterPrepareConfigurationForEditorEvent
========================================

This is a TYPO3 Core event of EXT:rte_ckeditor.
The rte_ckeditor_image extension does not register a listener for it.
Use it in your own extension to add configuration to the CKEditor instance.

Event Properties
----------------

.. code-block:: php

   final class AfterPrepareConfigurationForEditorEvent
   {
       public function __construct(
           private array $configuration,
           private readonly array $data,
       ) {}

       public function getData(): array;
       public function getConfiguration(): array;
       public function setConfiguration(array $configuration): void;
   }

Event Lifecycle
---------------

:Dispatch Point: At the end of ``RichTextElement::prepareConfigurationForEditor()``
:Mutability: Configuration array can be modified by listeners
:Ordering: Use ``before`` and ``after`` in the ``event.listener`` tag
:Multiple Listeners: Supported - each listener receives modified config from previous

Where the Image Plugin Configuration Comes From
================================================

The plugin configuration is not created by an event listener.
``Configuration/RTE/Plugin.yaml`` declares the external plugin:

.. code-block:: yaml

   editor:
     externalPlugins:
       typo3image: { route: "rteckeditorimage_wizard_select_image" }

TYPO3 Core (``RichTextElement::getExtraPlugins()``) builds ``routeUrl`` from
``route`` with the backend ``UriBuilder``.
After ``AfterPrepareConfigurationForEditorEvent`` has been dispatched,
Core merges this plugin configuration into the editor configuration under the
key ``typo3image``.
If a listener has already set values under ``typo3image``, those values are
kept (``array_replace_recursive()``).

The CKEditor plugin reads the configuration like this:

.. code-block:: javascript

   // Resources/Public/JavaScript/Plugins/typo3image.js
   const routeUrl = editor.config.get('typo3image').routeUrl;

Extending Configuration with Custom Listener
=============================================

Create your own listener to add custom configuration:

.. code-block:: php

   // EXT:my_ext/Classes/EventListener/CustomRteConfigListener.php
   namespace MyVendor\MyExt\EventListener;

   use TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent;

   final class CustomRteConfigListener
   {
       public function __invoke(AfterPrepareConfigurationForEditorEvent $event): void
       {
           $configuration = $event->getConfiguration();

           // Add custom configuration
           $configuration['myext'] = [
               'apiEndpoint' => '/api/my-endpoint',
               'options' => ['foo' => 'bar'],
           ];

           $event->setConfiguration($configuration);
       }
   }

Register in ``Configuration/Services.yaml``:

.. code-block:: yaml

   MyVendor\MyExt\EventListener\CustomRteConfigListener:
     tags:
       - name: event.listener
         identifier: 'custom_rte_config_listener'
         event: TYPO3\CMS\RteCKEditor\Form\Element\Event\AfterPrepareConfigurationForEditorEvent

Access in CKEditor plugin:

.. code-block:: javascript

   const myConfig = editor.config.get('myext');
   console.log(myConfig.apiEndpoint);  // '/api/my-endpoint'


Related Documentation
=====================

- :ref:`Controllers API <api-controllers>`
- :ref:`Data Handling API <api-datahandling>`
- :ref:`CKEditor Plugin Development <ckeditor-plugin-development>`
- :ref:`Configuration Guide <integration>`
- :ref:`Architecture Overview <architecture-overview>`
