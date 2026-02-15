<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Dto;

/**
 * Collection of validation issues with summary statistics.
 */
final class ValidationResult
{
    /** @var list<ValidationIssue> */
    private array $issues = [];

    private int $scannedRecords = 0;

    private int $scannedImages = 0;

    /** @var array<string, true> table:uid keys of affected records */
    private array $affectedRecordKeys = [];

    public function addIssue(ValidationIssue $issue): void
    {
        $this->issues[]                                              = $issue;
        $this->affectedRecordKeys[$issue->table . ':' . $issue->uid] = true;
    }

    public function incrementScannedRecords(): void
    {
        ++$this->scannedRecords;
    }

    public function incrementScannedImages(): void
    {
        ++$this->scannedImages;
    }

    public function hasIssues(): bool
    {
        return $this->issues !== [];
    }

    /**
     * @return list<ValidationIssue>
     */
    public function getIssues(): array
    {
        return $this->issues;
    }

    /**
     * @return list<ValidationIssue>
     */
    public function getFixableIssues(): array
    {
        return array_values(
            array_filter(
                $this->issues,
                static fn (ValidationIssue $issue): bool => $issue->isFixable(),
            ),
        );
    }

    public function getScannedRecords(): int
    {
        return $this->scannedRecords;
    }

    public function getScannedImages(): int
    {
        return $this->scannedImages;
    }

    public function getAffectedRecords(): int
    {
        return count($this->affectedRecordKeys);
    }
}
