<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Builder;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Service for building and reconstructing img tags.
 *
 * Handles:
 * - Building img tags from attribute arrays
 * - Updating attributes with processed image data
 * - Converting absolute URLs to relative
 * - Cleaning style attributes
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class ImageTagBuilder
{
    /**
     * Build an img tag from attributes array.
     *
     * Ensures required attributes are present and cleans up style.
     *
     * @param array<string, mixed> $attributes The img tag attributes
     *
     * @return string The constructed img tag
     */
    public function build(array $attributes): string
    {
        // Ensure alt attribute exists (required for accessibility)
        $attributes['alt'] ??= '';

        // Remove width and height from style attribute (use explicit attributes)
        if (isset($attributes['style'])) {
            $attributes['style'] = $this->cleanStyleAttribute((string) $attributes['style']);

            // Remove empty style attribute
            if (trim($attributes['style']) === '') {
                unset($attributes['style']);
            }
        }

        return '<img ' . GeneralUtility::implodeAttributes($attributes, true, true) . ' />';
    }

    /**
     * Update attributes with processed image data.
     *
     * @param array<string, mixed> $attributes The current attributes
     * @param int                  $width      The processed image width
     * @param int                  $height     The processed image height
     * @param string               $src        The processed image source URL
     * @param int|null             $fileUid    Optional file UID to set
     *
     * @return array<string, mixed> The updated attributes
     */
    public function withProcessedImage(
        array $attributes,
        int $width,
        int $height,
        string $src,
        ?int $fileUid = null,
    ): array {
        $attributes['width']  = $width;
        $attributes['height'] = $height;
        $attributes['src']    = $src;

        if ($fileUid !== null) {
            $attributes['data-htmlarea-file-uid'] = $fileUid;
        }

        return $attributes;
    }

    /**
     * Convert absolute URL to relative URL.
     *
     * @param string $src     The source URL
     * @param string $siteUrl The site URL to remove
     *
     * @return string The relative URL
     */
    public function makeRelativeSrc(string $src, string $siteUrl): string
    {
        if ($siteUrl !== '' && str_starts_with($src, $siteUrl)) {
            return substr($src, strlen($siteUrl));
        }

        return $src;
    }

    /**
     * Clean style attribute by removing width and height declarations.
     *
     * @param string $style The style attribute value
     *
     * @return string The cleaned style value
     */
    private function cleanStyleAttribute(string $style): string
    {
        $cleaned = preg_replace(
            '/(?:^|[^-])(\s*(?:width|height)\s*:[^;]*(?:$|;))/si',
            '',
            $style,
        );

        return $cleaned ?? $style;
    }
}
