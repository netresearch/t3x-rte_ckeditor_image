<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'CKEditor Rich Text Editor Image Support',
    'description' => 'Adds FAL image support to CKEditor for TYPO3.',
    'category' => 'be',
    'state' => 'stable',
    'clearCacheOnLoad' => 0,
    'author' => 'Sebastian Koschel',
    'author_email' => 'sebastian.koschel@netresearch.de',
    'version' => '12.0.1',
    'constraints' => [
        'depends' => [
            'php' => '8.1.0-8.9.99',
            'typo3' => '12.4.0-12.4.99',
            'cms_rte_ckeditor' => '12.4.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'setup' => '',
        ],
    ],
];
