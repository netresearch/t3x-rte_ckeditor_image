<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\DataHandling\SoftReference;

use Netresearch\RteCKEditorImage\DataHandling\SoftReference\RteImageSoftReferenceParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for RteImageSoftReferenceParser.
 */
#[CoversClass(RteImageSoftReferenceParser::class)]
final class RteImageSoftReferenceParserTest extends UnitTestCase
{
    private RteImageSoftReferenceParser $subject;
    private HtmlParser $htmlParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->htmlParser = new HtmlParser();
        $this->subject    = new RteImageSoftReferenceParser($this->htmlParser);
        // setParserKey requires 2 parameters in TYPO3 13
        $this->subject->setParserKey('rtehtmlarea_images', []);
    }

    #[Test]
    public function parseReturnsEmptyResultForContentWithoutImages(): void
    {
        $content = '<p>This is plain text without images.</p>';
        $result  = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        // Parser returns empty content when no substitutions needed
        self::assertEmpty($result->getMatchedElements());
    }

    #[Test]
    public function parseReturnsEmptyResultForImageWithoutDataAttribute(): void
    {
        $content = '<p><img src="/path/to/image.jpg" alt="Test" /></p>';
        $result  = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        // No data-htmlarea-file-uid means no matches
        self::assertEmpty($result->getMatchedElements());
    }

    #[Test]
    public function parseFindsImageWithDataHtmlareaFileUidAttribute(): void
    {
        $content = '<p><img src="/path/to/image.jpg" data-htmlarea-file-uid="123" alt="Test" /></p>';
        $result  = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        $matchedElements = $result->getMatchedElements();
        self::assertNotEmpty($matchedElements);
        self::assertCount(1, $matchedElements);

        $firstMatch = reset($matchedElements);
        self::assertIsArray($firstMatch);
        self::assertArrayHasKey('subst', $firstMatch);
        self::assertIsArray($firstMatch['subst']);
        self::assertSame('db', $firstMatch['subst']['type']);
        self::assertSame('sys_file:123', $firstMatch['subst']['recordRef']);
        self::assertSame('123', $firstMatch['subst']['tokenValue']);
    }

    #[Test]
    public function parseFindsMultipleImagesWithDataAttributes(): void
    {
        $content = '<p><img src="/img1.jpg" data-htmlarea-file-uid="123" />'
            . '<img src="/img2.jpg" data-htmlarea-file-uid="456" /></p>';
        $result = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        $matchedElements = $result->getMatchedElements();
        self::assertCount(2, $matchedElements);

        $values = array_column(array_column($matchedElements, 'subst'), 'tokenValue');
        self::assertContains('123', $values);
        self::assertContains('456', $values);
    }

    #[Test]
    public function parseMixedContentWithAndWithoutDataAttributes(): void
    {
        $content = '<p><img src="/img1.jpg" data-htmlarea-file-uid="123" />'
            . '<img src="/img2.jpg" alt="No UID" />'
            . '<img src="/img3.jpg" data-htmlarea-file-uid="789" /></p>';
        $result = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        $matchedElements = $result->getMatchedElements();
        self::assertCount(2, $matchedElements);

        $values = array_column(array_column($matchedElements, 'subst'), 'tokenValue');
        self::assertContains('123', $values);
        self::assertContains('789', $values);
        self::assertNotContains('', $values);
    }

    #[Test]
    public function parseReplacesDataAttributeWithSoftrefToken(): void
    {
        $content = '<img src="/test.jpg" data-htmlarea-file-uid="999" />';
        $result  = $this->subject->parse('tt_content', 'bodytext', 42, $content);

        // The parser creates soft references for images with data-htmlarea-file-uid
        $matchedElements = $result->getMatchedElements();
        self::assertNotEmpty($matchedElements);

        $firstMatch = reset($matchedElements);
        self::assertIsArray($firstMatch);
        self::assertArrayHasKey('subst', $firstMatch);
        self::assertIsArray($firstMatch['subst']);
        self::assertSame('999', $firstMatch['subst']['tokenValue']);
    }

    #[Test]
    public function parseWithDifferentParserKeyReturnsEmptyResult(): void
    {
        $this->subject->setParserKey('some_other_parser', []);
        $content = '<img src="/test.jpg" data-htmlarea-file-uid="123" />';
        $result  = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        self::assertEmpty($result->getMatchedElements());
    }

    #[Test]
    public function parseWithStructurePathGeneratesCorrectTokenId(): void
    {
        $content = '<img src="/test.jpg" data-htmlarea-file-uid="555" />';
        $result  = $this->subject->parse(
            'tt_content',
            'pi_flexform',
            10,
            $content,
            'data/sheet.language/lDEF/field.el/1',
        );

        $matchedElements = $result->getMatchedElements();
        self::assertNotEmpty($matchedElements);

        $firstMatch = reset($matchedElements);
        self::assertIsArray($firstMatch);
        self::assertArrayHasKey('subst', $firstMatch);
        self::assertIsArray($firstMatch['subst']);
        self::assertArrayHasKey('tokenID', $firstMatch['subst']);
        // Token ID is a hash, just verify it exists
        self::assertNotEmpty($firstMatch['subst']['tokenID']);
    }

    #[Test]
    public function parsePreservesImageAttributesExceptDataUid(): void
    {
        $content = '<img src="/test.jpg" class="image" alt="Alt text" '
            . 'title="Title" data-htmlarea-file-uid="123" width="800" />';
        $result = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        $modifiedContent = $result->getContent();
        self::assertStringContainsString('class="image"', $modifiedContent);
        self::assertStringContainsString('alt="Alt text"', $modifiedContent);
        self::assertStringContainsString('title="Title"', $modifiedContent);
        self::assertStringContainsString('width="800"', $modifiedContent);
    }

    // ========================================================================
    // Edge Case Tests - Malformed HTML Handling
    // ========================================================================

    #[Test]
    public function parseHandlesImageWithMissingQuotesOnAttributes(): void
    {
        // Malformed attributes: missing quotes around value
        $content = '<img src=/test.jpg data-htmlarea-file-uid="456" />';
        $result  = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        // Should still find the data-htmlarea-file-uid
        $matchedElements = $result->getMatchedElements();
        self::assertNotEmpty($matchedElements);

        $firstMatch = reset($matchedElements);
        self::assertIsArray($firstMatch);
        self::assertArrayHasKey('subst', $firstMatch);
        self::assertSame('sys_file:456', $firstMatch['subst']['recordRef']);
    }

    #[Test]
    public function parseFindsNestedImagesInComplexStructure(): void
    {
        // Complex HTML with nested elements and multiple images
        $content = '<div><a href="#"><img src="/outer.jpg" data-htmlarea-file-uid="111" /></a>'
            . '<figure><img src="/figure.jpg" data-htmlarea-file-uid="222" />'
            . '<figcaption>Caption</figcaption></figure></div>';
        $result = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        $matchedElements = $result->getMatchedElements();
        self::assertCount(2, $matchedElements);

        $values = array_column(array_column($matchedElements, 'subst'), 'tokenValue');
        self::assertContains('111', $values);
        self::assertContains('222', $values);
    }

    #[Test]
    public function parseHandlesMixOfRteImagesAndRegularImages(): void
    {
        // Mix of RTE-managed images (with data-htmlarea-file-uid) and regular img tags
        $content = '<img src="/regular1.jpg" alt="Regular image" />'
            . '<img src="/rte1.jpg" data-htmlarea-file-uid="100" />'
            . '<img src="/regular2.jpg" class="external" />'
            . '<img src="/rte2.jpg" data-htmlarea-file-uid="200" />';
        $result = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        // Should only find the 2 RTE images
        $matchedElements = $result->getMatchedElements();
        self::assertCount(2, $matchedElements);

        $values = array_column(array_column($matchedElements, 'subst'), 'tokenValue');
        self::assertContains('100', $values);
        self::assertContains('200', $values);
    }

    #[Test]
    public function parseIncludesEmptyDataHtmlareaFileUidAttribute(): void
    {
        // Empty string value for data-htmlarea-file-uid - parser still extracts it
        $content = '<img src="/test.jpg" data-htmlarea-file-uid="" />';
        $result  = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        // Parser extracts even empty values (validation happens elsewhere)
        $matchedElements = $result->getMatchedElements();
        self::assertNotEmpty($matchedElements);

        $firstMatch = reset($matchedElements);
        self::assertIsArray($firstMatch);
        self::assertSame('', $firstMatch['subst']['tokenValue']);
    }

    #[Test]
    public function parseHandlesNonNumericFileUidValue(): void
    {
        // Non-numeric value in data-htmlarea-file-uid (malformed data)
        $content = '<img src="/test.jpg" data-htmlarea-file-uid="invalid" />';
        $result  = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        // Parser doesn't validate UID format, just extracts the value
        $matchedElements = $result->getMatchedElements();
        self::assertNotEmpty($matchedElements);

        $firstMatch = reset($matchedElements);
        self::assertIsArray($firstMatch);
        self::assertSame('sys_file:invalid', $firstMatch['subst']['recordRef']);
        self::assertSame('invalid', $firstMatch['subst']['tokenValue']);
    }

    #[Test]
    public function parseHandlesMultipleImagesWithDifferentFileUidsInVariousStructures(): void
    {
        // Multiple images with different UIDs in various HTML structures
        $content = '<p><img src="/img1.jpg" data-htmlarea-file-uid="10" /></p>'
            . '<div class="content"><img data-htmlarea-file-uid="20" src="/img2.jpg" alt="Second" /></div>'
            . '<section><img src="/img3.jpg" data-htmlarea-file-uid="30" class="large" /></section>';
        $result = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        $matchedElements = $result->getMatchedElements();
        self::assertCount(3, $matchedElements);

        $references = array_column(array_column($matchedElements, 'subst'), 'recordRef');
        self::assertContains('sys_file:10', $references);
        self::assertContains('sys_file:20', $references);
        self::assertContains('sys_file:30', $references);
    }

    #[Test]
    public function parseHandlesWhitespaceAroundAttributeValue(): void
    {
        // Whitespace inside attribute value
        $content = '<img src="/test.jpg" data-htmlarea-file-uid=" 789 " />';
        $result  = $this->subject->parse('tt_content', 'bodytext', 1, $content);

        $matchedElements = $result->getMatchedElements();
        self::assertNotEmpty($matchedElements);

        // The value includes whitespace as-is
        $firstMatch = reset($matchedElements);
        self::assertIsArray($firstMatch);
        self::assertSame(' 789 ', $firstMatch['subst']['tokenValue']);
    }
}
