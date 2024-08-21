<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\Ternary\SwitchNegatedTernaryRector;
use Rector\Config\RectorConfig;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\CodeQuality\General\ConvertImplicitVariablesToExplicitGlobalsRector;
use Ssch\TYPO3Rector\CodeQuality\General\ExtEmConfRector;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;
use Ssch\Typo3RectorTestingFramework\Set\TYPO3TestingFrameworkSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/Classes/',
        __DIR__ . '/Configuration/',
        __DIR__ . '/Tests/',
        __DIR__ . '/ext_emconf.php',
        __DIR__ . '/ext_localconf.php',
    ])
    ->withPhpVersion(PhpVersion::PHP_81)
    ->withPhpSets(
        true
    )
    // Note: We're only enabling a single set by default to improve performance. (Rector needs at least a single set to
    // run.)
    //
    // You can temporarily enable more sets as needed.
    ->withSets([
        // Rector sets

        LevelSetList::UP_TO_PHP_81,
        // LevelSetList::UP_TO_PHP_82,
        // LevelSetList::UP_TO_PHP_83,

        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        SetList::NAMING,
        SetList::PRIVATIZATION,
        SetList::STRICT_BOOLEANS,
        SetList::TYPE_DECLARATION,

        // PHPUnit sets
        PHPUnitSetList::PHPUNIT_110,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,

        // TYPO3 Sets
        // https://github.com/sabbelasichon/typo3-rector/blob/main/src/Set/Typo3LevelSetList.php
        // https://github.com/sabbelasichon/typo3-rector/blob/main/src/Set/Typo3SetList.php

        Typo3SetList::CODE_QUALITY,
        Typo3SetList::GENERAL,

        Typo3LevelSetList::UP_TO_TYPO3_12,

        //TYPO3TestingFrameworkSetList::TYPO3_TESTING_FRAMEWORK_8,
    ])
    // To have a better analysis from PHPStan, we teach it here some more things
    ->withPHPStanConfigs([
        Typo3Option::PHPSTAN_FOR_RECTOR_PATH,
    ])
    ->withRules([
        AddVoidReturnTypeWhereNoReturnRector::class,
        ConvertImplicitVariablesToExplicitGlobalsRector::class,
    ])
    ->withImportNames(true, true, false)
    ->withConfiguredRule(ExtEmConfRector::class, [
        // ExtEmConfRector::PHP_VERSION_CONSTRAINT => '8.1.0-8.3.99',
        // ExtEmConfRector::TYPO3_VERSION_CONSTRAINT => '12.0.0-13.2.99',
        // ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [],
    ])
    ->withSkip([
    ]);