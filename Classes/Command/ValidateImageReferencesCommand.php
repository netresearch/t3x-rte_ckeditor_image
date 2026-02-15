<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Command;

use Netresearch\RteCKEditorImage\Dto\ValidationResult;
use Netresearch\RteCKEditorImage\Service\RteImageReferenceValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CLI command to validate and fix RTE image references.
 *
 * Usage:
 *   bin/typo3 rte_ckeditor_image:validate           # dry-run report
 *   bin/typo3 rte_ckeditor_image:validate --fix      # apply fixes
 *   bin/typo3 rte_ckeditor_image:validate --table=tt_content  # limit to table
 */
#[AsCommand(
    name: 'rte_ckeditor_image:validate',
    description: 'Validate and fix RTE image references (stale src, orphaned UIDs, processed URLs)',
)]
class ValidateImageReferencesCommand extends Command
{
    public function __construct(
        private readonly RteImageReferenceValidator $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'fix',
            null,
            InputOption::VALUE_NONE,
            'Apply fixes for fixable issues (default: dry-run report only)',
        );
        $this->addOption(
            'table',
            't',
            InputOption::VALUE_REQUIRED,
            'Limit scan to a specific table (e.g. tt_content)',
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Explicitly request dry-run mode (default behavior)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limitToTable = $input->getOption('table');
        $shouldFix    = (bool) $input->getOption('fix');

        if (!is_string($limitToTable)) {
            $limitToTable = null;
        }

        $io->title('RTE Image Reference Validation');

        if ($limitToTable !== null) {
            $io->note('Limiting scan to table: ' . $limitToTable);
        }

        $result = $this->validator->validate($limitToTable);

        $this->renderSummary($io, $result);

        if (!$result->hasIssues()) {
            $io->success('No issues found. All image references are valid.');

            return Command::SUCCESS;
        }

        $this->renderIssueTable($io, $output, $result);

        if (!$shouldFix) {
            $fixableCount = count($result->getFixableIssues());
            $io->note(sprintf(
                'Dry-run mode. %d fixable issue(s) found. Use --fix to apply corrections.',
                $fixableCount,
            ));

            return Command::FAILURE;
        }

        return $this->applyFixes($io, $result);
    }

    private function renderSummary(SymfonyStyle $io, ValidationResult $result): void
    {
        $io->definitionList(
            ['Scanned records' => (string) $result->getScannedRecords()],
            ['Scanned images'   => (string) $result->getScannedImages()],
            ['Issues found'     => (string) count($result->getIssues())],
            ['Affected records' => (string) $result->getAffectedRecords()],
        );
    }

    private function renderIssueTable(SymfonyStyle $io, OutputInterface $output, ValidationResult $result): void
    {
        $io->section('Issues');

        $table = new Table($output);
        $table->setHeaders(['Type', 'Table', 'UID', 'Field', 'File UID', 'Current src', 'Expected src', 'Fixable']);

        foreach ($result->getIssues() as $issue) {
            $table->addRow([
                $issue->type->value,
                $issue->table,
                (string) $issue->uid,
                $issue->field,
                $issue->fileUid !== null ? (string) $issue->fileUid : '-',
                $this->truncate($issue->currentSrc ?? '-', 50),
                $this->truncate($issue->expectedSrc ?? '-', 50),
                $issue->isFixable() ? 'yes' : 'no',
            ]);
        }

        $table->render();
    }

    private function applyFixes(SymfonyStyle $io, ValidationResult $result): int
    {
        $fixableCount = count($result->getFixableIssues());

        if ($fixableCount === 0) {
            $io->warning('No fixable issues found. Manual intervention required.');

            return Command::FAILURE;
        }

        $io->section('Applying fixes');
        $updatedCount = $this->validator->fix($result);
        $io->success(sprintf('Fixed %d record(s) (%d fixable issues).', $updatedCount, $fixableCount));

        return Command::SUCCESS;
    }

    private function truncate(?string $value, int $maxLength): string
    {
        if ($value === null) {
            return '-';
        }

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 3) . '...';
    }
}
