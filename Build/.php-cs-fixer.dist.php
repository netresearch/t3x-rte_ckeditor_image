<?php

/**
 * Dist config for PHP-CS-Fixer (repo-wide).
 * Copy to .php-cs-fixer.php locally if you want to override anything.
 */

if (PHP_SAPI !== 'cli') {
    die('Run this only from the command line.');
}

$header = <<<EOF
This file is part of the package netresearch/rte-ckeditor-image.

For the full copyright and license information, please read the
LICENSE file that was distributed with this source code.
EOF;

$repoRoot = __DIR__ . '/..';

$finder = PhpCsFixer\Finder::create()
    ->in($repoRoot)
    ->exclude(['.Build', 'config', 'node_modules', 'var'])
    // ext_emconf.php must NOT have strict_types - TER cannot parse it
    ->notPath('ext_emconf.php');

$config = new PhpCsFixer\Config();
$config
    // Enable fixers that might change behavior (you control which via setRules)
    ->setRiskyAllowed(true)
    ->setRules([
        // Base style + modern PHP (8.2 floor so code runs on 8.2–8.4)
        '@Symfony'        => true,
        '@PER-CS3x0'      => true,
        '@PHP8x2Migration' => true,

        // --- Your original customizations (kept / refined) ---
        'declare_strict_types' => true,

        'concat_space' => ['spacing' => 'one'],

        'header_comment' => [
            'header'       => $header,
            'comment_type' => 'comment',   // was "PHPDoc"; safer to keep real PHPDocs intact
            'location'     => 'after_open',
            'separate'     => 'both',
        ],

        // Keep docblock behavior stable; drop legacy noise
        'phpdoc_to_comment'          => false,
        'no_superfluous_phpdoc_tags' => false, // keep if your team wants explicit tags

        // Optional grouping example kept
        'phpdoc_separation' => [
            'groups' => [['author', 'license', 'link']],
        ],

        'no_alias_functions' => true,

        // Your chosen alignment style (preserved)
        'binary_operator_spaces' => [
            'operators' => [
                '='  => 'align_single_space_minimal',
                '=>' => 'align_single_space_minimal',
            ],
        ],

        // Keep Yoda off
        'yoda_style' => [
            'equal'                => false,
            'identical'            => false,
            'less_and_greater'     => false,
            'always_move_variable' => false,
        ],

        // Keep these quality-of-life rules
        'global_namespace_import' => [
            'import_classes'   => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        'function_declaration' => [
            'closure_function_spacing' => 'one',
            'closure_fn_spacing'       => 'one',
        ],

        // Modern diffs: add trailing commas in multiline constructs
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments'],
        ],

        // Useful additions for hygiene (safe + common)
        'no_unused_imports'               => true,
        'ordered_imports'                 => ['sort_algorithm' => 'alpha'],
        'whitespace_after_comma_in_array' => ['ensure_single_space' => true],
        'single_line_throw'               => false, // keep your preference
        'self_accessor'                   => false, // keep your preference
    ])
    ->setFinder($finder);

// Allow running on PHP 8.3/8.4 even though composer.json minimum is PHP 8.2
// The code generated is compatible with PHP 8.2 (no 8.3+ specific syntax)
if (method_exists($config, 'setUnsupportedPhpVersionAllowed')) {
    $config->setUnsupportedPhpVersionAllowed(true);
}

return $config;
