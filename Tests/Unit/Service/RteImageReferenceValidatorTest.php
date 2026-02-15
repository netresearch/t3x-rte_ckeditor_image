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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
}
