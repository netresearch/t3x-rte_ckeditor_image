<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Builder;

/**
 * Interface for building img tag HTML strings.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
interface ImageTagBuilderInterface
{
    /**
     * Build an img tag from attributes.
     *
     * @param array<string, mixed> $attributes The tag attributes
     *
     * @return string The built img tag
     */
    public function build(array $attributes): string;

    /**
     * Make src attribute relative to site URL.
     *
     * @param string $src     The source URL
     * @param string $siteUrl The site URL
     *
     * @return string The relative src
     */
    public function makeRelativeSrc(string $src, string $siteUrl): string;

    /**
     * Update attributes with processed image data.
     *
     * @param array<string, mixed> $attributes The current attributes
     * @param int                  $width      The processed width
     * @param int                  $height     The processed height
     * @param string               $src        The processed src
     * @param int|null             $fileUid    Optional file UID
     *
     * @return array<string, mixed> The updated attributes
     */
    public function withProcessedImage(
        array $attributes,
        int $width,
        int $height,
        string $src,
        ?int $fileUid = null,
    ): array;
}
