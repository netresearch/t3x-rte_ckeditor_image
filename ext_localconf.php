<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || exit;

call_user_func(static function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
        = RteImagesDbHook::class;

    // Register default RTE preset with image support
    $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['rteWithImages']
        = 'EXT:rte_ckeditor_image/Configuration/RTE/rteWithImages.yaml';

    // Load TypoScript via addTypoScript for all TYPO3 versions
    // This ensures the TypoScript is included in sys_template compilation
    // Note: Site sets provide additional configuration but sys_template records
    // are still the primary TypoScript source in TYPO3 v13
    ExtensionManagementUtility::addTypoScript(
        'rte_ckeditor_image',
        'setup',
        '@import "EXT:rte_ckeditor_image/Configuration/TypoScript/ImageRendering/setup.typoscript"',
    );
});
