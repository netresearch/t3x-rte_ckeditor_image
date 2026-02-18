<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

defined('TYPO3') || exit;

/**
 * TCA override for tt_content table.
 */
call_user_func(
    static function (): void {
        /** @var string[] $cleanSoftReferences */
        $cleanSoftReferences = explode(
            ',',
            (string) $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config']['softref'],
        );

        // Remove obsolete soft reference key 'images', the references from RTE content to the original
        // images are handled with the key 'rtehtmlarea_images'
        $cleanSoftReferences   = array_diff($cleanSoftReferences, ['images']);
        $cleanSoftReferences[] = 'rtehtmlarea_images';

        // Set up soft reference index parsing for RTE images
        $GLOBALS['TCA']['tt_content']['columns']['bodytext']['config']['softref'] = implode(
            ',',
            $cleanSoftReferences,
        );

        // Preview renderer is now registered dynamically via RtePreviewRendererRegistrar
        // for ALL CTypes with RTE-enabled bodytext (not just CType "text").
        // See: Classes/Listener/TCA/RtePreviewRendererRegistrar.php
    },
);
