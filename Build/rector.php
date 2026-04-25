<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Configuration\Option;
use Rector\Configuration\Parameter\SimpleParameterProvider;
use Rector\Set\ValueObject\LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

$configure = require __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/rector/rector.php';

return static function (RectorConfig $rectorConfig) use ($configure): void {
    // Apply shared base config with standard TYPO3 extension paths
    $configure($rectorConfig, __DIR__ . '/..');

    // Replace (not append to) the shared phpstanConfig list. The shared
    // Build/phpstan.neon inherits typo3-ci-workflows v1.3's
    // `parameters: ergebnis:` block; Rector's bundled phpstan.phar does
    // not have ergebnis/phpstan-rules registered and trips a
    // ValidationException. We can't use $rectorConfig->phpstanConfig()
    // here because it APPENDS to PHPSTAN_FOR_RECTOR_PATHS — the broken
    // shared neon would still be loaded. setParameter() replaces the
    // whole list with just our Rector-only neon (level + paths only).
    SimpleParameterProvider::setParameter(Option::PHPSTAN_FOR_RECTOR_PATHS, [__DIR__ . '/phpstan-rector.neon']);

    // Extension-specific sets (merged with shared code-quality sets)
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ]);
};
