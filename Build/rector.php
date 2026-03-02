<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

$configure = require __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/rector/rector.php';

return static function (RectorConfig $rectorConfig) use ($configure): void {
    // Apply shared base config (code-quality sets + common skips, additive)
    $configure($rectorConfig);

    // Extension-specific paths
    $rectorConfig->paths([
        __DIR__ . '/../Classes',
        __DIR__ . '/../Configuration',
        __DIR__ . '/../Resources',
        __DIR__ . '/../ext_*.php',
    ]);

    // Extension-specific skips (merged with shared skips)
    $rectorConfig->skip([
        __DIR__ . '/../ext_emconf.php',
        __DIR__ . '/../ext_*.sql',
    ]);

    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
    $rectorConfig->phpVersion(80200);

    // Extension-specific sets (merged with shared code-quality sets)
    $rectorConfig->sets([
        // STRICT_BOOLEANS not in shared config — add here if wanted
        SetList::STRICT_BOOLEANS,

        // PHP 8.4 support for latest language features
        LevelSetList::UP_TO_PHP_84,

        // Use UP_TO_TYPO3_13 for dual v13/v14 support
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ]);
};
