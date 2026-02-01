<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Domain\Model;

/**
 * Encapsulates link/popup configuration for linked images.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
final readonly class LinkDto
{
    /**
     * @param string                   $url      Link URL (validated)
     * @param string|null              $target   Link target (_blank, _self, etc.)
     * @param string|null              $class    CSS class for link element
     * @param string|null              $params   Additional URL parameters (e.g., "&L=1&type=123")
     * @param bool                     $isPopup  Whether this is a popup/lightbox link
     * @param array<string,mixed>|null $jsConfig JavaScript configuration for lightbox/popup
     */
    public function __construct(
        public string $url,
        public ?string $target,
        public ?string $class,
        public ?string $params,
        public bool $isPopup,
        public ?array $jsConfig,
    ) {}

    /**
     * Get URL with params properly appended.
     *
     * Handles the query string separator correctly:
     * - If URL has no "?", params starting with "&" get "?" prefix instead
     * - If URL already has "?", params are appended as-is
     *
     * @return string Complete URL with params
     */
    public function getUrlWithParams(): string
    {
        if ($this->params === null || $this->params === '') {
            return $this->url;
        }

        // If URL already has query string, append params directly
        if (str_contains($this->url, '?')) {
            return $this->url . $this->params;
        }

        // URL has no query string - replace leading & with ? if present
        $params = $this->params;

        if (str_starts_with($params, '&')) {
            $params = '?' . substr($params, 1);
        } elseif (!str_starts_with($params, '?')) {
            // Params doesn't start with & or ? - add ?
            $params = '?' . $params;
        }

        return $this->url . $params;
    }
}
