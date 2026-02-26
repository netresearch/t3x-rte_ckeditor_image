<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Attribute;

use Attribute;

/**
 * Polyfill for PHPUnit 12's AllowMockObjectsWithoutExpectations attribute.
 *
 * This attribute is only available in PHPUnit 12+. For PHPUnit 11 (used with PHP 8.2),
 * this polyfill provides a no-op attribute to prevent "class does not exist" errors.
 *
 * @see https://docs.phpunit.de/en/12.0/attributes.html#allowmockobjectswithoutexpectations
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AllowMockObjectsWithoutExpectations {}
