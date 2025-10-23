<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || exit;

// Load Page TSConfig globally for all sites
ExtensionManagementUtility::addPageTSConfig(
    '@import "EXT:rte_ckeditor_image/Configuration/page.tsconfig"',
);
