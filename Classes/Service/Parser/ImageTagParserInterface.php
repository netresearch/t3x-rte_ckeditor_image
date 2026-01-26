<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Parser;

/**
 * Interface for HTML parsing services that extract img tags.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
interface ImageTagParserInterface
{
    /**
     * Split HTML content by img tags.
     *
     * Returns an array where odd indices contain img tags.
     *
     * @param string $html The HTML content
     *
     * @return string[] The split segments
     */
    public function splitByImageTags(string $html): array;

    /**
     * Extract attributes from an img tag.
     *
     * @param string $imgTag The img tag string
     *
     * @return array<string, string> The extracted attributes
     */
    public function extractAttributes(string $imgTag): array;

    /**
     * Calculate the site path from URLs.
     *
     * @param string $siteUrl     The site URL
     * @param string $requestHost The request host
     *
     * @return string The calculated site path
     */
    public function calculateSitePath(string $siteUrl, string $requestHost): string;

    /**
     * Normalize image source URL.
     *
     * @param string $src      The source attribute value
     * @param string $siteUrl  The site URL
     * @param string $sitePath The site path
     *
     * @return string The normalized URL
     */
    public function normalizeImageSrc(string $src, string $siteUrl, string $sitePath): string;

    /**
     * Get dimension from attributes.
     *
     * @param array<string, mixed> $attributes The attributes
     * @param string               $key        The dimension key (width or height)
     *
     * @return int The dimension value
     */
    public function getDimension(array $attributes, string $key): int;
}
