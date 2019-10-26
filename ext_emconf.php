<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'CKEditor Rich Text Editor Image Support',
    'description' => 'Adds FAL image support to CKEditor for TYPO3.',
    'category' => 'be',
    'state' => 'stable',
    'uploadfolder' => 1,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'author' => 'Christian Opitz',
    'author_email' => 'christian.opitz@netresearch.de',
    'version' => '9.0.4',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-9.5.99',
            'rte_ckeditor' => '9.5.0-9.5.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'setup' => '',
        ],
    ],
];
