.. _api-services:

============
Services API
============

.. versionadded:: 13.1.5

   The new service architecture replaces legacy controllers with a clean pipeline:
   Parser → Resolver → Renderer.

The RTE CKEditor Image extension uses a three-service architecture following TYPO3 v13
best practices with clear separation of concerns.

.. contents:: Table of contents
   :depth: 3
   :local:

Architecture overview
=====================

..  uml::
    :caption: Service pipeline architecture

    skinparam componentStyle rectangle

    component "ImageAttributeParser" as Parser {
        note right: HTML Parsing\nDOMDocument\nAttribute extraction
    }

    component "ImageResolverService" as Resolver {
        note right: Business Logic\nSecurity Validation\nFAL Processing
    }

    component "ImageRenderingService" as Renderer {
        note right: Fluid Rendering\nViewFactoryInterface\nTemplate Selection
    }

    Parser --> Resolver : Raw attributes
    Resolver --> Renderer : ImageRenderingDto
    Renderer --> [Rendered HTML]

ImageAttributeParser
====================

.. _api-imageattributeparser:

..  php:class:: Netresearch\RteCKEditorImage\Service\ImageAttributeParser

    Pure HTML parsing using :php:`DOMDocument` - no business logic.

Responsibility
--------------

-  Extract raw attributes from HTML strings.
-  Parse :html:`<img>` tags within content.
-  Parse :html:`<a>` tags containing :html:`<img>` tags.
-  **NO** validation, **NO** sanitization - just parsing.

Methods
-------

parseImageAttributes()
~~~~~~~~~~~~~~~~~~~~~~

..  php:method:: parseImageAttributes($html)

    Parse attributes from :html:`<img>` tag HTML string.

    :param string $html: HTML string containing :html:`<img>` tag.
    :returns: Attribute name => value pairs.
    :returntype: array<string,string>

**Example:**

..  code-block:: php
    :caption: Parsing image attributes

    $parser = GeneralUtility::makeInstance(ImageAttributeParser::class);
    $attributes = $parser->parseImageAttributes(
        '<img src="image.jpg" data-htmlarea-file-uid="123" alt="Example" />'
    );
    // Returns: ['src' => 'image.jpg', 'data-htmlarea-file-uid' => '123', 'alt' => 'Example']

parseLinkWithImages()
~~~~~~~~~~~~~~~~~~~~~

..  php:method:: parseLinkWithImages($html)

    Parse attributes from :html:`<a>` tag containing :html:`<img>` tags.

    :param string $html: HTML string containing :html:`<a><img /></a>`.
    :returns: Array with `link` and `images` keys.
    :returntype: array{link: array<string,string>, images: array}

**Return structure:**

..  code-block:: php
    :caption: Return value structure

    [
        'link' => ['href' => 'page.html', 'title' => 'Link title'],
        'images' => [
            [
                'attributes' => ['src' => 'image.jpg', 'alt' => 'Alt text'],
                'originalHtml' => '<img src="image.jpg" alt="Alt text" />'
            ]
        ]
    ]

ImageResolverService
====================

.. _api-imageresolverservice:

..  php:class:: Netresearch\RteCKEditorImage\Service\ImageResolverService

    Business logic, security validation, and FAL processing.

Responsibility
--------------

-  Transform raw attributes into validated DTOs.
-  Resolve FAL file references.
-  Apply security checks (file visibility, protocol blocking).
-  Process images with quality settings.
-  **ALL** security validation happens here.

Security features
-----------------

.. versionadded:: 13.1.5

The service includes comprehensive security measures:

-  **File visibility validation**: Prevents access to hidden/restricted files.
-  **Protocol blocking**: Blocks dangerous protocols (`javascript:`, `file:`, `data:text/html`, `vbscript:`).
-  **XSS prevention**: Uses :php:`htmlspecialchars()` with :php:`ENT_QUOTES | ENT_HTML5`.
-  **Type safety**: Read-only DTO properties prevent modification.

Quality settings
----------------

.. versionadded:: 13.1.5

The service supports quality multipliers for image processing:

