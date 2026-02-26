<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Bootstrap for unit tests.
 *
 * Provides polyfills for PHPUnit 12 attributes that don't exist in PHPUnit 11.
 */

// Polyfill for PHPUnit 12's AllowMockObjectsWithoutExpectations attribute
// This attribute is only available in PHPUnit 12+. For PHPUnit 11 (used with PHP 8.2),
// we create an alias to our polyfill class to prevent "class does not exist" errors.
if (!class_exists(PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations::class)) {
    class_alias(
        Netresearch\RteCKEditorImage\Tests\Unit\Attribute\AllowMockObjectsWithoutExpectations::class,
        PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations::class,
    );
}
