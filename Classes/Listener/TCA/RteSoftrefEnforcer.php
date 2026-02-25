<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Listener\TCA;

use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Event listener that automatically adds rtehtmlarea_images soft reference
 * to all RTE-enabled text fields across all tables.
 *
 * This ensures images inserted via CKEditor are properly tracked in TYPO3's
 * reference index, preventing data loss when records are moved, copied, or deleted.
 *
 * Configuration options (via Extension Configuration):
 * - enableAutomaticRteSoftref: Primary toggle to enable/disable automatic processing
 * - includedTablesOnly: Inclusion list mode - only process specified tables
 * - excludedTables: Exclusion list mode - exclude specified tables from processing
 *
 * @author Sebastian Mendel <sebastian.mendel@netresearch.de>
 */
final readonly class RteSoftrefEnforcer
{
    /**
     * Extension configuration from $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['rte_ckeditor_image'].
     *
     * @var array<string, mixed>
     */
    private array $extensionConfiguration;

    public function __construct(ExtensionConfiguration $extensionConfiguration)
    {
        $config = $extensionConfiguration->get('rte_ckeditor_image');
        /** @var array<string, mixed> $config */
        $this->extensionConfiguration = is_array($config) ? $config : [];
    }

    /**
     * Process TCA after compilation to add rtehtmlarea_images softref to all RTE fields.
     *
     * Triggered by AfterTcaCompilationEvent with priority 'after: *' to ensure
     * all other TCA modifications have been applied first.
     */
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        // Check if automatic processing is enabled
        if (!$this->isAutomaticProcessingEnabled()) {
            return;
        }

        $tca = $event->getTca();

        // Parse configuration
        $includedTablesOnly = $this->parseTableList(
            $this->extensionConfiguration['includedTablesOnly'] ?? '',
        );
        $excludedTables = $this->parseTableList(
            $this->extensionConfiguration['excludedTables'] ?? '',
        );

        // Process each table
        foreach ($tca as $tableName => &$tableConfig) {
            // Apply inclusion list filter (if configured)
            if ($includedTablesOnly !== [] && !in_array($tableName, $includedTablesOnly, true)) {
                continue;
            }

            // Apply exclusion list filter (only if inclusion list is not configured)
            if ($includedTablesOnly === [] && in_array($tableName, $excludedTables, true)) {
                continue;
            }

            // Process each column in the table
            if (!isset($tableConfig['columns'])) {
                continue;
            }

            if (!is_array($tableConfig['columns'])) {
                continue;
            }

            foreach ($tableConfig['columns'] as &$columnConfig) {
                if ($this->isRteEnabledTextField($columnConfig)) {
                    $this->addRteSoftref($columnConfig);
                }
            }
        }

        $event->setTca($tca);
    }

    /**
     * Check if automatic RTE softref processing is enabled.
     */
    private function isAutomaticProcessingEnabled(): bool
    {
        return (bool) ($this->extensionConfiguration['enableAutomaticRteSoftref'] ?? true);
    }

    /**
     * Parse comma-separated table list into array.
     *
     * @return string[]
     */
    private function parseTableList(string $tableList): array
    {
        if (trim($tableList) === '') {
            return [];
        }

        return array_filter(
            array_map(trim(...), explode(',', $tableList)),
            static fn (string $tableName): bool => $tableName !== '',
        );
    }

    /**
     * Check if a field is an RTE-enabled text field.
     *
     * @param array<string, mixed> $columnConfig
     */
    private function isRteEnabledTextField(array $columnConfig): bool
    {
        $config = $columnConfig['config'] ?? [];

        // Must be a text field
        if (($config['type'] ?? '') !== 'text') {
            return false;
        }

        // Must have RTE enabled
        return !empty($config['enableRichtext']);
    }

    /**
     * Add rtehtmlarea_images to the softref configuration of a field.
     *
     * @param array<string, mixed> $columnConfig
     */
    private function addRteSoftref(array &$columnConfig): void
    {
        $config = &$columnConfig['config'];

        // Get existing softref configuration
        $softrefString = (string) ($config['softref'] ?? '');

        // Parse into array
        $softrefs = array_filter(
            array_map(trim(...), explode(',', $softrefString)),
            static fn (string $ref): bool => $ref !== '',
        );

        // Remove obsolete 'images' reference
        $softrefs = array_diff($softrefs, ['images']);

        // Add rtehtmlarea_images if not already present
        if (!in_array('rtehtmlarea_images', $softrefs, true)) {
            $softrefs[] = 'rtehtmlarea_images';
        }

        // Update configuration
        $config['softref'] = implode(',', $softrefs);
    }
}
