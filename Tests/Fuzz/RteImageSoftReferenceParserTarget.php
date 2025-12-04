<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * Fuzzing target for RteImageSoftReferenceParser.
 *
 * Tests parse() with random/mutated HTML content to find crashes,
 * memory exhaustion, or unexpected exceptions in soft reference parsing.
 *
 * Usage:
 *   composer ci:fuzz:softref-parser
 *
 * @see https://github.com/nikic/PHP-Fuzzer
 */

use Netresearch\RteCKEditorImage\DataHandling\SoftReference\RteImageSoftReferenceParser;
use TYPO3\CMS\Core\Html\HtmlParser;

require_once dirname(__DIR__, 2) . '/.Build/vendor/autoload.php';

/** @var PhpFuzzer\Config $config */

// Create HtmlParser instance (TYPO3 core class)
$htmlParser = new HtmlParser();

// Create the soft reference parser
$parser = new RteImageSoftReferenceParser($htmlParser);

// Set the parser key (required for parse() to work)
$reflection    = new ReflectionClass($parser);
$parserKeyProp = $reflection->getProperty('parserKey');
$parserKeyProp->setValue($parser, 'rtehtmlarea_images');

$config->setTarget(function (string $input) use ($parser): void {
    // Test parse() - the main entry point
    $parser->parse(
        'tt_content',
        'bodytext',
        1,
        $input,
    );
});

// Limit maximum input length to prevent excessive memory usage
$config->setMaxLen(65536);
