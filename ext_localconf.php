<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;

defined('TYPO3') || exit;

call_user_func(static function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
        = RteImagesDbHook::class;

    // Register default RTE preset with image support
    $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['rteWithImages']
        = 'EXT:rte_ckeditor_image/Configuration/RTE/Default.yaml';
});
