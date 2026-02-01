<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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
