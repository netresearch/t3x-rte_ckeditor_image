<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\RteCKEditorImage\Database\RteImagesDbHook;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][]
    = RteImagesDbHook::class;

ExtensionManagementUtility::addPageTSConfig(
    'RTE.default.proc.overruleMode := addToList(default)

    RTE.default.buttons.image.options.magic {
        maxWidth = 1920
        maxHeight = 9999
    }
    '
);
ExtensionManagementUtility::addPageTSConfig(
    'RTE.default.proc.overruleMode := addToList(rtehtmlarea_images_db)'
);
