<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Unit\Listener\FileOperation;

use Doctrine\DBAL\Result;
use Netresearch\RteCKEditorImage\Listener\FileOperation\UpdateImageReferences;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionContainerInterface;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Event\AfterFileMovedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileRenamedEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Unit tests for UpdateImageReferences event listener.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(UpdateImageReferences::class)]
final class UpdateImageReferencesTest extends UnitTestCase
{
    private ConnectionPool&MockObject $connectionPool;

    private HtmlParser $htmlParser;

    private UpdateImageReferences $subject;

    /** @var Connection&MockObject */
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->connection     = $this->createMock(Connection::class);
        $this->htmlParser     = new HtmlParser();

        $logManager = $this->createMock(LogManager::class);
        $logManager->method('getLogger')->willReturn(new NullLogger());

        $this->subject = new UpdateImageReferences(
            $this->connectionPool,
            $this->htmlParser,
            $logManager,
        );
    }

    #[Test]
    public function handleFileRenamedSkipsNonAbstractFile(): void
    {
        $file = $this->createMock(FileInterface::class);

        $event = new AfterFileRenamedEvent($file, 'old-name.jpg');

        $this->connectionPool->expects(self::never())->method('getQueryBuilderForTable');

        $this->subject->handleFileRenamed($event);
    }

    #[Test]
    public function handleFileMovedSkipsNonAbstractFile(): void
    {
        $file         = $this->createMock(FileInterface::class);
        $folder       = $this->createMock(Folder::class);
        $targetFolder = $this->createMock(Folder::class);

        $event = new AfterFileMovedEvent($file, $targetFolder, $folder);

        $this->connectionPool->expects(self::never())->method('getQueryBuilderForTable');

        $this->subject->handleFileMoved($event);
    }

    #[Test]
    public function handleFileRenamedSkipsFileWithUidZero(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(0);

        $event = new AfterFileRenamedEvent($file, 'old-name.jpg');

        $this->connectionPool->expects(self::never())->method('getQueryBuilderForTable');

        $this->subject->handleFileRenamed($event);
    }

    #[Test]
    public function handleFileRenamedSkipsFileWithNullPublicUrl(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(42);
        $file->method('getPublicUrl')->willReturn(null);

        $event = new AfterFileRenamedEvent($file, 'old-name.jpg');

        $this->connectionPool->expects(self::never())->method('getQueryBuilderForTable');

        $this->subject->handleFileRenamed($event);
    }

    #[Test]
    public function handleFileRenamedSkipsFileWithEmptyPublicUrl(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(42);
        $file->method('getPublicUrl')->willReturn('');

        $event = new AfterFileRenamedEvent($file, 'old-name.jpg');

        $this->connectionPool->expects(self::never())->method('getQueryBuilderForTable');

        $this->subject->handleFileRenamed($event);
    }

    #[Test]
    public function handleFileRenamedSkipsWhenNoRefindexMatches(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(42);
        $file->method('getPublicUrl')->willReturn('/fileadmin/new-name.jpg');

        $this->setupMocks([], []);

        $this->connection->expects(self::never())->method('update');

        $event = new AfterFileRenamedEvent($file, 'old-name.jpg');
        $this->subject->handleFileRenamed($event);
    }

    #[Test]
    public function handleFileRenamedUpdatesSrcForMatchingFileUid(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(1);
        $file->method('getPublicUrl')->willReturn('/fileadmin/renamed.jpg');

        $oldHtml = '<p>Text <img data-htmlarea-file-uid="1" src="/fileadmin/old.jpg" /> more</p>';
        $newHtml = '<p>Text <img data-htmlarea-file-uid="1" src="/fileadmin/renamed.jpg" /> more</p>';

        $this->setupMocks(
            [['tablename' => 'tt_content', 'recuid' => 1, 'field' => 'bodytext']],
            ['tt_content' => [1 => ['bodytext' => $oldHtml]]],
        );

        $this->connection->expects(self::once())->method('update')
            ->with('tt_content', ['bodytext' => $newHtml], ['uid' => 1]);

        $event = new AfterFileRenamedEvent($file, 'old.jpg');
        $this->subject->handleFileRenamed($event);
    }

    #[Test]
    public function handleFileMovedUpdatesSrcForMatchingFileUid(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(5);
        $file->method('getPublicUrl')->willReturn('/fileadmin/subdir/photo.jpg');

        $oldHtml = '<img data-htmlarea-file-uid="5" src="/fileadmin/photo.jpg" />';
        $newHtml = '<img data-htmlarea-file-uid="5" src="/fileadmin/subdir/photo.jpg" />';

        $this->setupMocks(
            [['tablename' => 'tt_content', 'recuid' => 10, 'field' => 'bodytext']],
            ['tt_content' => [10 => ['bodytext' => $oldHtml]]],
        );

        $this->connection->expects(self::once())->method('update')
            ->with('tt_content', ['bodytext' => $newHtml], ['uid' => 10]);

        $folder       = $this->createMock(Folder::class);
        $targetFolder = $this->createMock(Folder::class);

        $event = new AfterFileMovedEvent($file, $targetFolder, $folder);
        $this->subject->handleFileMoved($event);
    }

    #[Test]
    public function handleFileRenamedUpdatesMultipleImgTagsWithSameUid(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(1);
        $file->method('getPublicUrl')->willReturn('/fileadmin/new.jpg');

        $oldHtml = '<p><img data-htmlarea-file-uid="1" src="/fileadmin/old.jpg" /></p>'
            . '<p><img data-htmlarea-file-uid="1" src="/fileadmin/old.jpg" /></p>';
        $newHtml = '<p><img data-htmlarea-file-uid="1" src="/fileadmin/new.jpg" /></p>'
            . '<p><img data-htmlarea-file-uid="1" src="/fileadmin/new.jpg" /></p>';

        $this->setupMocks(
            [['tablename' => 'tt_content', 'recuid' => 1, 'field' => 'bodytext']],
            ['tt_content' => [1 => ['bodytext' => $oldHtml]]],
        );

        $this->connection->expects(self::once())->method('update')
            ->with('tt_content', ['bodytext' => $newHtml], ['uid' => 1]);

        $event = new AfterFileRenamedEvent($file, 'old.jpg');
        $this->subject->handleFileRenamed($event);
    }

    #[Test]
    public function handleFileRenamedDoesNotTouchImgTagsWithDifferentUid(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(1);
        $file->method('getPublicUrl')->willReturn('/fileadmin/new.jpg');

        $html = '<p><img data-htmlarea-file-uid="99" src="/fileadmin/other.jpg" /></p>';

        $this->setupMocks(
            [['tablename' => 'tt_content', 'recuid' => 1, 'field' => 'bodytext']],
            ['tt_content' => [1 => ['bodytext' => $html]]],
        );

        $this->connection->expects(self::never())->method('update');

        $event = new AfterFileRenamedEvent($file, 'old.jpg');
        $this->subject->handleFileRenamed($event);
    }

    #[Test]
    public function handleFileRenamedNoOpWhenSrcAlreadyCorrect(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getUid')->willReturn(1);
        $file->method('getPublicUrl')->willReturn('/fileadmin/current.jpg');

        $html = '<img data-htmlarea-file-uid="1" src="/fileadmin/current.jpg" />';

        $this->setupMocks(
            [['tablename' => 'tt_content', 'recuid' => 1, 'field' => 'bodytext']],
            ['tt_content' => [1 => ['bodytext' => $html]]],
        );

        $this->connection->expects(self::never())->method('update');

        $event = new AfterFileRenamedEvent($file, 'old.jpg');
        $this->subject->handleFileRenamed($event);
    }

    /**
     * Set up ConnectionPool mocks for refindex query and field value fetching.
     *
     * @param array<int, array<string, mixed>>                 $refindexRecords Records returned by sys_refindex query
     * @param array<string, array<int, array<string, string>>> $fieldValues     Nested: table => uid => field => value
     */
    private function setupMocks(array $refindexRecords, array $fieldValues): void
    {
        $refindexStatement = $this->createMock(Result::class);
        $refindexStatement->method('fetchAllAssociative')->willReturn($refindexRecords);

        $refindexQb = $this->createQueryBuilderMock($refindexStatement);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturnCallback(function (string $table) use ($refindexQb, $fieldValues): QueryBuilder {
                if ($table === 'sys_refindex') {
                    return $refindexQb;
                }

                return $this->createFieldQueryBuilder($table, $fieldValues);
            });

        $this->connectionPool
            ->method('getConnectionForTable')
            ->willReturn($this->connection);
    }

    /**
     * Create a QueryBuilder mock that returns the given result.
     */
    private function createQueryBuilderMock(Result&MockObject $statement): QueryBuilder&MockObject
    {
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $expressionBuilder->method('eq')->willReturn('1=1');

        $restrictions = $this->createMock(QueryRestrictionContainerInterface::class);
        $restrictions->method('removeAll')->willReturnSelf();
        $restrictions->method('add')->willReturnSelf();

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('getRestrictions')->willReturn($restrictions);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('groupBy')->willReturnSelf();
        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('createNamedParameter')->willReturn("'dummy'");
        $queryBuilder->method('executeQuery')->willReturn($statement);

        return $queryBuilder;
    }

    /**
     * Create a QueryBuilder mock for field value fetching.
     *
     * @param array<string, array<int, array<string, string>>> $fieldValues
     */
    private function createFieldQueryBuilder(string $tableName, array $fieldValues): QueryBuilder&MockObject
    {
        // Find the value for this table (first matching uid/field pair)
        $value = null;

        if (isset($fieldValues[$tableName])) {
            $tableData = $fieldValues[$tableName];
            $firstUid  = array_key_first($tableData);

            if ($firstUid !== null) {
                $firstField = array_key_first($tableData[$firstUid]);

                if ($firstField !== null) {
                    $value = $tableData[$firstUid][$firstField];
                }
            }
        }

        $statement = $this->createMock(Result::class);
        $statement->method('fetchOne')->willReturn($value);

        return $this->createQueryBuilderMock($statement);
    }
}
