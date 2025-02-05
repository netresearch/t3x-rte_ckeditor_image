<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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
