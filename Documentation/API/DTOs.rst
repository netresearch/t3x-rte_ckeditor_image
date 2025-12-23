.. include:: /Includes.rst.txt

.. _api-dtos:

======================
Data Transfer Objects
======================

.. versionadded:: 13.1.5

   DTOs provide type-safe data contracts between services.

Data Transfer Objects (DTOs) encapsulate validated, sanitized data for image rendering.
They are immutable (:php:`readonly`) to ensure data integrity throughout the rendering pipeline.

.. contents:: Table of contents
   :depth: 2
   :local:

ImageRenderingDto
=================

.. _api-imagerenderingdto:

..  php:class:: Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto

    Type-safe container for all image rendering data.

.. important::

   All security validation MUST occur in :php:`ImageResolverService` before DTO construction.
   The DTO represents validated, sanitized data ready for presentation.

Properties
----------

..  code-block:: php
    :caption: ImageRenderingDto class definition

    final readonly class ImageRenderingDto
    {
        public function __construct(
            public string $src,              // Image source URL (validated)
            public int $width,               // Display width in pixels
            public int $height,              // Display height in pixels
            public ?string $alt,             // Alternative text for accessibility
            public ?string $title,           // Title attribute for hover tooltip
            public array $htmlAttributes,    // Additional HTML attributes
            public ?string $caption,         // Caption text (XSS-sanitized)
            public ?LinkDto $link,           // Link/popup configuration
            public bool $isMagicImage,       // Whether TYPO3 processing enabled
        ) {}
    }

Property details
----------------

..  confval:: src
    :name: dto-src
    :type: string
    :required: true

    The processed image URL. Always validated and safe for output.

..  confval:: width
    :name: dto-width
    :type: int
    :required: true

    Display width in pixels. Used for proper aspect ratio and layout.

..  confval:: height
    :name: dto-height
    :type: int
    :required: true

    Display height in pixels. Used for proper aspect ratio and layout.

..  confval:: alt
    :name: dto-alt
    :type: ?string
    :required: false

    Alternative text for accessibility (screen readers, broken images).

..  confval:: title
    :name: dto-title
    :type: ?string
    :required: false

    Title attribute shown as tooltip on hover.

..  confval:: htmlAttributes
    :name: dto-htmlattributes
    :type: array<string,mixed>
    :required: true

    Additional HTML attributes such as:

    -  `class`: CSS classes.
    -  `style`: Inline styles.
    -  `loading`: Lazy loading setting (`lazy`, `eager`).
    -  `data-*`: Custom data attributes.

..  confval:: caption
    :name: dto-caption
    :type: ?string
    :required: false

    Caption text for :html:`<figcaption>`. Already sanitized with :php:`htmlspecialchars()`.

..  confval:: link
    :name: dto-link
    :type: ?LinkDto
    :required: false

    Link or popup configuration. See :ref:`api-linkdto`.

..  confval:: isMagicImage
    :name: dto-ismagicimage
    :type: bool
    :required: true

    Indicates whether TYPO3 image processing (magic images) was applied.

LinkDto
=======

.. _api-linkdto:

..  php:class:: Netresearch\RteCKEditorImage\Domain\Model\LinkDto

    Encapsulates link/popup configuration for linked images.

Properties
----------

..  code-block:: php
    :caption: LinkDto class definition

    final readonly class LinkDto
    {
        public function __construct(
            public string $url,        // Link URL (validated)
            public ?string $target,    // Link target (_blank, _self, etc.)
            public ?string $class,     // CSS class for link element
            public bool $isPopup,      // Whether this is a popup/lightbox link
            public ?array $jsConfig,   // JavaScript configuration for lightbox
        ) {}
    }

Property details
----------------

..  confval:: url
    :name: linkdto-url
    :type: string
    :required: true

    The link target URL. Validated against dangerous protocols.

..  confval:: target
    :name: linkdto-target
    :type: ?string
    :required: false

    Link target attribute (`_blank`, `_self`, `_parent`, `_top`).

..  confval:: class
    :name: linkdto-class
    :type: ?string
    :required: false

    CSS classes applied to the :html:`<a>` element.

..  confval:: isPopup
    :name: linkdto-ispopup
    :type: bool
    :required: true

    Whether the link should open in a popup/lightbox instead of navigating.

..  confval:: jsConfig
    :name: linkdto-jsconfig
    :type: ?array<string,mixed>
    :required: false

    JavaScript configuration for lightbox/popup behavior:

    ..  code-block:: php
        :caption: Example jsConfig structure

        [
            'width' => 800,
            'height' => 600,
            'effect' => 'fade'
        ]

Usage example
=============

..  code-block:: php
    :caption: Creating DTOs for image rendering

    use Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto;
    use Netresearch\RteCKEditorImage\Domain\Model\LinkDto;

    // Create link DTO for popup
    $link = new LinkDto(
        url: '/fileadmin/images/large.jpg',
        target: null,
        class: 'lightbox',
        isPopup: true,
        jsConfig: ['effect' => 'fade']
    );

    // Create image DTO
    $image = new ImageRenderingDto(
        src: '/fileadmin/_processed_/image_hash.jpg',
        width: 800,
        height: 600,
        alt: 'Example image',
        title: 'Click to enlarge',
        htmlAttributes: ['class' => 'img-responsive', 'loading' => 'lazy'],
        caption: 'Photo by Photographer',
        link: $link,
        isMagicImage: true
    );

    // DTOs are immutable - properties cannot be changed
    // $image->width = 1000; // Error: Cannot modify readonly property

Related documentation
=====================

-  :ref:`Services API <api-services>` - Service architecture using DTOs.
-  :ref:`Template Overrides <examples-template-overrides>` - Accessing DTO in templates.
