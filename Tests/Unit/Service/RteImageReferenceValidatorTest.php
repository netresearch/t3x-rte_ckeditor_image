<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Service;

use Netresearch\RteCKEditorImage\Dto\ValidationIssue;
use Netresearch\RteCKEditorImage\Dto\ValidationIssueType;
use Netresearch\RteCKEditorImage\Dto\ValidationResult;
use Netresearch\RteCKEditorImage\Service\RteImageReferenceValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Unit tests for RteImageReferenceValidator.
 *
 * Tests the HTML parsing and issue detection logic in isolation.
 * Database-dependent tests are in the functional test suite.
 */
#[CoversClass(RteImageReferenceValidator::class)]
class RteImageReferenceValidatorTest extends TestCase
{
    private RteImageReferenceValidator $subject;

    /** @var ResourceFactory&\PHPUnit\Framework\MockObject\MockObject */
    private ResourceFactory $resourceFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $connectionPool        = $this->createMock(ConnectionPool::class);
        $this->resourceFactory = $this->createMock(ResourceFactory::class);

        $this->subject = new RteImageReferenceValidator(
            $connectionPool,
            $this->resourceFactory,
            new HtmlParser(),
        );
    }

    #[Test]
    public function cleanContentReturnsNoIssues(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/image.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(1)
            ->willReturn($file);

        $html = '<p>Text <img data-htmlarea-file-uid="1" src="/fileadmin/image.jpg" alt="test" /> more</p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 1, 'bodytext');

        self::assertSame([], $issues);
    }

    #[Test]
    public function detectsProcessedImageSrc(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/image.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(1)
            ->willReturn($file);

        $html = '<p><img data-htmlarea-file-uid="1" src="/fileadmin/_processed_/a/b/csm_image_abc123.jpg" /></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 42, 'bodytext');

        self::assertCount(1, $issues);
        self::assertSame(ValidationIssueType::ProcessedImageSrc, $issues[0]->type);
        self::assertSame(42, $issues[0]->uid);
        self::assertSame('/fileadmin/_processed_/a/b/csm_image_abc123.jpg', $issues[0]->currentSrc);
        self::assertSame('/fileadmin/image.jpg', $issues[0]->expectedSrc);
    }

    #[Test]
    public function detectsSrcMismatch(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/new-location/image.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(5)
            ->willReturn($file);

        $html = '<p><img data-htmlarea-file-uid="5" src="/fileadmin/old-location/image.jpg" /></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 10, 'bodytext');

        self::assertCount(1, $issues);
        self::assertSame(ValidationIssueType::SrcMismatch, $issues[0]->type);
        self::assertSame(5, $issues[0]->fileUid);
        self::assertSame('/fileadmin/old-location/image.jpg', $issues[0]->currentSrc);
        self::assertSame('/fileadmin/new-location/image.jpg', $issues[0]->expectedSrc);
    }

    #[Test]
    public function detectsMissingFileUid(): void
    {
        $html = '<p><img src="/fileadmin/image.jpg" alt="no uid" /></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 7, 'bodytext');

        self::assertCount(1, $issues);
        self::assertSame(ValidationIssueType::MissingFileUid, $issues[0]->type);
        self::assertNull($issues[0]->fileUid);
        self::assertSame('/fileadmin/image.jpg', $issues[0]->currentSrc);
        self::assertFalse($issues[0]->isFixable());
    }

    #[Test]
    public function detectsOrphanedFileUid(): void
    {
        $this->resourceFactory
            ->method('getFileObject')
            ->with(999)
            ->willThrowException(new FileDoesNotExistException());

        $html = '<p><img data-htmlarea-file-uid="999" src="/fileadmin/deleted.jpg" /></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 3, 'bodytext');

        self::assertCount(1, $issues);
        self::assertSame(ValidationIssueType::OrphanedFileUid, $issues[0]->type);
        self::assertSame(999, $issues[0]->fileUid);
        self::assertTrue($issues[0]->isFixable());
    }

    #[Test]
    public function detectsBrokenSrc(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/image.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(2)
            ->willReturn($file);

        $html = '<p><img data-htmlarea-file-uid="2" src="" /></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 5, 'bodytext');

        self::assertCount(1, $issues);
        self::assertSame(ValidationIssueType::BrokenSrc, $issues[0]->type);
        self::assertSame('/fileadmin/image.jpg', $issues[0]->expectedSrc);
    }

    #[Test]
    public function detectsMultipleIssuesInSameContent(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/photo.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->willReturnCallback(function (int $uid) use ($file): File {
                if ($uid === 1) {
                    return $file;
                }

                throw new FileDoesNotExistException();
            });

        $html = '<p>'
            . '<img data-htmlarea-file-uid="1" src="/fileadmin/_processed_/x/csm_photo.jpg" />'
            . '<img src="/fileadmin/no-uid.jpg" />'
            . '<img data-htmlarea-file-uid="99" src="/fileadmin/gone.jpg" />'
            . '</p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 1, 'bodytext');

        self::assertCount(3, $issues);
        self::assertSame(ValidationIssueType::ProcessedImageSrc, $issues[0]->type);
        self::assertSame(0, $issues[0]->imgIndex);
        self::assertSame(ValidationIssueType::MissingFileUid, $issues[1]->type);
        self::assertSame(1, $issues[1]->imgIndex);
        self::assertSame(ValidationIssueType::OrphanedFileUid, $issues[2]->type);
        self::assertSame(2, $issues[2]->imgIndex);
    }

    #[Test]
    public function htmlWithNoImgTagsReturnsNoIssues(): void
    {
        $html = '<p>Just text, no images here.</p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 1, 'bodytext');

        self::assertSame([], $issues);
    }

    #[Test]
    public function validationResultTracksScannedImages(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/ok.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->willReturn($file);

        $html   = '<p><img data-htmlarea-file-uid="1" src="/fileadmin/ok.jpg" /><img data-htmlarea-file-uid="1" src="/fileadmin/ok.jpg" /></p>';
        $result = new ValidationResult();

        $this->subject->validateHtml($html, 'tt_content', 1, 'bodytext', $result);

        self::assertSame(2, $result->getScannedImages());
        self::assertFalse($result->hasIssues());
    }

    #[Test]
    public function validationResultGetFixableIssuesExcludesMissingFileUid(): void
    {
        $result = new ValidationResult();

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::MissingFileUid,
            table: 'tt_content',
            uid: 1,
            field: 'bodytext',
            fileUid: null,
            currentSrc: '/img.jpg',
            expectedSrc: null,
            imgIndex: 0,
        ));

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::SrcMismatch,
            table: 'tt_content',
            uid: 2,
            field: 'bodytext',
            fileUid: 1,
            currentSrc: '/old.jpg',
            expectedSrc: '/new.jpg',
            imgIndex: 0,
        ));

        self::assertCount(2, $result->getIssues());
        self::assertCount(1, $result->getFixableIssues());
        self::assertSame(ValidationIssueType::SrcMismatch, $result->getFixableIssues()[0]->type);
    }

    #[Test]
    public function validationResultTracksAffectedRecords(): void
    {
        $result = new ValidationResult();

        // Two issues for the same record
        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::SrcMismatch,
            table: 'tt_content',
            uid: 1,
            field: 'bodytext',
            fileUid: 1,
            currentSrc: '/old.jpg',
            expectedSrc: '/new.jpg',
            imgIndex: 0,
        ));

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::ProcessedImageSrc,
            table: 'tt_content',
            uid: 1,
            field: 'bodytext',
            fileUid: 2,
            currentSrc: '/processed.jpg',
            expectedSrc: '/original.jpg',
            imgIndex: 1,
        ));

        // One issue for a different record
        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::OrphanedFileUid,
            table: 'tt_content',
            uid: 5,
            field: 'bodytext',
            fileUid: 99,
            currentSrc: '/gone.jpg',
            expectedSrc: null,
            imgIndex: 0,
        ));

        self::assertSame(3, count($result->getIssues()));
        self::assertSame(2, $result->getAffectedRecords());
    }

    #[Test]
    public function detectsBrokenSrcWithNullSrc(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/image.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(3)
            ->willReturn($file);

        // img tag with data-htmlarea-file-uid but NO src attribute at all
        $html = '<p><img data-htmlarea-file-uid="3" alt="no src attribute" /></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 15, 'bodytext');

        self::assertCount(1, $issues);
        self::assertSame(ValidationIssueType::BrokenSrc, $issues[0]->type);
        self::assertNull($issues[0]->currentSrc);
        self::assertSame('/fileadmin/image.jpg', $issues[0]->expectedSrc);
        self::assertSame(3, $issues[0]->fileUid);
        self::assertTrue($issues[0]->isFixable());
    }

    #[Test]
    public function returnsNullForEmptyPublicUrl(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn(null);

        $this->resourceFactory
            ->method('getFileObject')
            ->with(4)
            ->willReturn($file);

        $html = '<p><img data-htmlarea-file-uid="4" src="/fileadmin/image.jpg" /></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 20, 'bodytext');

        self::assertSame([], $issues);
    }

    #[Test]
    public function returnsNullForEmptyStringPublicUrl(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(6)
            ->willReturn($file);

        $html = '<p><img data-htmlarea-file-uid="6" src="/fileadmin/image.jpg" /></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 25, 'bodytext');

        self::assertSame([], $issues);
    }

    // -------------------------------------------------------------------------
    // applyFixes() edge cases via reflection
    // -------------------------------------------------------------------------

    /**
     * Invoke the private applyFixes() method via reflection.
     *
     * @param list<ValidationIssue> $issues
     */
    private function invokeApplyFixes(string $html, array $issues): string
    {
        $method = new ReflectionMethod(RteImageReferenceValidator::class, 'applyFixes');

        $result = $method->invoke($this->subject, $html, $issues);
        self::assertIsString($result);

        return $result;
    }

    #[Test]
    public function applyFixesReturnsUnchangedHtmlWhenFixMapIsEmpty(): void
    {
        // Issue with null fileUid (MissingFileUid) produces an empty fixMap
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::MissingFileUid,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: null,
                currentSrc: '/fileadmin/img.jpg',
                expectedSrc: null,
                imgIndex: 0,
            ),
        ];

        $html = '<p><img src="/fileadmin/img.jpg" /></p>';

        self::assertSame($html, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesInsertsSrcWhenCurrentSrcIsEmpty(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::BrokenSrc,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 2,
                currentSrc: '',
                expectedSrc: '/fileadmin/banner.png',
                imgIndex: 0,
            ),
        ];

        $html     = '<p><img data-htmlarea-file-uid="2" src="" alt="banner" /></p>';
        $expected = '<p><img data-htmlarea-file-uid="2" src="/fileadmin/banner.png" alt="banner" /></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesInsertsSrcWhenCurrentSrcIsNull(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::BrokenSrc,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 3,
                currentSrc: null,
                expectedSrc: '/fileadmin/image.jpg',
                imgIndex: 0,
            ),
        ];

        $html     = '<p><img data-htmlarea-file-uid="3" alt="no src" /></p>';
        $expected = '<p><img src="/fileadmin/image.jpg" data-htmlarea-file-uid="3" alt="no src" /></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesReturnsUnchangedHtmlWhenSrcAlreadyCorrect(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::SrcMismatch,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 1,
                currentSrc: '/fileadmin/image.jpg',
                expectedSrc: '/fileadmin/image.jpg',
                imgIndex: 0,
            ),
        ];

        $html = '<p><img data-htmlarea-file-uid="1" src="/fileadmin/image.jpg" /></p>';

        // src already matches expectedSrc, so no change should be applied
        self::assertSame($html, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesReplacesSrcWhenMismatched(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::SrcMismatch,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 5,
                currentSrc: '/fileadmin/old-location/photo.jpg',
                expectedSrc: '/fileadmin/new-location/photo.jpg',
                imgIndex: 0,
            ),
        ];

        $html     = '<p><img data-htmlarea-file-uid="5" src="/fileadmin/old-location/photo.jpg" /></p>';
        $expected = '<p><img data-htmlarea-file-uid="5" src="/fileadmin/new-location/photo.jpg" /></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesSkipsImgWithoutFileUidAttribute(): void
    {
        // Issue references fileUid=1, but the img tag in HTML has no data-htmlarea-file-uid
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::SrcMismatch,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 1,
                currentSrc: '/fileadmin/old.jpg',
                expectedSrc: '/fileadmin/new.jpg',
                imgIndex: 0,
            ),
        ];

        $html = '<p><img src="/fileadmin/old.jpg" alt="no uid" /></p>';

        // No data-htmlarea-file-uid attribute, so the fix cannot be applied
        self::assertSame($html, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesHandlesIssueWithNullExpectedSrc(): void
    {
        // OrphanedFileUid with null expectedSrc: should not be added to fixMap
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::OrphanedFileUid,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 999,
                currentSrc: '/fileadmin/deleted.jpg',
                expectedSrc: null,
                imgIndex: 0,
            ),
        ];

        $html = '<p><img data-htmlarea-file-uid="999" src="/fileadmin/deleted.jpg" /></p>';

        // null expectedSrc means fixMap is empty, so HTML is returned unchanged
        self::assertSame($html, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesSkipsImgWithDifferentFileUid(): void
    {
        // Issue references fileUid=1, but the img tag has fileUid=99
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::SrcMismatch,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 1,
                currentSrc: '/fileadmin/old.jpg',
                expectedSrc: '/fileadmin/new.jpg',
                imgIndex: 0,
            ),
        ];

        $html = '<p><img data-htmlarea-file-uid="99" src="/fileadmin/old.jpg" /></p>';

        // The img tag's file UID (99) is not in the fixMap, so no fix applied
        self::assertSame($html, $this->invokeApplyFixes($html, $issues));
    }

    // -------------------------------------------------------------------------
    // Nested link wrapper detection and fixing (#667)
    // -------------------------------------------------------------------------

    #[Test]
    public function detectsNestedLinkWrapper(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/test.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(2)
            ->willReturn($file);

        $html = '<p><a href="https://example.com"><a href="https://example.com">'
            . '<img class="image-inline" src="/fileadmin/test.jpg" data-htmlarea-file-uid="2" />'
            . '</a></a></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 1, 'bodytext');

        self::assertCount(1, $issues);
        self::assertSame(ValidationIssueType::NestedLinkWrapper, $issues[0]->type);
        self::assertSame(2, $issues[0]->fileUid);
        self::assertTrue($issues[0]->isFixable());
    }

    #[Test]
    public function detectsNestedLinkWrapperWithRealWorldAttributes(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/user_upload/photo.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(5)
            ->willReturn($file);

        $html = '<p><a class="image image-inline" href="t3://page?uid=1#1" target="_blank">'
            . '<a class="image image-inline" href="t3://page?uid=1#1" target="_blank">'
            . '<img class="image-inline" src="/fileadmin/user_upload/photo.jpg" '
            . 'data-htmlarea-file-uid="5" width="300" height="200" />'
            . '</a></a></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 42, 'bodytext');

        self::assertCount(1, $issues);
        self::assertSame(ValidationIssueType::NestedLinkWrapper, $issues[0]->type);
        self::assertSame(5, $issues[0]->fileUid);
    }

    #[Test]
    public function detectsNestedLinkWrapperWithWhitespace(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/test.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(2)
            ->willReturn($file);

        $html = "<p>\n<a href=\"https://example.com\">\n  <a href=\"https://example.com\">\n    "
            . "<img src=\"/fileadmin/test.jpg\" data-htmlarea-file-uid=\"2\" />\n  </a>\n</a>\n</p>";

        $issues = $this->subject->validateHtml($html, 'tt_content', 1, 'bodytext');

        $nestedIssues = array_filter(
            $issues,
            static fn (ValidationIssue $i): bool => $i->type === ValidationIssueType::NestedLinkWrapper,
        );

        self::assertCount(1, $nestedIssues);
    }

    #[Test]
    public function noNestedLinkIssueForSingleLinkWrappedImage(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/test.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->with(1)
            ->willReturn($file);

        $html = '<p><a href="https://example.com">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="1" />'
            . '</a></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 1, 'bodytext');

        self::assertSame([], $issues);
    }

    #[Test]
    public function detectsNestedLinkWrapperAmongNormalContent(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getPublicUrl')->willReturn('/fileadmin/img.jpg');

        $this->resourceFactory
            ->method('getFileObject')
            ->willReturn($file);

        $html = '<p><img src="/fileadmin/img.jpg" data-htmlarea-file-uid="1" /></p>'
            . '<p><a href="https://example.com"><a href="https://example.com">'
            . '<img src="/fileadmin/img.jpg" data-htmlarea-file-uid="3" />'
            . '</a></a></p>';

        $issues = $this->subject->validateHtml($html, 'tt_content', 1, 'bodytext');

        self::assertCount(1, $issues);
        self::assertSame(ValidationIssueType::NestedLinkWrapper, $issues[0]->type);
        self::assertSame(3, $issues[0]->fileUid);
    }

    #[Test]
    public function applyFixesCollapsesNestedLinkWrapper(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::NestedLinkWrapper,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 2,
                currentSrc: null,
                expectedSrc: null,
                imgIndex: 0,
            ),
        ];

        $html = '<p><a href="https://example.com" target="_blank">'
            . '<a href="https://example.com" target="_blank">'
            . '<img class="image-inline" src="/fileadmin/test.jpg" data-htmlarea-file-uid="2" />'
            . '</a></a></p>';

        $expected = '<p><a href="https://example.com" target="_blank">'
            . '<img class="image-inline" src="/fileadmin/test.jpg" data-htmlarea-file-uid="2" />'
            . '</a></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesCollapsesNestedLinkPreservingOuterAttributes(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::NestedLinkWrapper,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 7,
                currentSrc: null,
                expectedSrc: null,
                imgIndex: 0,
            ),
        ];

        // Outer and inner <a> have DIFFERENT attributes — outer should be kept.
        // In #667, renderInlineLink() adds the outer <a> with resolved attributes,
        // so outer-wins is the correct behavior for DB content. In practice, both
        // <a> tags have identical attributes since the bug duplicates them.
        $html = '<p><a class="image image-inline" href="t3://page?uid=1" target="_blank">'
            . '<a class="other-class" href="t3://page?uid=99" target="_self">'
            . '<img src="/fileadmin/photo.jpg" data-htmlarea-file-uid="7" />'
            . '</a></a></p>';

        $expected = '<p><a class="image image-inline" href="t3://page?uid=1" target="_blank">'
            . '<img src="/fileadmin/photo.jpg" data-htmlarea-file-uid="7" />'
            . '</a></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesCollapsesNestedLinkWithWhitespace(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::NestedLinkWrapper,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 2,
                currentSrc: null,
                expectedSrc: null,
                imgIndex: 0,
            ),
        ];

        $html = "<p><a href=\"https://example.com\">\n  <a href=\"https://example.com\">\n    "
            . "<img src=\"/fileadmin/test.jpg\" data-htmlarea-file-uid=\"2\" />\n  </a>\n</a></p>";

        $expected = '<p><a href="https://example.com">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="2" />'
            . '</a></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesCollapsesMultipleNestedLinks(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::NestedLinkWrapper,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 2,
                currentSrc: null,
                expectedSrc: null,
                imgIndex: 0,
            ),
            new ValidationIssue(
                type: ValidationIssueType::NestedLinkWrapper,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 4,
                currentSrc: null,
                expectedSrc: null,
                imgIndex: 1,
            ),
        ];

        $html = '<p><a href="https://one.com"><a href="https://one.com">'
            . '<img src="/fileadmin/one.jpg" data-htmlarea-file-uid="2" />'
            . '</a></a></p>'
            . '<p><a href="https://two.com"><a href="https://two.com">'
            . '<img src="/fileadmin/two.jpg" data-htmlarea-file-uid="4" />'
            . '</a></a></p>';

        $expected = '<p><a href="https://one.com">'
            . '<img src="/fileadmin/one.jpg" data-htmlarea-file-uid="2" />'
            . '</a></p>'
            . '<p><a href="https://two.com">'
            . '<img src="/fileadmin/two.jpg" data-htmlarea-file-uid="4" />'
            . '</a></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesLeavesNonNestedLinksUntouched(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::SrcMismatch,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 1,
                currentSrc: '/fileadmin/old.jpg',
                expectedSrc: '/fileadmin/new.jpg',
                imgIndex: 0,
            ),
        ];

        // Normal <a><img></a> structure — should NOT be collapsed, only src fixed
        $html = '<p><a href="https://example.com">'
            . '<img src="/fileadmin/old.jpg" data-htmlarea-file-uid="1" />'
            . '</a></p>';

        $expected = '<p><a href="https://example.com">'
            . '<img src="/fileadmin/new.jpg" data-htmlarea-file-uid="1" />'
            . '</a></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function applyFixesHandlesMixedNestedLinkAndSrcMismatch(): void
    {
        // Both structural (nested link) and attribute (src mismatch) fixes on same content
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::NestedLinkWrapper,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 2,
                currentSrc: null,
                expectedSrc: null,
                imgIndex: 0,
            ),
            new ValidationIssue(
                type: ValidationIssueType::SrcMismatch,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 2,
                currentSrc: '/fileadmin/old.jpg',
                expectedSrc: '/fileadmin/new.jpg',
                imgIndex: 0,
            ),
        ];

        $html = '<p><a href="https://example.com"><a href="https://example.com">'
            . '<img src="/fileadmin/old.jpg" data-htmlarea-file-uid="2" />'
            . '</a></a></p>';

        // Both fixes should apply: collapse nested link AND fix src
        $expected = '<p><a href="https://example.com">'
            . '<img src="/fileadmin/new.jpg" data-htmlarea-file-uid="2" />'
            . '</a></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }

    #[Test]
    public function nestedLinkWrapperIsIncludedInFixableIssues(): void
    {
        $result = new ValidationResult();

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::NestedLinkWrapper,
            table: 'tt_content',
            uid: 1,
            field: 'bodytext',
            fileUid: 2,
            currentSrc: null,
            expectedSrc: null,
            imgIndex: 0,
        ));

        $result->addIssue(new ValidationIssue(
            type: ValidationIssueType::MissingFileUid,
            table: 'tt_content',
            uid: 2,
            field: 'bodytext',
            fileUid: null,
            currentSrc: '/img.jpg',
            expectedSrc: null,
            imgIndex: 0,
        ));

        self::assertCount(2, $result->getIssues());
        self::assertCount(1, $result->getFixableIssues());
        self::assertSame(ValidationIssueType::NestedLinkWrapper, $result->getFixableIssues()[0]->type);
    }

    #[Test]
    public function applyFixesCollapsesTripleNestedLinks(): void
    {
        $issues = [
            new ValidationIssue(
                type: ValidationIssueType::NestedLinkWrapper,
                table: 'tt_content',
                uid: 1,
                field: 'bodytext',
                fileUid: 2,
                currentSrc: null,
                expectedSrc: null,
                imgIndex: 0,
            ),
        ];

        // Triple nesting: <a><a><a><img></a></a></a>
        $html = '<p><a href="https://example.com"><a href="https://example.com">'
            . '<a href="https://example.com">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="2" />'
            . '</a></a></a></p>';

        $expected = '<p><a href="https://example.com">'
            . '<img src="/fileadmin/test.jpg" data-htmlarea-file-uid="2" />'
            . '</a></p>';

        self::assertSame($expected, $this->invokeApplyFixes($html, $issues));
    }
}
