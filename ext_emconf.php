<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CKEditor Rich Text Editor Image Support',
    'description' => 'Adds FAL image support to CKEditor for TYPO3.',
    'category' => 'be',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'author' => 'Sebastian Koschel',
    'author_email' => 'sebastian.koschel@netresearch.de',
    'version' => '13.0.0',
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
