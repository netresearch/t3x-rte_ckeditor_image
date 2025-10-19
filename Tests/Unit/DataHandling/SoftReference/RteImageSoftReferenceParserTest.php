<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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
}
