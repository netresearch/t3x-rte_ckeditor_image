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
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/655
 */
#[CoversNothing]
final class Typo3ImagePluginTest extends UnitTestCase
{
    private const TYPO3IMAGE_JS_PATH = __DIR__ . '/../../../Resources/Public/JavaScript/Plugins/typo3image.js';

    private string $jsContent = '';

    /**
     * Lazily load JS content — only when tests actually need it.
     * This allows typo3imageJsFileExists() to run first and provide
     * a clear assertion message if the file is missing.
     */
    private function getJsContent(): string
    {
        if ($this->jsContent === '') {
            $content = file_get_contents(self::TYPO3IMAGE_JS_PATH);
            self::assertNotFalse($content, 'Could not read typo3image.js');
            $this->jsContent = $content;
        }

        return $this->jsContent;
    }

    #[Test]
    public function allowedAttributesIncludesCaption(): void
    {
        // Regression test for https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
        // Caption must be in allowedAttributes to persist dialog-entered captions

        $jsContent = $this->getJsContent();

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
        // Verify the caption field is defined in the dialog fields
        self::assertStringContainsString(
            "caption: { label: 'Caption'",
            $this->getJsContent(),
            'Caption field must be defined in the image dialog',
        );
    }

    #[Test]
    public function typo3imageJsContainsCaptionElementCreation(): void
    {
        // Verify caption element creation code exists
        self::assertStringContainsString(
            "createElement('typo3imageCaption')",
            $this->getJsContent(),
            'Code to create typo3imageCaption element must exist',
        );
    }

    // ========================================================================
    // Issue #655 Regression Tests
    // ========================================================================

    /**
     * Bug 1: New block images must get a default 'image-block' class.
     *
     * When inserting a new image, the dialog's "none" click behavior sets
     * class = preservedAlignmentClasses.join(' '). For new images this is empty.
     * The edit() function must ensure a default class is applied.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/655
     */
    #[Test]
    public function newBlockImageGetsDefaultClass(): void
    {
        // The edit() function must ensure new block images get a default class.
        // Find the edit() function's model.change block where newImageAttributes is built.
        $editPos = strpos($this->getJsContent(), 'function edit(selectedImage, editor, imageAttributes)');
        self::assertNotFalse($editPos, 'edit() function must exist');

        // Get code from edit() up to the next top-level function
        $nextFuncPos = strpos($this->getJsContent(), "\nfunction ", $editPos + 10);
        self::assertIsInt($nextFuncPos, 'Next function after edit() must exist');
        $editCode = substr($this->getJsContent(), $editPos, $nextFuncPos - $editPos);

        // The edit function must detect new images and default the class to 'image-block'
        self::assertStringContainsString(
            'isNewImage',
            $editCode,
            'edit() must check isNewImage flag to conditionally default class (#655 bug 1)',
        );
        self::assertMatchesRegularExpression(
            '/class.*(?:attributes\.class|\|\|).*image-block/',
            $editCode,
            'edit() must default class to image-block for new block images (#655 bug 1)',
        );
    }

    /**
     * Bug 2b: Inline image upcast must filter alignment classes from link class.
     *
     * When upcasting <a class="image-inline" href="..."><img class="image-inline"...></a>,
     * the alignment class 'image-inline' on the <a> must NOT be stored as imageLinkClass.
     * Only the block image upcast filtered these; the inline upcast did not.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/655
     */
    #[Test]
    public function inlineImageUpcastFiltersAlignmentClassesFromLinkClass(): void
    {
        // The inline image upcast (for bare <img> with image-inline class) must
        // filter alignment classes from the parent <a> class, just like block upcast does.

        // Find the inline image upcast section (after "Upcast converter for inline images")
        $inlineUpcastPos = strpos($this->getJsContent(), 'Upcast converter for inline images');
        self::assertNotFalse($inlineUpcastPos, 'Inline image upcast section must exist');

        // Get the code from inline upcast to the next upcast converter
        $nextUpcastPos = strpos($this->getJsContent(), 'Upcast converter for standalone img', $inlineUpcastPos);
        self::assertIsInt($nextUpcastPos, 'Next upcast converter after inline upcast must exist');
        $inlineUpcastCode = substr($this->getJsContent(), $inlineUpcastPos, $nextUpcastPos - $inlineUpcastPos);

        // The inline upcast must filter alignment classes from the link class
        // before storing as imageLinkClass. Look for the actualLinkClass pattern
        // that filters out alignment classes (same pattern used in block upcast).
        self::assertStringContainsString(
            'actualLinkClass',
            $inlineUpcastCode,
            'Inline image upcast must filter alignment classes via actualLinkClass (#655 bug 2b)',
        );
    }

    /**
     * Bug 3: ToggleImageTypeCommand must clean imageLinkClass of alignment classes.
     *
     * When toggling inline→block, the 'image-inline' class must be removed from
     * imageLinkClass, not just from the image's own class attribute.
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/655
     */
    #[Test]
    public function toggleCommandCleansImageLinkClass(): void
    {
        // Find the ToggleImageTypeCommand execute() method
        $togglePos = strpos($this->getJsContent(), 'class ToggleImageTypeCommand');
        self::assertNotFalse($togglePos, 'ToggleImageTypeCommand class must exist');

        // Get ToggleImageTypeCommand code (up to next class or function declaration)
        $nextClassPos = strpos($this->getJsContent(), "\nclass ", $togglePos + 10);
        $nextFuncPos  = strpos($this->getJsContent(), "\nfunction ", $togglePos + 10);
        $endPos       = min(
            $nextClassPos !== false ? $nextClassPos : PHP_INT_MAX,
            $nextFuncPos !== false ? $nextFuncPos : PHP_INT_MAX,
        );
        $toggleCode = substr($this->getJsContent(), $togglePos, $endPos - $togglePos);

        // Must actively filter/clean imageLinkClass during toggle, not just copy it.
        // The execute() method must contain code that reads imageLinkClass and
        // removes alignment classes from it (like image-inline, image-block).
        // Look for getAttribute('imageLinkClass') followed by filtering logic.
        self::assertMatchesRegularExpression(
            "/getAttribute\('imageLinkClass'\).*(?:filter|replace|split)/s",
            $toggleCode,
            'ToggleImageTypeCommand must filter alignment classes from imageLinkClass (#655 bug 3)',
        );
    }
}
