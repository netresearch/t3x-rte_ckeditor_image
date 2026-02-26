<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Netresearch\RteCKEditorImage\Controller\SelectImageController;

/**
 * Definitions of AJAX routes.
 */
return [
    'rteckeditorimage_link_browser' => [
        'path'   => '/rte/image/linkbrowser',
        'target' => SelectImageController::class . '::linkBrowserAction',
    ],
];
