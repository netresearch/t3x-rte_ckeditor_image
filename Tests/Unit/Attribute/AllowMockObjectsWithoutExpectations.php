<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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
