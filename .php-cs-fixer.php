<?php

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create();
// @TODO 4.0 no need to call this manually
$config->setParallelConfig(ParallelConfigFactory::detect());
$config->getFinder()->in('Classes')->in('Configuration')->in('Tests');

$existingRules = $config->getRules();
$newRule = ['php_unit_test_case_static_method_calls' => ['call_type' => 'this']];
$config->setRules(array_merge($existingRules, $newRule));

return $config;