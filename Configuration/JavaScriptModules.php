<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'dependencies' => [
        'rte_ckeditor',
    ],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@netresearch/rte-ckeditor-image/' => 'EXT:rte_ckeditor_image/Resources/Public/JavaScript/',
    ],
];
