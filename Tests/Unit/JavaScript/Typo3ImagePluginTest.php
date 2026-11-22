<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
 * @author  Netresearch DTT GmbH
 * @license AGPL-3.0-or-later
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

    /**
     * Get the code of a top-level function, from its signature to its closing brace.
     */
    private function getTopLevelFunctionCode(string $signature): string
    {
        $start = strpos($this->getJsContent(), $signature);
        self::assertIsInt($start, $signature . ' must exist in typo3image.js');
        $end = strpos($this->getJsContent(), "\n}\n", $start);
        self::assertIsInt($end, 'End of ' . $signature . ' must exist');

        return substr($this->getJsContent(), $start, $end - $start + 2);
    }

    #[Test]
    public function allowedAttributesIncludesCaption(): void
    {
        // Regression test for https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
        // Caption must be in allowedAttributes to persist dialog-entered captions

        $jsContent = $this->getJsContent();

        // Find the allowedAttributes list definition
        // Pattern matches: allowedAttributes = [ ... ] or allowedAttributes = new Set([ ... ])
        $pattern = '/allowedAttributes\s*=\s*(?:new Set\(\s*)?\[(.*?)\]/s';
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
        // Verify the caption field is defined in the dialog fields (uses translated label with fallback)
        self::assertStringContainsString(
            "caption: { label: img.lang.caption || 'Caption'",
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
            '/imageClass = attributes\.class \|\| defaultImageClass\(isNewImage, isInlineImage\)/',
            $editCode,
            'edit() must default an empty class through defaultImageClass() (#655 bug 1)',
        );

        // defaultImageClass() keeps an empty class for existing images and
        // returns image-block for new block images
        $defaultClassCode = $this->getTopLevelFunctionCode('function defaultImageClass(isNewImage, isInlineImage)');
        self::assertMatchesRegularExpression(
            "/if \\(!isNewImage\\) \\{\\s*return '';\\s*\\}/",
            $defaultClassCode,
            'defaultImageClass() must keep the empty class of an existing image (#655 bug 1)',
        );
        self::assertStringContainsString(
            "return isInlineImage ? 'image-inline' : 'image-block';",
            $defaultClassCode,
            'defaultImageClass() must default new block images to image-block (#655 bug 1)',
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
        // before storing as imageLinkClass: it keeps only the own classes that
        // splitLinkClass() returns (same helper as the double-link upcast).
        self::assertStringContainsString(
            'link.class = splitLinkClass(link.class).own;',
            $inlineUpcastCode,
            'Inline image upcast must filter alignment classes via splitLinkClass() (#655 bug 2b)',
        );
        self::assertStringContainsString(
            'own: parts.filter(c => !ALIGNMENT_CLASSES.has(c))',
            $this->getTopLevelFunctionCode('function splitLinkClass(classValue)'),
            'splitLinkClass() must leave the alignment classes out of the own classes (#655 bug 2b)',
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
        // execute() takes the attributes from getToggledImageAttributes(), which
        // reads imageLinkClass and removes alignment classes from it (like
        // image-inline, image-block).
        self::assertStringContainsString(
            'getToggledImageAttributes(imageElement)',
            $toggleCode,
            'ToggleImageTypeCommand must copy the attributes via getToggledImageAttributes() (#655 bug 3)',
        );
        // Look for getAttribute('imageLinkClass') followed by filtering logic.
        self::assertMatchesRegularExpression(
            "/getAttribute\('imageLinkClass'\).*(?:filter|replace|split)/s",
            $this->getTopLevelFunctionCode('function getToggledImageAttributes(imageElement)'),
            'ToggleImageTypeCommand must filter alignment classes from imageLinkClass (#655 bug 3)',
        );
    }
}
