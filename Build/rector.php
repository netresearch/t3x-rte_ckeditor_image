<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodParameterRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/../Classes',
        __DIR__ . '/../Configuration',
        __DIR__ . '/../Resources',
        __DIR__ . '/../ext_*.php',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/../ext_emconf.php',
        __DIR__ . '/../ext_*.sql',
    ]);

    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
    $rectorConfig->phpVersion(80200);
    $rectorConfig->importNames();
    $rectorConfig->removeUnusedImports();

    // Define what rule sets will be applied
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        SetList::PRIVATIZATION,
        SetList::STRICT_BOOLEANS,
        SetList::TYPE_DECLARATION,

        // PHP 8.4 support for latest language features
        LevelSetList::UP_TO_PHP_84,

        // Use UP_TO_TYPO3_13 for dual v13/v14 support
        // UP_TO_TYPO3_14 would migrate to v14-only APIs breaking v13 compatibility
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ]);

    // Skip some rules
    $rectorConfig->skip([
        CatchExceptionNameMatchingTypeRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessVarTagRector::class,
        RemoveUnusedPrivateMethodParameterRector::class,
    ]);
};
