<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

defined('TYPO3') or die();

/**
 * TCA override for sys_template table.
 */
call_user_func(
    static function () {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
            'rte_ckeditor_image',
            'Configuration/TypoScript/ImageRendering',
            'CKEditor Image Support'
        );
    }
);
