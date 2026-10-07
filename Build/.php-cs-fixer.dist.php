<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$createConfig = require __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/php-cs-fixer/config.php';

$config = $createConfig(<<<'EOF'
    Copyright (c) 2025-2026 Netresearch DTT GmbH
    SPDX-License-Identifier: AGPL-3.0-or-later
    EOF, __DIR__ . '/..');

// The functional test bootstrap is TYPO3 testing-framework boilerplate under
// GPL-2.0-or-later (see NOTICE); the header rule must not put the AGPL header
// of this extension on it.
$finder = $config->getFinder();
if ($finder instanceof PhpCsFixer\Finder) {
    $finder->notPath('Build/phpunit/FunctionalTestsBootstrap.php');
}

return $config;
