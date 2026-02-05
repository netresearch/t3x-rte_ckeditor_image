<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller;

use Netresearch\RteCKEditorImage\Domain\Model\ImageRenderingDto;
use Netresearch\RteCKEditorImage\Service\ImageAttributeParser;
use Netresearch\RteCKEditorImage\Service\ImageRenderingService;
use Netresearch\RteCKEditorImage\Service\ImageResolverService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsAllowedCallable;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * TypoScript adapter for image rendering using new service architecture.
 *
 * This adapter bridges TypoScript preUserFunc interface to the modern
 * service-based architecture (ImageResolverService → ImageRenderingService).
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class ImageRenderingAdapter
{
    /**
     * Same as class name.
     *
     * @var string
     */
    public string $prefixId = 'ImageRenderingAdapter';

    /**
     * Path to this script relative to the extension dir.
     *
     * @var string
     */
    public string $scriptRelPath = 'Classes/Controller/ImageRenderingAdapter.php';

    /**
     * The extension key.
     *
     * @var string
     */
    public string $extKey = 'rte_ckeditor_image';

    /**
     * ContentObjectRenderer from TypoScript.
     */
    protected ?ContentObjectRenderer $cObj = null;

    public function __construct(
        private readonly ImageResolverService $resolverService,
        private readonly ImageRenderingService $renderingService,
        private readonly ImageAttributeParser $attributeParser,
    ) {}

    /**
     * Set ContentObjectRenderer from TypoScript.
     *
     * Called by TYPO3 before invoking preUserFunc methods.
     *
     * @param ContentObjectRenderer $cObj Content object renderer
     */
    public function setContentObjectRenderer(ContentObjectRenderer $cObj): void
    {
        $this->cObj = $cObj;
    }

    /**
     * Render image for standalone <img> tags.
     *
     * TypoScript: lib.parseFunc_RTE.tags.img.preUserFunc
     *
     * IMPORTANT: This handler processes standalone <img> tags and must NOT create
     * figure wrappers. Figure wrappers with captions should only be created by
     * renderFigure() which handles the tags.figure configuration.
     *
     * When CKEditor outputs captioned images, it creates:
     * <figure><img data-caption="X"><figcaption>X</figcaption></figure>
     *
     * If this handler created figure wrappers for data-caption images, parseFunc
     * would produce nested figures (bug #546).
     *
     * @param string|null            $content Content input (not used)
     * @param array<string, mixed>   $conf    TypoScript configuration
     * @param ServerRequestInterface $request Current request
     *
     * @return string Rendered HTML (img tag only, no figure wrapper)
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/566
     */
    #[AsAllowedCallable]
    public function renderImageAttributes(?string $content, array $conf, ServerRequestInterface $request): string
    {
        // Get attributes from ContentObjectRenderer (populated by TypoScript tags.img parser)
        $attributes = $this->cObj instanceof ContentObjectRenderer
            ? ($this->cObj->parameters ?? [])
            : [];

        if ($attributes === []) {
            // No attributes - return original content
            return $this->cObj instanceof ContentObjectRenderer
                ? ($this->cObj->getCurrentVal() ?? '')
                : '';
        }

        // CRITICAL FIX for #546 and #566: Skip processing for images with caption.
        // Images with data-caption are part of a <figure> structure and MUST be
        // processed by renderFigure() instead. If we process here, we strip the
        // data-htmlarea-file-uid attribute, preventing renderFigure() from resolving
        // the file later.
        if (isset($attributes['data-caption']) && $attributes['data-caption'] !== '') {
            return $this->cObj instanceof ContentObjectRenderer
                ? ($this->cObj->getCurrentVal() ?? '')
                : '';
        }

        // Resolve image to validated DTO
        $dto = $this->resolverService->resolve($attributes, $conf, $request);

        if (!$dto instanceof ImageRenderingDto) {
            // Resolution failed - return original content
            return $this->cObj instanceof ContentObjectRenderer
                ? ($this->cObj->getCurrentVal() ?? '')
                : '';
        }

        // Render via Fluid templates (passing TypoScript config for template paths)
        return $this->renderingService->render($dto, $request, $conf);
    }

    /**
     * Render images inside <a> tags.
     *
     * TypoScript: lib.parseFunc_RTE.tags.a.preUserFunc
     *
     * IMPORTANT: When tags.a is configured as TEXT with current=1, parseFunc only
     * passes the inner content of the <a> tag, not the wrapper. We must reconstruct
     * the complete <a>...</a> structure using the tag attributes from cObj->parameters.
     *
     * @param string|null            $content Content input (not used)
     * @param array<string, mixed>   $conf    TypoScript configuration
     * @param ServerRequestInterface $request Current request
     *
     * @return string Rendered HTML with processed images wrapped in <a> tag
     */
    #[AsAllowedCallable]
    public function renderImages(?string $content, array $conf, ServerRequestInterface $request): string
    {
        // Get link inner HTML from ContentObjectRenderer
        $linkContent = $this->cObj instanceof ContentObjectRenderer
            ? $this->cObj->getCurrentVal()
            : null;

        if (!is_string($linkContent) || $linkContent === '') {
            return '';
        }

        // Get link attributes from tag parameters (populated by parseFunc for tags.a)
        // Filter to string values only for type safety
        $linkAttributes = [];

        if ($this->cObj instanceof ContentObjectRenderer) {
            foreach ($this->cObj->parameters as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $linkAttributes[$key] = $value;
                }
            }
        }

        // Parse images from link content
        $parsed = $this->attributeParser->parseLinkWithImages($linkContent);

        if ($parsed['images'] === []) {
            // No images found - reconstruct link with original content
            return $this->wrapInLink($linkContent, $linkAttributes);
        }

        // Process each image and build replacement map
        $replacements = [];

        foreach ($parsed['images'] as $imageData) {
            // Extract attributes and original HTML from parsed data
            $imageAttributes = $imageData['attributes'] ?? [];
            $originalHtml    = $imageData['originalHtml'] ?? '';

            // Skip images without file UID (external images)
            $fileUid = (int) ($imageAttributes['data-htmlarea-file-uid'] ?? 0);

            if ($fileUid === 0) {
                continue;
            }

            // CRITICAL: Skip block images (those without "image-inline" class).
            // Block images inside <a> tags within <figure> elements should be
            // processed by renderFigure() instead. Only process truly inline images
            // here to avoid interfering with figure rendering.
            // See: https://github.com/netresearch/t3x-rte_ckeditor_image/issues/580
            $imageClass  = $imageAttributes['class'] ?? '';
            $splitResult = preg_split('/\s+/', $imageClass, -1, PREG_SPLIT_NO_EMPTY);
            $classTokens = is_array($splitResult) ? $splitResult : [];

            if (!in_array('image-inline', $classTokens, true)) {
                // Not an inline image - skip and let renderFigure handle it
                continue;
            }

            // Images inside links should not create figure wrappers or popup links.
            // - data-caption: Would create figure wrapper, but image is inside <a>, not figure
            // - data-htmlarea-zoom/clickenlarge: Image already wrapped in link, no popup needed
            unset(
                $imageAttributes['data-caption'],
                $imageAttributes['data-htmlarea-zoom'],
                $imageAttributes['data-htmlarea-clickenlarge'],
            );

            // Resolve image to DTO (no link attributes - image is already in a link)
            $dto = $this->resolverService->resolve($imageAttributes, $conf, $request);

            if (!$dto instanceof ImageRenderingDto) {
                continue;
            }

            // Render just the <img> tag (no link wrapper)
            $renderedImg = $this->renderingService->render($dto, $request, $conf);

            // Use original HTML from parser for accurate replacement
            if ($originalHtml !== '') {
                $replacements[$originalHtml] = $renderedImg;
            }
        }

        // Apply replacements to link content
        // Use strtr() instead of str_replace() to prevent collision when one
        // image tag is a substring of another - strtr prioritizes longer keys
        $processedContent = $replacements !== []
            ? strtr($linkContent, $replacements)
            : $linkContent;

        // Reconstruct the <a> wrapper with processed content
        return $this->wrapInLink($processedContent, $linkAttributes);
    }

    /**
     * Wrap content in an <a> tag with the given attributes.
     *
     * @param string               $content    Content to wrap
     * @param array<string,string> $attributes Link attributes (href, target, class, etc.)
     *
     * @return string Complete <a>...</a> HTML
     */
    private function wrapInLink(string $content, array $attributes): string
    {
        if ($attributes === [] || !isset($attributes['href'])) {
            // No link attributes or no href - return content as-is
            return $content;
        }

        $attrParts = [];

        foreach ($attributes as $name => $value) {
            // Skip empty values
            if ($value === '') {
                continue;
            }

            // Escape attribute value for HTML safety
            $attrParts[] = sprintf('%s="%s"', htmlspecialchars($name), htmlspecialchars($value));
        }

        if ($attrParts === []) {
            return $content;
        }

        return '<a ' . implode(' ', $attrParts) . '>' . $content . '</a>';
    }

    /**
     * Render link elements containing images (externalBlocks handler).
     *
     * TypoScript: lib.parseFunc_RTE.externalBlocks.a.stdWrap.preUserFunc
     *
     * This handler receives the COMPLETE <a>...</a> HTML including all inner content.
     * Unlike tags.a which only receives inner content after recursive processing,
     * externalBlocks.a with callRecursive=0 preserves the full link structure.
     *
     * This is necessary for links containing mixed text + image content like:
     * <a href="...">Click here <img class="image-inline"...> to visit</a>
     *
     * @param string|null            $content Full <a>...</a> HTML from externalBlocks
     * @param array<string, mixed>   $conf    TypoScript configuration
     * @param ServerRequestInterface $request Current request
     *
     * @return string Processed HTML with images rendered
     */
    #[AsAllowedCallable]
    public function renderLink(?string $content, array $conf, ServerRequestInterface $request): string
    {
        // Get link HTML from either content param or cObj->getCurrentVal()
        $linkHtml = $content;

        if (!is_string($linkHtml) || $linkHtml === '') {
            $linkHtml = $this->cObj instanceof ContentObjectRenderer
                ? $this->cObj->getCurrentVal()
                : null;
        }

        if (!is_string($linkHtml) || $linkHtml === '') {
            return '';
        }

        // Check if this is actually a link element
        if (!str_contains($linkHtml, '<a ') && !str_contains($linkHtml, '<a>')) {
            // Not a link - return original content
            return $linkHtml;
        }

        // Parse the link to extract attributes and inner content
        $parsed         = $this->attributeParser->parseLinkWithImages($linkHtml);
        $linkAttributes = $parsed['link'];
        $images         = $parsed['images'];

        // If no images found, return original HTML unchanged
        if ($images === []) {
            return $linkHtml;
        }

        // Extract inner content from link (everything between <a...> and </a>)
        $innerContent = $this->extractLinkInnerContent($linkHtml);

        if ($innerContent === '') {
            return $linkHtml;
        }

        // Process each image and build replacement map
        $replacements = [];

        foreach ($images as $imageData) {
            $imageAttributes = $imageData['attributes'] ?? [];
            $originalHtml    = $imageData['originalHtml'] ?? '';

            // Skip images without file UID (external images or already processed)
            $fileUid = (int) ($imageAttributes['data-htmlarea-file-uid'] ?? 0);

            if ($fileUid === 0) {
                continue;
            }

            // Skip block images - only process inline images in links
            $imageClass  = $imageAttributes['class'] ?? '';
            $splitResult = preg_split('/\s+/', $imageClass, -1, PREG_SPLIT_NO_EMPTY);
            $classTokens = is_array($splitResult) ? $splitResult : [];

            if (!in_array('image-inline', $classTokens, true)) {
                continue;
            }

            // Remove attributes that shouldn't apply to images in links
            unset(
                $imageAttributes['data-caption'],
                $imageAttributes['data-htmlarea-zoom'],
                $imageAttributes['data-htmlarea-clickenlarge'],
            );

            // Resolve and render the image
            $dto = $this->resolverService->resolve($imageAttributes, $conf, $request);

            if (!$dto instanceof ImageRenderingDto) {
                continue;
            }

            $renderedImg = $this->renderingService->render($dto, $request, $conf);

            if ($originalHtml !== '') {
                $replacements[$originalHtml] = $renderedImg;
            }
        }

        // Apply replacements to inner content
        $processedContent = $replacements !== []
            ? strtr($innerContent, $replacements)
            : $innerContent;

        // Resolve t3:// links (not resolved by normal parseFunc since we use externalBlocks.a)
        if (isset($linkAttributes['href'])) {
            $linkAttributes['href'] = $this->resolveTypo3LinkUrl($linkAttributes['href'], $request);
        }

        // Reconstruct the link with processed content
        return $this->wrapInLink($processedContent, $linkAttributes);
    }

    /**
     * Resolve TYPO3 internal link references (t3://) to actual URLs.
     *
     * Since this extension handles <a> tags via externalBlocks instead of the
     * standard tags.a handler, t3:// links are not automatically resolved
     * by TYPO3's typolink processing. We must resolve them explicitly.
     *
     * @param string                 $url     The URL to resolve
     * @param ServerRequestInterface $request Current request
     *
     * @return string Resolved URL, or original URL if resolution fails
     */
    private function resolveTypo3LinkUrl(string $url, ServerRequestInterface $request): string
    {
        if (!str_starts_with($url, 't3://') || !$this->cObj instanceof ContentObjectRenderer) {
            return $url;
        }

        $this->cObj->setRequest($request);
        $resolved = $this->cObj->typoLink_URL(['parameter' => $url]);

        return $resolved !== '' ? $resolved : $url;
    }

    /**
     * Extract inner content from a link HTML string.
     *
     * @param string $linkHtml Full <a>...</a> HTML
     *
     * @return string Inner content (everything between opening and closing tags)
     */
    private function extractLinkInnerContent(string $linkHtml): string
    {
        // Use regex to extract content between <a...> and </a>
        if (preg_match('/<a[^>]*>(.*)<\/a>/is', $linkHtml, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Render figure-wrapped images with caption support.
     *
     * TypoScript: lib.parseFunc_RTE.externalBlocks.figure.stdWrap.preUserFunc
     *
     * Handles CKEditor 5 output format: <figure><img/><figcaption>...</figcaption></figure>
     * And linked images: <figure><a href="..."><img/></a><figcaption>...</figcaption></figure>
     * Extracts caption from figcaption element and link attributes from anchor wrapper.
     *
     * @param string|null            $content Content input (not used)
     * @param array<string, mixed>   $conf    TypoScript configuration
     * @param ServerRequestInterface $request Current request
     *
     * @return string Rendered HTML
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    #[AsAllowedCallable]
    public function renderFigure(?string $content, array $conf, ServerRequestInterface $request): string
    {
        // Get figure HTML from either:
        // 1. First parameter $content (externalBlocks.stdWrap.preUserFunc context)
        // 2. ContentObjectRenderer->getCurrentVal() (tags.figure context)
        $figureHtml = $content;

        if (!is_string($figureHtml) || $figureHtml === '') {
            $figureHtml = $this->cObj instanceof ContentObjectRenderer
                ? $this->cObj->getCurrentVal()
                : null;
        }

        if (!is_string($figureHtml) || $figureHtml === '') {
            return '';
        }

        // Check if this is actually a figure with an image
        if (!$this->attributeParser->hasFigureWrapper($figureHtml)) {
            // Not a figure-wrapped image, return original content
            return $figureHtml;
        }

        // Parse figure to extract image attributes, caption, link attributes, and figure class
        $parsed          = $this->attributeParser->parseFigureWithCaption($figureHtml);
        $imageAttributes = $parsed['attributes'];
        $caption         = $parsed['caption'];
        $linkAttributes  = $parsed['link'];
        $figureClass     = $parsed['figureClass'] ?? '';

        // Skip images without file UID (external images)
        $fileUid = (int) ($imageAttributes['data-htmlarea-file-uid'] ?? 0);

        if ($fileUid === 0) {
            // No file UID - return original content
            return $figureHtml;
        }

        // Add caption from figcaption to attributes, overriding any existing data-caption
        // Figcaption takes precedence as it's the visible element in the editor
        // This also ensures backward compatibility with content that has figcaption but no data-caption
        if ($caption !== '') {
            $imageAttributes['data-caption'] = $caption;
        }

        // Resolve t3:// links before passing to service
        if ($linkAttributes !== [] && isset($linkAttributes['href'])) {
            $linkAttributes['href'] = $this->resolveTypo3LinkUrl($linkAttributes['href'], $request);
        }

        // Pass link attributes to resolver if image is wrapped in <a>
        // This ensures the correct template (LinkWithCaption) is selected
        $linkAttributesOrNull = $linkAttributes !== [] ? $linkAttributes : null;
        $figureClassOrNull    = $figureClass !== '' ? $figureClass : null;

        // Resolve image to validated DTO
        $dto = $this->resolverService->resolve($imageAttributes, $conf, $request, $linkAttributesOrNull, $figureClassOrNull);

        if (!$dto instanceof ImageRenderingDto) {
            // Resolution failed - return original content
            return $figureHtml;
        }

        // Render via Fluid templates (will use LinkWithCaption.html if link and caption present)
        return $this->renderingService->render($dto, $request, $conf);
    }
}
