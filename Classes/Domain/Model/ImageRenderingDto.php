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
 * Type-safe data contract for image rendering.
 *
 * SECURITY: All security validation MUST occur before DTO construction.
 * This DTO represents validated, sanitized data ready for presentation.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
final readonly class ImageRenderingDto
{
    /**
     * @param string              $src            Image source URL (validated)
     * @param int                 $width          Display width in pixels
     * @param int                 $height         Display height in pixels
     * @param string|null         $alt            Alternative text for accessibility
     * @param string|null         $title          Title attribute for hover tooltip
     * @param array<string,mixed> $htmlAttributes Additional HTML attributes (data-*, class, style, loading)
     * @param string|null         $caption        Caption text (already XSS-sanitized with htmlspecialchars)
     * @param LinkDto|null        $link           Link/popup configuration (nullable for linked images)
     * @param bool                $isMagicImage   Whether this is a magic image (TYPO3 processing enabled)
     */
    public function __construct(
        public string $src,
        public int $width,
        public int $height,
        public ?string $alt,
        public ?string $title,
        public array $htmlAttributes,
        public ?string $caption,
        public ?LinkDto $link,
        public bool $isMagicImage,
    ) {}
}
