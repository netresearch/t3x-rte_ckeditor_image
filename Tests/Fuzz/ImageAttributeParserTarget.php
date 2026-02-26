<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Fuzzing target for ImageAttributeParser.
 *
 * Tests parseImageAttributes() and parseLinkWithImages() with random/mutated inputs
 * to find crashes, memory exhaustion, or unexpected exceptions.
 *
 * Usage:
 *   composer ci:fuzz:image-parser
 *
 * @see https://github.com/nikic/PHP-Fuzzer
 */

use Netresearch\RteCKEditorImage\Service\ImageAttributeParser;

require_once dirname(__DIR__, 2) . '/.Build/vendor/autoload.php';

/** @var PhpFuzzer\Config $config */
$parser = new ImageAttributeParser();

$config->setTarget(function (string $input) use ($parser): void {
    // Test parseImageAttributes() - the primary parsing method
    $parser->parseImageAttributes($input);

    // Test parseLinkWithImages() - handles <a><img></a> structures
    $parser->parseLinkWithImages($input);
});

// Limit maximum input length to prevent excessive memory usage
$config->setMaxLen(65536);
