<?php

/**
 * This file is part of the package netresearch/nr-sync.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

$EM_CONF['rte_ckeditor_image'] = [
    'title'            => 'CKEditor Rich Text Editor Image Support',
    'description'      => 'Adds FAL image support to CKEditor for TYPO3.',
    'category'         => 'be',
    'author'           => 'Sebastian Koschel, Sebastian Mendel, Rico Sonntag',
    'author_email'     => 'sebastian.koschel@netresearch.de, sebastian.mendel@netresearch.de, rico.sonntag@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state'            => 'stable',
    'version'          => '13.0.0',
    'constraints'      => [
        'depends'   => [
            'php'          => '8.2.0-8.9.99',
            'typo3'        => '13.4.0-13.4.99',
            'rte_ckeditor' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests'  => [
            'setup' => '',
        ],
    ],
];
