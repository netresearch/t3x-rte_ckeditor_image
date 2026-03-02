<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Dist config for PHP-CS-Fixer (repo-wide).
 * Copy to .php-cs-fixer.php locally if you want to override anything.
 */

if (PHP_SAPI !== 'cli') {
    die('Run this only from the command line.');
}

$sharedRules = require __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/php-cs-fixer/rules.php';

$header = <<<EOF
Copyright (c) 2025-2026 Netresearch DTT GmbH
SPDX-License-Identifier: AGPL-3.0-or-later
EOF;

$repoRoot = __DIR__ . '/..';

$finder = PhpCsFixer\Finder::create()
    ->in($repoRoot)
    ->exclude(['.Build', 'config', 'node_modules', 'var'])
    // ext_emconf.php must NOT have strict_types - TER cannot parse it
    ->notPath('ext_emconf.php');

$config = new PhpCsFixer\Config();
$config
    ->setRiskyAllowed(true)
    ->setRules(array_merge($sharedRules, [
        'header_comment' => [
            'header'       => $header,
            'comment_type' => 'comment',
            'location'     => 'after_open',
            'separate'     => 'both',
        ],
    ]))
    ->setFinder($finder);

// Allow running on PHP 8.3/8.4 even though composer.json minimum is PHP 8.2
if (method_exists($config, 'setUnsupportedPhpVersionAllowed')) {
    $config->setUnsupportedPhpVersionAllowed(true);
}

return $config;
