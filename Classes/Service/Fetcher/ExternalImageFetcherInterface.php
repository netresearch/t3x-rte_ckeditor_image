<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Fetcher;

/**
 * Interface for fetching external images with security validation.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
interface ExternalImageFetcherInterface
{
    /**
     * Fetch an external image with security validation.
     *
     * @param string $url The external image URL
     *
     * @return string|null The image content or null on failure
     */
    public function fetch(string $url): ?string;

    /**
     * Check if a URL is an external URL.
     *
     * @param string $url The URL to check
     *
     * @return bool True if the URL is external
     */
    public function isExternalUrl(string $url): bool;
}
