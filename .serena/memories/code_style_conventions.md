# Code Style & Conventions

## PHP Standards
- **Base Standards**: PSR-12, PER-CS2.0, Symfony coding standards
- **Strict Types**: `declare(strict_types=1);` required on all PHP files
- **PHP Version**: 8.2-8.9

## File Header
Every PHP file must have:
```php
<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);
```

## Code Style Rules (PHP-CS-Fixer)
- **Concat Spacing**: Single space around concatenation (`. `)
- **Global Imports**: Import classes, constants, functions globally
- **Binary Operators**: Align `=` and `=>` with single space minimal
- **No Yoda Style**: Natural variable order (`$value === 'constant'`)
- **Function Declaration**: One space closure spacing
- **PHPDoc**: No conversion to comments, no superfluous tags
- **Array Formatting**: Whitespace after comma ensures single space

## Naming Conventions
- **Classes**: PascalCase (e.g., `SelectImageController`)
- **Methods**: camelCase (e.g., `mainAction`, `getImage`)
- **Properties**: camelCase with visibility (e.g., `private ResourceFactory $resourceFactory`)
- **Constants**: UPPER_SNAKE_CASE
- **Namespaces**: PSR-4 compliant (`Netresearch\RteCKEditorImage\...`)

## Type Hints
- **Required**: All method parameters and return types must have type hints
- **Strict**: Use specific types (avoid `mixed` when possible)
- **Nullable**: Use `?Type` or union types for nullable parameters
- **PHP 8.x**: Leverage union types, named arguments, constructor property promotion

## Directory Structure Conventions
- `Classes/` → Application code (Controllers, EventListeners, Database, Utils, DataHandling)
- `Configuration/` → YAML, Routes, TCA, RTE, TypoScript
- `Resources/Public/` → JavaScript, CSS, Images
- `Tests/` → Functional and unit tests
- `Build/` → Quality tool configurations

## Static Analysis
- **PHPStan Level**: 6 (strict rules + deprecation rules)
- **Baseline**: `Build/phpstan-baseline.neon` for known issues
- **TYPO3 Extension**: Uses `saschaegerer/phpstan-typo3`