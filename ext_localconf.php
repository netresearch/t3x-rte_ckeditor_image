<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;

defined('TYPO3') || exit;

call_user_func(static function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
        = RteImagesDbHook::class;

    // Register default RTE preset with image support
    $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['rteWithImages']
        = 'EXT:rte_ckeditor_image/Configuration/RTE/rteWithImages.yaml';
});
