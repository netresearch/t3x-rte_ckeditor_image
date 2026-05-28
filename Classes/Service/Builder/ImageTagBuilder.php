<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
final class ImageTagBuilder implements ImageTagBuilderInterface
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
            $rawStyle            = $attributes['style'];
            $attributes['style'] = $this->cleanStyleAttribute(is_string($rawStyle) ? $rawStyle : '');

            // Remove empty style attribute
            if (trim($attributes['style']) === '') {
                unset($attributes['style']);
            }
        }

        // Ensure all attribute values are int or string for GeneralUtility::implodeAttributes
        /** @var array<string, int|string> $stringAttributes */
        $stringAttributes = [];

        foreach ($attributes as $key => $value) {
            if (is_int($value)) {
                $stringAttributes[$key] = $value;
            } elseif (is_string($value)) {
                $stringAttributes[$key] = $value;
            } elseif (is_scalar($value)) {
                $stringAttributes[$key] = (string) $value;
            } else {
                $stringAttributes[$key] = '';
            }
        }

        return '<img ' . GeneralUtility::implodeAttributes($stringAttributes, true, true) . ' />';
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
     * Convert an absolute site-internal URL to a site-root-relative one.
     *
     * The result is always leading-slash form ("/fileadmin/image.jpg"), never
     * a slashless ("fileadmin/image.jpg") relative URL — the slashless form
     * resolves against the current page path in the browser and is broken in
     * rendered HTML (TYPO3 v12+ does not emit <base href>). Subpath installs
     * (e.g. /~user/) keep the leading-slash form here and rely on
     * config.absRefPrefix to prepend the subpath at render time, so storage
     * stays canonical across root and subpath installs (#778, #837).
     *
     * @param string $src     The source URL
     * @param string $siteUrl The site URL to remove
     *
     * @return string The site-root-relative URL with leading slash, or the
     *                original src when it is not a same-site absolute URL
     */
    public function makeRelativeSrc(string $src, string $siteUrl): string
    {
        if ($siteUrl !== '' && str_starts_with($src, $siteUrl)) {
            return '/' . ltrim(substr($src, strlen($siteUrl)), '/');
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
