<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'CKEditor Rich Text Editor Image Support',
    'description' => 'Adds FAL image support to CKEditor for TYPO3.',
    'category' => 'be',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'author' => 'Christian Opitz, Mathias Uhlmann',
    'author_email' => 'christian.opitz@netresearch.de',
    'version' => '11.0.6',
    'constraints' => [
        'depends' => [
            'php' => '7.4.0-8.9.99',
            'typo3' => '11.0.0-11.5.99',
            'rte_ckeditor' => '11.0.0-11.5.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'setup' => '',
        ],
    ],
];
