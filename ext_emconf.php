<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CKEditor Rich Text Editor Image Support',
    'description' => 'Adds FAL image support to CKEditor for TYPO3.',
    'category' => 'be',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'author' => 'Sebastian Koschel',
    'author_email' => 'sebastian.koschel@netresearch.de',
    'author_company' => 'Netresearch DTT GbmH',
    'version' => '12.0.2',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.9.99',
            'typo3' => '12.4.0-12.4.99',
            'rte_ckeditor' => '12.4.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'setup' => '',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Netresearch\\RteCKEditorImage\\' => 'Classes/',
        ],
    ],
    'autoload-dev' => [
        'psr-4' => [
            'Netresearch\\RteCKEditorImage\\Tests\\' => 'Tests/',
        ],
    ],
];
