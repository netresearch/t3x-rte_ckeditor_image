<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Parser;

use TYPO3\CMS\Core\Html\HtmlParser;

/**
 * Service for parsing HTML content and extracting image tag information.
 *
 * Handles:
 * - Splitting HTML by img tags
 * - Extracting attributes from img tags
 * - Parsing dimensions from style or attributes
 * - Normalizing image source URLs
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final readonly class ImageTagParser implements ImageTagParserInterface
{
    public function __construct(
        private HtmlParser $htmlParser,
    ) {}

    /**
     * Split HTML content into segments with img tags at odd indices.
     *
     * @param string $html The HTML content to parse
     *
     * @return string[] Array of segments (img tags at odd indices)
     */
    public function splitByImageTags(string $html): array
    {
        return $this->htmlParser->splitTags('img', $html);
    }

    /**
     * Extract attributes from an img tag string.
     *
     * @param string $imgTag The img tag string
     *
     * @return array<string, string> Attribute name => value pairs
     */
    public function extractAttributes(string $imgTag): array
    {
        [$attributes] = $this->htmlParser->get_tag_attributes($imgTag, true);

        return is_array($attributes) ? $attributes : [];
    }

    /**
     * Get dimension (width or height) from attributes.
     *
     * Prefers value from style attribute over direct attribute.
     *
     * @param array<string, mixed> $attributes The img tag attributes
     * @param string               $dimension  The dimension to get ('width' or 'height')
     *
     * @return int The dimension value in pixels, or 0 if not found
     */
    public function getDimension(array $attributes, string $dimension): int
    {
        $value = $this->extractFromAttributeValueOrStyle($attributes, $dimension);

        return (int) $value;
    }

    /**
     * Normalize image source URL relative to site.
     *
     * Handles site paths (e.g., /~user/) by converting relative to absolute URLs.
     *
     * @param string $src      The image source URL
     * @param string $siteUrl  The full site URL
     * @param string $sitePath The site path component
     *
     * @return string The normalized absolute URL
     */
    public function normalizeImageSrc(string $src, string $siteUrl, string $sitePath): string
    {
        $absoluteUrl = trim($src);

        // Make path absolute if it is relative and we have a site path which is not '/'
        if ($sitePath !== '' && str_starts_with($absoluteUrl, $sitePath)) {
            // If site is in a subpath (e.g., /~user_jim/) this path needs to be removed
            // because it will be added with $siteUrl
            $absoluteUrl = substr($absoluteUrl, strlen($sitePath));
            $absoluteUrl = $siteUrl . $absoluteUrl;
        }

        return $absoluteUrl;
    }

    /**
     * Calculate site path from site URL and request host.
     *
     * @param string $siteUrl     The full site URL
     * @param string $requestHost The request host
     *
     * @return string The site path (empty string if at root)
     */
    public function calculateSitePath(string $siteUrl, string $requestHost): string
    {
        if ($requestHost === '') {
            return '';
        }

        return str_replace($requestHost, '', $siteUrl);
    }

    /**
     * Extract attribute value from direct attribute or style.
     *
     * Style attribute takes precedence over direct attribute.
     *
     * @param array<string, mixed> $attributes     The attributes array
     * @param string               $imageAttribute The attribute to extract
     *
     * @return string|null The attribute value or null if not found
     */
    private function extractFromAttributeValueOrStyle(array $attributes, string $imageAttribute): ?string
    {
        $styleValue = $attributes['style'] ?? '';
        $style      = is_string($styleValue) ? trim($styleValue) : '';

        if ($style !== '') {
            $value = $this->matchStyleAttribute($style, $imageAttribute);
            if ($value !== null) {
                return $value;
            }
        }

        $attrValue = $attributes[$imageAttribute] ?? null;

        return is_string($attrValue) ? $attrValue : null;
    }

    /**
     * Match and extract dimension from style attribute.
     *
     * @param string $styleAttribute The style attribute value
     * @param string $imageAttribute The attribute to match (e.g., 'width', 'height')
     *
     * @return string|null The matched value or null
     */
    private function matchStyleAttribute(string $styleAttribute, string $imageAttribute): ?string
    {
        $regex   = '[[:space:]]*:[[:space:]]*([0-9]*)[[:space:]]*px';
        $matches = [];

        if (preg_match('/' . $imageAttribute . $regex . '/i', $styleAttribute, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
