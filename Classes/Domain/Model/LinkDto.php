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
     * @param bool                     $isPopup  Whether this is a popup/lightbox link
     * @param array<string,mixed>|null $jsConfig JavaScript configuration for lightbox/popup
     */
    public function __construct(
        public string $url,
        public ?string $target,
        public ?string $class,
        public bool $isPopup,
        public ?array $jsConfig,
    ) {}
}
