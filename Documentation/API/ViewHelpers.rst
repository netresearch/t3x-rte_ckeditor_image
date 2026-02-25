.. include:: /Includes.rst.txt

.. _api-viewhelpers:

===========
ViewHelpers
===========

Fluid ViewHelpers for rendering RTE image previews in backend templates.

.. contents:: Table of contents
   :depth: 3
   :local:

RteImagePreviewViewHelper
=========================

.. _api-rteimagepreviewviewhelper:

..  php:class:: Netresearch\RteCKEditorImage\ViewHelpers\RteImagePreviewViewHelper

    Renders a backend preview of RTE HTML content by stripping disallowed tags
    and truncating text while preserving HTML structure.

    This ViewHelper replicates the preview logic from
    :php:`RteImagePreviewRenderer` for use in Content Blocks and other custom
    backend preview templates where the built-in renderer is not available.

    .. versionadded:: 13.6.0

Arguments
---------

.. confval:: html
   :type: string
   :required: true

   The RTE HTML content to preview. Typically ``{data.bodytext}`` in a
   Content Blocks backend preview template.

.. confval:: maxLength
   :type: int
   :default: 1500

   Maximum number of text characters before truncation. When exceeded, the
   text is truncated with an ellipsis (``...``).
   HTML tags do not count toward this limit.

.. confval:: allowedTags
   :type: string
   :default: ``<img><p>``

   HTML tags to preserve in the preview output, in :php:`strip_tags()` format.
   All other tags are stripped (their text content is kept).

Processing Pipeline
-------------------

The ViewHelper processes HTML through three stages:

1. **Sanitization** — Removes control characters (``\x00``-``\x1F``),
   UTF-16 surrogates, and Unicode non-characters, replacing them with
   U+FFFD (replacement character).

2. **Tag stripping** — Calls :php:`strip_tags()` with the ``allowedTags``
   argument, keeping only ``<img>`` and ``<p>`` by default.

3. **DOM-aware truncation** — Parses the remaining HTML with
   :php:`DOMDocument`, walks the DOM tree counting text length, and
   truncates at ``maxLength`` while keeping all tags properly closed.

Usage with Content Blocks
-------------------------

Content Blocks is the official TYPO3-endorsed successor to Mask/DCE/Flux for
creating custom content element types. Content Blocks registers its own backend
preview templates (``backend-preview.fluid.html``), which do not use the
built-in :php:`RteImagePreviewRenderer`.

To render RTE image previews in a Content Block, use this ViewHelper in the
block's backend preview template:

.. code-block:: html
   :caption: ContentBlocks/ContentElements/my-block/Templates/backend-preview.fluid.html

   <html xmlns:nr="http://typo3.org/ns/Netresearch/RteCKEditorImage/ViewHelpers"
         data-namespace-typo3-fluid="true">
   <nr:rteImagePreview html="{data.bodytext}" />
   </html>

Custom Tag Allowlist
--------------------

To also preserve ``<figure>`` and ``<figcaption>`` in the preview:

.. code-block:: html

   <nr:rteImagePreview html="{data.bodytext}"
                       allowedTags="<img><p><figure><figcaption>" />

Custom Truncation Length
------------------------

To show a shorter preview (e.g., in a compact list view):

.. code-block:: html

   <nr:rteImagePreview html="{data.bodytext}" maxLength="300" />

Standard Content Elements
-------------------------

For standard ``tt_content`` types with RTE bodytext, you do **not** need this
ViewHelper. The built-in :php:`RteImagePreviewRenderer` handles backend
previews automatically via TYPO3's ``PreviewRendererInterface``.
