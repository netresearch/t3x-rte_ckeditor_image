<?php

/**
 * This file represents the configuration for Code Sniffing PSR-2-related
 * automatic checks of coding guidelines
 * Install @fabpot's great php-cs-fixer tool via
 *
 *  $ composer global require friendsofphp/php-cs-fixer
 *
 * And then simply run
 *
 *  $ php-cs-fixer fix
 *
 * For more information read:
 *  http://www.php-fig.org/psr/psr-2/
 *  http://cs.sensiolabs.org
 */

if (PHP_SAPI !== 'cli') {
    die('This script supports command line usage only. Please check your command.');
}

$header = <<<EOF
This file is part of the package netresearch/rte-ckeditor-image.

For the full copyright and license information, please read the
LICENSE file that was distributed with this source code.
EOF;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                          => true,
        '@PER-CS2x0'                      => true,
        '@Symfony'                        => true,

        // Additional custom rules
        'declare_strict_types'            => true,
        'concat_space'                    => [
            'spacing' => 'one',
        ],
        'header_comment'                  => [
            'header'       => $header,
            'comment_type' => 'PHPDoc',
            'location'     => 'after_open',
            'separate'     => 'both',
        ],
        'phpdoc_to_comment'               => false,
        'phpdoc_no_alias_tag'             => false,
        'no_superfluous_phpdoc_tags'      => false,
        'phpdoc_separation'               => [
            'groups' => [
                [
                    'author',
                    'license',
                    'link',
                ],
            ],
        ],
        'no_alias_functions'              => true,
        'whitespace_after_comma_in_array' => [
            'ensure_single_space' => true,
        ],
        'single_line_throw'               => false,
        'self_accessor'                   => false,
        'global_namespace_import'         => [
            'import_classes'   => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'function_declaration'            => [
            'closure_function_spacing' => 'one',
            'closure_fn_spacing'       => 'one',
        ],
        'binary_operator_spaces'          => [
            'operators' => [
                '='  => 'align_single_space_minimal',
                '=>' => 'align_single_space_minimal',
            ],
        ],
        'yoda_style'                      => [
            'equal'                => false,
            'identical'            => false,
            'less_and_greater'     => false,
            'always_move_variable' => false,
        ],
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->exclude('.build')
            ->exclude('config')
            ->exclude('node_modules')
            ->exclude('var')
            ->in(__DIR__ . '/../')
    );
