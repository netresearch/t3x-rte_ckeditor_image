<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;

defined('TYPO3') || exit;

call_user_func(static function (): void {
    // Register DataHandler hook for RTE image processing.
    // TYPO3 bootstrap guarantees the TYPO3_CONF_VARS structure exists;
    // @phpstan-var annotations satisfy PHPStan level 10 without runtime guards.

    /** @phpstan-var array{SC_OPTIONS: array<string, array<string, list<class-string>>>, RTE: array{Presets: array<string, string>}} $conf */
    $conf = &$GLOBALS['TYPO3_CONF_VARS'];

    $conf['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = RteImagesDbHook::class;
    $conf['RTE']['Presets']['rteWithImages']                                      = 'EXT:rte_ckeditor_image/Configuration/RTE/rteWithImages.yaml';
});
