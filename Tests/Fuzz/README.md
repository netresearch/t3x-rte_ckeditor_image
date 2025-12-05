# Fuzz Testing

This directory contains fuzz tests for security-critical HTML parsing components using [nikic/php-fuzzer](https://github.com/nikic/PHP-Fuzzer).

## Overview

Fuzz testing (fuzzing) automatically generates random/mutated inputs to find crashes, memory exhaustion, or unexpected exceptions. This is particularly important for code that parses untrusted HTML input.

## Targets

### ImageAttributeParserTarget.php
Tests `ImageAttributeParser::parseImageAttributes()` and `parseLinkWithImages()` which parse HTML `<img>` and `<a>` tags using DOMDocument.

### RteImageSoftReferenceParserTarget.php
Tests `RteImageSoftReferenceParser::parse()` which processes RTE content to find FAL soft references.

## Running Fuzz Tests

### Using runTests.sh (recommended)
```bash
# Run ImageAttributeParser fuzzer (default)
Build/Scripts/runTests.sh -s fuzz

# Run with specific target
Build/Scripts/runTests.sh -s fuzz Tests/Fuzz/ImageAttributeParserTarget.php

# Run SoftReference parser fuzzer
Build/Scripts/runTests.sh -s fuzz Tests/Fuzz/RteImageSoftReferenceParserTarget.php

# Run with custom max-runs
Build/Scripts/runTests.sh -s fuzz Tests/Fuzz/ImageAttributeParserTarget.php 50000
```

### Using Composer
```bash
# Run all fuzz tests
composer ci:fuzz

# Run specific fuzzer
composer ci:fuzz:image-parser
composer ci:fuzz:softref-parser
```

### Directly with php-fuzzer
```bash
# Corpus is a positional argument, not an option
.Build/bin/php-fuzzer fuzz Tests/Fuzz/ImageAttributeParserTarget.php \
    Tests/Fuzz/corpus/image-parser \
    --max-runs 10000
```

## Corpus

The `corpus/` directories contain seed inputs that the fuzzer uses as starting points:

- `corpus/image-parser/` - HTML snippets with `<img>` tags
- `corpus/softref-parser/` - RTE content with `data-htmlarea-file-uid` attributes

The fuzzer will:
1. Start with these seed inputs
2. Mutate them (bit flips, boundary values, format strings)
3. Track code coverage to find new execution paths
4. Save interesting inputs back to the corpus

## Interpreting Results

- **Crashes**: The fuzzer found an input that causes a PHP error/exception
- **Hangs**: The fuzzer found an input that causes infinite loops or excessive runtime
- **Memory exhaustion**: The fuzzer found an input that causes excessive memory usage

All crash-inducing inputs are saved for reproduction and debugging.

## CI Integration

Fuzz testing is not run automatically in CI due to its time requirements. Run manually before releases to check for security issues in HTML parsing code.
