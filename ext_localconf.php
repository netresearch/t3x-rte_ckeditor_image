<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

defined('TYPO3_MODE') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_parsehtml_proc.php']['transformation']['rtehtmlarea_images_db']
    = \Netresearch\RteCKEditorImage\Database\RteImagesDbHook::class;

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
    'RTE.default.proc.overruleMode := addToList(default)'
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
    'RTE.default.proc.overruleMode := addToList(rtehtmlarea_images_db)'
);
