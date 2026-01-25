.. include:: /Includes.rst.txt

.. _examples-template-overrides:

==================
Template Overrides
==================

.. versionadded:: 13.1.5

   The new Fluid-based rendering architecture allows complete customization
   of image output via template overrides.

Override the default Fluid templates to customize image rendering output
for your site's design requirements.

.. contents:: Table of contents
   :depth: 2
   :local:

Overview
========

The extension provides six Fluid templates for different rendering contexts:

..  code-block:: text
    :caption: Template directory structure

    Resources/Private/Templates/Image/
    ├── Standalone.html      # Basic image without wrapper
    ├── WithCaption.html     # Image with <figure>/<figcaption>
    ├── Link.html            # Image wrapped in <a> tag
    ├── LinkWithCaption.html # Linked image with caption
    ├── Popup.html           # Image with lightbox/popup link
    └── PopupWithCaption.html # Popup image with caption

Template selection
------------------

The :php:`ImageRenderingService` automatically selects the appropriate template:

..  list-table:: Template selection matrix
    :header-rows: 1
    :widths: 50 50

    *   - Condition
        - Template

    *   - No link, no caption
        - :file:`Standalone.html`

    *   - No link, has caption
        - :file:`WithCaption.html`

    *   - Has link, no popup, no caption
        - :file:`Link.html`

    *   - Has link, no popup, has caption
        - :file:`LinkWithCaption.html`

    *   - Has popup, no caption
        - :file:`Popup.html`

    *   - Has popup, has caption
        - :file:`PopupWithCaption.html`

Setting up overrides
====================

Step 1: Create template directory
---------------------------------

In your site package, create the override directory:

..  code-block:: bash
    :caption: Create template override directory

    mkdir -p packages/my_sitepackage/Resources/Private/Templates/Image/

Step 2: Configure TypoScript
----------------------------

Add the template path to your TypoScript setup:

..  code-block:: typoscript
    :caption: EXT:my_sitepackage/Configuration/TypoScript/setup.typoscript

    lib.parseFunc_RTE.tags.img {
        # Add your templates with higher priority (higher number = higher priority)
        settings.templateRootPaths {
            10 = EXT:my_sitepackage/Resources/Private/Templates/
        }
        settings.partialRootPaths {
            10 = EXT:my_sitepackage/Resources/Private/Partials/
        }
        settings.layoutRootPaths {
            10 = EXT:my_sitepackage/Resources/Private/Layouts/
        }
    }

..  note::

    The configuration must be placed within ``lib.parseFunc_RTE.tags.img``
    (not directly in ``lib.parseFunc_RTE``). The same configuration can be
    added to ``tags.a`` and ``tags.figure`` to control the templates used
    for images that are already wrapped in ``<a>`` or ``<figure>`` elements.

Step 3: Create override templates
---------------------------------

Copy and modify only the templates you need to customize.

Available DTO properties
========================

All templates receive the `image` variable containing an :php:`ImageRenderingDto`:

..  code-block:: html
    :caption: Available template variables

    <!-- Core properties -->
    {image.src}                    <!-- Processed image URL -->
    {image.width}                  <!-- Display width in pixels -->
    {image.height}                 <!-- Display height in pixels -->
    {image.alt}                    <!-- Alternative text -->
    {image.title}                  <!-- Title attribute -->
    {image.caption}                <!-- Caption text (XSS-sanitized) -->
    {image.isMagicImage}           <!-- Whether TYPO3 processing applied -->

    <!-- HTML attributes -->
    {image.htmlAttributes.class}   <!-- CSS classes -->
    {image.htmlAttributes.style}   <!-- Inline styles -->
    {image.htmlAttributes.loading} <!-- lazy/eager -->

    <!-- Link properties (when linked) -->
    {image.link.url}               <!-- Link URL -->
    {image.link.target}            <!-- Link target (_blank, etc.) -->
    {image.link.class}             <!-- Link CSS classes -->
    {image.link.isPopup}           <!-- Whether popup/lightbox -->

See :ref:`api-imagerenderingdto` for complete property documentation.

Example overrides
=================

Bootstrap 5 responsive image
----------------------------

Override :file:`Standalone.html` for Bootstrap 5 responsive images:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Templates/Image/Standalone.html

    <img src="{image.src}"
         alt="{image.alt}"
         width="{image.width}"
         height="{image.height}"
         class="img-fluid {image.htmlAttributes.class}"
         {f:if(condition: image.title, then: 'title="{image.title}"')}
         {f:if(condition: image.htmlAttributes.style, then: 'style="{image.htmlAttributes.style}"')}
         loading="lazy"
         decoding="async" />

Figure with custom styling
--------------------------

Override :file:`WithCaption.html` for custom figure styling:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Templates/Image/WithCaption.html

    <figure class="content-image{f:if(condition: image.htmlAttributes.class, then: ' {image.htmlAttributes.class}')}">
        <img src="{image.src}"
             alt="{image.alt}"
             width="{image.width}"
             height="{image.height}"
             class="content-image__img"
             {f:if(condition: image.title, then: 'title="{image.title}"')}
             loading="lazy" />
        <figcaption class="content-image__caption">
            {image.caption}
        </figcaption>
    </figure>

PhotoSwipe lightbox integration
-------------------------------

Override :file:`Popup.html` for PhotoSwipe v5 integration:

..  code-block:: html
    :caption: EXT:my_sitepackage/Resources/Private/Templates/Image/Popup.html

    <a href="{image.link.url}"
       class="pswp-gallery__item {image.link.class}"
       data-pswp-width="{image.width}"
       data-pswp-height="{image.height}"
       {f:if(condition: image.link.target, then: 'target="{image.link.target}"')}>
        <img src="{image.src}"
             alt="{image.alt}"
             width="{image.width}"
             height="{image.height}"
             {f:if(condition: image.title, then: 'title="{image.title}"')}
             {f:if(condition: image.htmlAttributes.class, then: 'class="{image.htmlAttributes.class}"')}
             loading="lazy" />
    </a>

Lazy loading with placeholder
-----------------------------

Override :file:`Standalone.html` for progressive image loading:

..  code-block:: html
    :caption: Lazy loading with SVG placeholder

    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {image.width} {image.height}'%3E%3C/svg%3E"
         data-src="{image.src}"
         alt="{image.alt}"
         width="{image.width}"
         height="{image.height}"
         class="lazyload {image.htmlAttributes.class}"
         {f:if(condition: image.title, then: 'title="{image.title}"')}
         {f:if(condition: image.htmlAttributes.style, then: 'style="{image.htmlAttributes.style}"')} />

Best practices
==============

#. **Only override what you need**: Copy only templates requiring changes.

#. **Preserve accessibility**: Always include `alt` attribute and maintain semantic HTML.

#. **Keep security intact**: The DTO properties are pre-sanitized. Do not apply additional encoding
   that could double-escape content.

#. **Test all contexts**: Verify overrides work with captions, links, and popups.

#. **Use native lazy loading**: Prefer `loading="lazy"` over JavaScript solutions.

Debugging templates
===================

Enable Fluid debugging to inspect available variables:

..  code-block:: html
    :caption: Debug all template variables

    <f:debug>{_all}</f:debug>

Or in TypoScript:

..  code-block:: typoscript
    :caption: Enable debug mode via TypoScript

    lib.parseFunc_RTE.settings.debug = 1

Related documentation
=====================

-  :ref:`api-imagerenderingservice` - Template selection logic.
-  :ref:`api-imagerenderingdto` - Available DTO properties.
-  :ref:`examples-image-styles` - CSS class configuration.
