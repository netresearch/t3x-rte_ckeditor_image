<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\JavaScript;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Regression tests for JavaScript plugin configuration.
 *
 * These tests validate critical JavaScript configurations by parsing
 * the source files. This ensures important fixes aren't accidentally reverted.
 *
 * @author  Netresearch DTT GmbH <info@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
 */
#[CoversNothing]
final class Typo3ImagePluginTest extends UnitTestCase
{
    private const TYPO3IMAGE_JS_PATH = __DIR__ . '/../../../Resources/Public/JavaScript/Plugins/typo3image.js';

    #[Test]
    public function allowedAttributesIncludesCaption(): void
    {
        // Regression test for https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
        // Caption must be in allowedAttributes to persist dialog-entered captions

        $jsContent = file_get_contents(self::TYPO3IMAGE_JS_PATH);

        self::assertNotFalse($jsContent, 'Could not read typo3image.js');

        // Find the allowedAttributes array definition
        // Pattern matches: allowedAttributes = [ ... ]
        $pattern = '/allowedAttributes\s*=\s*\[(.*?)\]/s';
        $matches = [];
        preg_match($pattern, $jsContent, $matches);

        self::assertNotEmpty($matches, 'Could not find allowedAttributes array in typo3image.js');

        $allowedAttributesContent = $matches[1];

        // Verify 'caption' is present in the array
        self::assertStringContainsString(
            "'caption'",
            $allowedAttributesContent,
            "The 'caption' attribute must be in allowedAttributes to fix issue #546. "
            . 'Without it, captions entered in the image dialog are not persisted.',
        );
    }

    #[Test]
    public function typo3imageJsFileExists(): void
    {
        self::assertFileExists(
            self::TYPO3IMAGE_JS_PATH,
            'typo3image.js must exist at expected path',
        );
    }

    #[Test]
    public function typo3imageJsContainsCaptionFieldDefinition(): void
    {
        $jsContent = file_get_contents(self::TYPO3IMAGE_JS_PATH);

        self::assertNotFalse($jsContent, 'Could not read typo3image.js');

        // Verify the caption field is defined in the dialog fields
        self::assertStringContainsString(
            "caption: { label: 'Caption'",
            $jsContent,
            'Caption field must be defined in the image dialog',
        );
    }

    #[Test]
    public function typo3imageJsContainsCaptionElementCreation(): void
    {
        $jsContent = file_get_contents(self::TYPO3IMAGE_JS_PATH);

        self::assertNotFalse($jsContent, 'Could not read typo3image.js');

        // Verify caption element creation code exists
        self::assertStringContainsString(
            "createElement('typo3imageCaption')",
            $jsContent,
            'Code to create typo3imageCaption element must exist',
        );
    }
}
