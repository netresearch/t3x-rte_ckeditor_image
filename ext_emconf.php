<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Extension Manager configuration for rte_ckeditor_image.
 *
 * IMPORTANT: Do NOT add declare(strict_types=1) here!
 * TER (TYPO3 Extension Repository) cannot parse ext_emconf.php with strict_types.
 * See: https://github.com/TYPO3/tailor/issues/...
 */

$EM_CONF[$_EXTKEY] = [
    'title'          => 'CKEditor Rich Text Editor Image Support',
    'description'    => 'Adds FAL image support to CKEditor for TYPO3 - by Netresearch.',
    'category'       => 'be',
    'author'         => 'Sebastian Koschel, Sebastian Mendel, Rico Sonntag',
    'author_email'   => 'sebastian.koschel@netresearch.de, sebastian.mendel@netresearch.de, rico.sonntag@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'stable',
    'version'        => '13.6.1',
    'constraints'    => [
        'depends' => [
            'php'          => '8.2.0-8.9.99',
            'typo3'        => '13.4.0-14.4.99',
            'rte_ckeditor' => '13.4.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests'  => [
            'setup' => '',
        ],
    ],
];
