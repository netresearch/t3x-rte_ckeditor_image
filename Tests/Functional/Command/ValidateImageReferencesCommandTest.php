<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Tests\Functional\Command;

use Netresearch\RteCKEditorImage\Command\ValidateImageReferencesCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
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
        self::assertInstanceOf(ValidateImageReferencesCommand::class, $command);

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
