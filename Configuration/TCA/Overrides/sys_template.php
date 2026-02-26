<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || exit;

/**
 * TCA override for sys_template table.
 */
call_user_func(
    static function (): void {
        ExtensionManagementUtility::addStaticFile(
            'rte_ckeditor_image',
            'Configuration/TypoScript/ImageRendering',
            'CKEditor Image Support',
        );
    },
);
