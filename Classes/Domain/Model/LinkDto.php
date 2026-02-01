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
     * - If URL already has "?", params are normalized to start with "&"
     * - URL fragments (#...) are preserved at the end
     *
     * @return string Complete URL with params
     */
    public function getUrlWithParams(): string
    {
        if ($this->params === null || $this->params === '') {
            return $this->url;
        }

        $url      = $this->url;
        $fragment = '';

        // Extract fragment if present (params go before fragment)
        $fragmentPos = strpos($url, '#');
        if ($fragmentPos !== false) {
            $fragment = substr($url, $fragmentPos);
            $url      = substr($url, 0, $fragmentPos);
        }

        // Normalize params
        $params = $this->params;

        // If URL already has query string, normalize params to start with &
        if (str_contains($url, '?')) {
            // Convert leading ? to & to avoid malformed URL like ?a=1?b=2
            if (str_starts_with($params, '?')) {
                $params = '&' . substr($params, 1);
            } elseif (!str_starts_with($params, '&')) {
                // Params doesn't start with & or ? - add &
                $params = '&' . $params;
            }
        } else {
            // URL has no query string - replace leading & with ? if present
            if (str_starts_with($params, '&')) {
                $params = '?' . substr($params, 1);
            } elseif (!str_starts_with($params, '?')) {
                // Params doesn't start with & or ? - add ?
                $params = '?' . $params;
            }
        }

        return $url . $params . $fragment;
    }
}
