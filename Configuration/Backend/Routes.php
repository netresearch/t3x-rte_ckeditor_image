<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Netresearch\RteCKEditorImage\Controller\SelectImageController;

/**
 * Definitions of routes.
 */
return [
    'rteckeditorimage_wizard_select_image' => [
        'path'       => '/rte/wizard/selectimage',
        'target'     => SelectImageController::class . '::mainAction',
        'parameters' => [
            'mode' => 'file',
        ],
    ],
];