..  code-block:: php
    :caption: Quality constants in ImageResolverService

    const QUALITY_NONE     = 'none';     // 1.0x - No scaling
    const QUALITY_LOW      = 'low';      // 0.9x - Performance optimized
    const QUALITY_STANDARD = 'standard'; // 1.0x - Default
    const QUALITY_RETINA   = 'retina';   // 2.0x - High-DPI displays
    const QUALITY_ULTRA    = 'ultra';    // 3.0x - Extra sharp
    const QUALITY_PRINT    = 'print';    // 6.0x - Print quality

Methods
-------

resolve()
~~~~~~~~~

..  php:method:: resolve($attributes, $conf, $request, $linkAttributes = null)

    Resolve image attributes to validated DTO.

    :param array $attributes: Raw attributes from parser.
    :param array $conf: TypoScript configuration.
    :param ServerRequestInterface $request: Current request.
    :param array|null $linkAttributes: Optional link attributes for linked images.
    :returns: Validated DTO or null if validation fails.
    :returntype: ImageRenderingDto|null

**Example:**

..  code-block:: php
    :caption: Resolving image attributes to DTO

    $resolver = GeneralUtility::makeInstance(ImageResolverService::class);
    $dto = $resolver->resolve(
        $attributes,
        $typoScriptConfig,
        $request
    );

    if ($dto === null) {
        // Validation failed - return original content
        return $content;
    }

ImageRenderingService
=====================

.. _api-imagerenderingservice:

..  php:class:: Netresearch\RteCKEditorImage\Service\ImageRenderingService

    Presentation layer using TYPO3 v13 :php:`ViewFactoryInterface`.

Responsibility
--------------

-  Render validated DTOs via Fluid templates.
-  Select appropriate template based on context.
-  **NO** business logic, **NO** validation - trusts the DTO.

Template selection
------------------

The service automatically selects templates based on the rendering context:

..  list-table:: Template selection matrix
    :header-rows: 1
    :widths: 50 50

    *   - Context
        - Template

    *   - Standalone image
        - :file:`Image/Standalone`

    *   - Image with caption
        - :file:`Image/WithCaption`

    *   - Image within link
        - :file:`Image/Link`

    *   - Linked image with caption
        - :file:`Image/LinkWithCaption`

    *   - Image with zoom/popup
        - :file:`Image/Popup`

    *   - Popup image with caption
        - :file:`Image/PopupWithCaption`

Methods
-------

render()
~~~~~~~~

..  php:method:: render(ImageRenderingDto $imageData, ServerRequestInterface $request): string

    Render image HTML from validated DTO.

    :param ImageRenderingDto $imageData: Validated image data.
    :param ServerRequestInterface $request: Current request.
    :returns: Rendered HTML.
    :returntype: string

**Example:**

..  code-block:: php
    :caption: Rendering image from DTO

    $renderer = GeneralUtility::makeInstance(ImageRenderingService::class);
    $html = $renderer->render($dto, $request);

Service decoration
==================

To customize service behavior, use Symfony service decoration:

..  code-block:: yaml
    :caption: EXT:my_extension/Configuration/Services.yaml

    App\Service\CustomImageResolver:
      decorates: Netresearch\RteCKEditorImage\Service\ImageResolverService
      arguments:
        $inner: '@.inner'

..  code-block:: php
    :caption: EXT:my_extension/Classes/Service/CustomImageResolver.php

    <?php

    declare(strict_types=1);

    namespace App\Service;

    use Netresearch\RteCKEditorImage\Service\ImageResolverService;
    use Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto;

    class CustomImageResolver
    {
        public function __construct(
            private readonly ImageResolverService $inner
        ) {}

        public function resolve(...$args): ?ImageRenderingDto
        {
            // Custom pre-processing
            $dto = $this->inner->resolve(...$args);
            // Custom post-processing
            return $dto;
        }
    }

Related documentation
=====================

-  :ref:`DTOs <api-dtos>` - Data transfer objects.
-  :ref:`Controllers API <api-controllers>` - TypoScript adapter.
-  :ref:`Template Overrides <examples-template-overrides>` - Customizing output.
