<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Command;

use Netresearch\RteCKEditorImage\Dto\SrcOrigin;
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
        $this->addOption(
            'include',
            null,
            InputOption::VALUE_REQUIRED,
            sprintf(
                'Comma-separated origins that are skipped by default but should be reported: %s. '
                . 'Use "all" to disable all skipping.',
                implode(', ', array_map(static fn (SrcOrigin $o): string => $o->value, SrcOrigin::defaultSkipSet())),
            ),
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

        $includeOrigins = $this->parseIncludeOption($input->getOption('include'), $io);

        // Only pass $includeOrigins when non-empty so existing mocks/subclasses
        // that still expect validate($limitToTable) arity keep working.
        $result = $includeOrigins === []
            ? $this->validator->validate($limitToTable)
            : $this->validator->validate($limitToTable, $includeOrigins);

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
        $definitions = [
            ['Scanned records' => (string) $result->getScannedRecords()],
            ['Scanned images'   => (string) $result->getScannedImages()],
            ['Issues found'     => (string) count($result->getIssues())],
            ['Affected records' => (string) $result->getAffectedRecords()],
        ];

        $skipped = $result->getSkippedByOrigin();
        if ($skipped !== []) {
            $parts = [];
            foreach ($skipped as $origin => $count) {
                $parts[] = sprintf('%d %s', $count, $origin);
            }

            $definitions[] = [
                'Skipped (out of scope)' => sprintf('%d total (%s)', $result->getSkippedTotal(), implode(', ', $parts)),
            ];
        }

        $io->definitionList(...$definitions);
    }

    /**
     * @param mixed $rawInclude
     *
     * @return list<SrcOrigin>
     */
    private function parseIncludeOption(mixed $rawInclude, SymfonyStyle $io): array
    {
        if (!is_string($rawInclude) || trim($rawInclude) === '') {
            return [];
        }

        $tokens = array_filter(array_map(trim(...), explode(',', $rawInclude)), static fn (string $t): bool => $t !== '');

        if (in_array('all', $tokens, true)) {
            return SrcOrigin::defaultSkipSet();
        }

        $origins  = [];
        $skipSet  = SrcOrigin::defaultSkipSet();
        $knownMap = [];
        foreach ($skipSet as $origin) {
            $knownMap[$origin->value] = $origin;
        }

        foreach ($tokens as $token) {
            if (isset($knownMap[$token])) {
                $origins[] = $knownMap[$token];
                continue;
            }

            $io->warning(sprintf(
                'Unknown --include value "%s". Allowed: %s, all.',
                $token,
                implode(', ', array_keys($knownMap)),
            ));
        }

        return array_values(array_unique($origins, SORT_REGULAR));
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
                $this->truncate($issue->currentSrc, 50),
                $this->truncate($issue->expectedSrc, 50),
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
