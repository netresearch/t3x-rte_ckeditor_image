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
     * @param string|null            $content Content input (not used)
     * @param mixed[]                $conf    TypoScript configuration
     * @param ServerRequestInterface $request Current request
     *
     * @return string Rendered HTML
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

        // Resolve image to validated DTO
        $dto = $this->resolverService->resolve($attributes, $conf, $request);

        if (!$dto instanceof ImageRenderingDto) {
            // Resolution failed - return original content
            return $this->cObj instanceof ContentObjectRenderer
                ? ($this->cObj->getCurrentVal() ?? '')
                : '';
        }

        // Render via Fluid templates
        return $this->renderingService->render($dto, $request);
    }

    /**
     * Render images inside <a> tags.
     *
     * TypoScript: lib.parseFunc_RTE.tags.a.preUserFunc
     *
     * @param string|null            $content Content input (not used)
     * @param mixed[]                $conf    TypoScript configuration
     * @param ServerRequestInterface $request Current request
     *
     * @return string Rendered HTML with processed images
     */
    #[AsAllowedCallable]
    public function renderImages(?string $content, array $conf, ServerRequestInterface $request): string
    {
        // Get link inner HTML from ContentObjectRenderer
        $linkContent = $this->cObj instanceof ContentObjectRenderer
            ? $this->cObj->getCurrentVal()
            : null;

        if ($linkContent === null || $linkContent === '') {
            return '';
        }

        // Parse images from link content
        $parsed = $this->attributeParser->parseLinkWithImages($linkContent);

        if ($parsed['images'] === []) {
            // No images found - return original content
            return $linkContent;
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

            // Disable zoom for images inside links (already wrapped in link)
            unset(
                $imageAttributes['data-htmlarea-zoom'],
                $imageAttributes['data-htmlarea-clickenlarge'],
            );

            // Resolve image to DTO (no link attributes - image is already in a link)
            $dto = $this->resolverService->resolve($imageAttributes, $conf, $request);

            if (!$dto instanceof ImageRenderingDto) {
                continue;
            }

            // Render just the <img> tag (no link wrapper)
            $renderedImg = $this->renderingService->render($dto, $request);

            // Use original HTML from parser for accurate replacement
            if ($originalHtml !== '') {
                $replacements[$originalHtml] = $renderedImg;
            }
        }

        // Apply replacements to link content
        if ($replacements !== []) {
            $linkContent = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $linkContent,
            );
        }

        return is_string($linkContent) ? $linkContent : '';
    }
}
