<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Command;

use Netresearch\RteCKEditorImage\Command\ValidateImageReferencesCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional tests for ValidateImageReferencesCommand.
 */
class ValidateImageReferencesCommandTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/rte_ckeditor_image',
    ];

    protected array $coreExtensionsToLoad = [
        'typo3/cms-rte-ckeditor',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CommandImport.csv');
    }

    private function createCommandTester(): CommandTester
    {
        $command = $this->get(ValidateImageReferencesCommand::class);
        self::assertSame(ValidateImageReferencesCommand::class, $command::class);

        return new CommandTester($command);
    }

    #[Test]
    public function dryRunReportsIssues(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('processed_image_src', $output);
        self::assertStringContainsString('Dry-run mode', $output);
    }

    #[Test]
    public function fixOptionAppliesFixes(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute(['--fix' => true]);

        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Fixed 1 record(s)', $output);
    }

    #[Test]
    public function fixWithDryRunLeavesRecordsUnchanged(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute(['--fix' => true, '--dry-run' => true]);

        $output = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('1 fixable issue(s) would be fixed', $output);
        self::assertStringNotContainsString('Fixed 1 record(s)', $output);

        $connectionPool = $this->get(ConnectionPool::class);
        self::assertSame(ConnectionPool::class, $connectionPool::class);

        $queryBuilder = $connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->removeAll();

        $bodytext = $queryBuilder
            ->select('bodytext')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        self::assertSame(
            '<p><img data-htmlarea-file-table="sys_file" data-htmlarea-file-uid="1" src="/fileadmin/_processed_/x/csm_image.jpg" /></p>',
            $bodytext,
        );
    }

    #[Test]
    public function tableOptionLimitsScope(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute(['--table' => 'nonexistent_table']);

        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No issues found', $output);
    }

    #[Test]
    public function outputContainsScanSummary(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute([]);

        $output = $tester->getDisplay();

        self::assertStringContainsString('Scanned records', $output);
        self::assertStringContainsString('Scanned images', $output);
        self::assertStringContainsString('Issues found', $output);
    }
}
