<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CKEditor Rich Text Editor Image Support',
    'description' => 'Adds FAL image support to CKEditor for TYPO3 - by Netresearch.',
    'category' => 'be',
    'state' => 'stable',
    'author' => 'Sebastian Koschel, Sebastian Mendel, Rico Sonntag',
    'author_email' => 'sebastian.koschel@netresearch.de, sebastian.mendel@netresearch.de, rico.sonntag@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'version' => '13.0.1',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.9.99',
            'typo3' => '13.4.0-13.4.99',
            'rte_ckeditor' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'setup' => '',
        ],
    ],
];
