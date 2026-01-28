<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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
