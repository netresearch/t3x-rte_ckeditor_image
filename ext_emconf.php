<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title'            => 'CKEditor Rich Text Editor Image Support',
    'description'      => 'Adds FAL image support to CKEditor for TYPO3.',
    'category'         => 'be',
    'state'            => 'stable',
    'clearCacheOnLoad' => 0,
    'author'           => 'Sebastian Koschel',
    'author_email'     => 'sebastian.koschel@netresearch.de',
    'version'          => '12.0.7',
    'constraints'      => [
        'depends' => [
            'php'          => '8.1.0-8.9.99',
            'typo3'        => '12.4.0-12.4.99',
            'rte_ckeditor' => '12.4.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests'  => [
            'setup' => '',
        ],
    ],
];
