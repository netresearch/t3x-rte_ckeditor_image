<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Listener\TCA;

use Netresearch\RteCKEditorImage\Backend\Preview\RteImagePreviewRenderer;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Event listener that automatically registers RteImagePreviewRenderer
 * for all CTypes with RTE-enabled bodytext fields.
 *
 * This ensures images inserted via CKEditor are visible in the TYPO3 page module
 * preview, regardless of CType. Without this, CTypes like textmedia and textpic
 * use StandardContentPreviewRenderer which strips <img> tags via strip_tags().
 *
 * Configuration options (via Extension Configuration):
 * - enableAutomaticPreviewRenderer: Primary toggle to enable/disable automatic processing
 * - includedTablesOnly: Inclusion list mode - only process specified tables
 * - excludedTables: Exclusion list mode - exclude specified tables from processing
 *
 * @see RteSoftrefEnforcer for the same pattern applied to softref enforcement
 */
final readonly class RtePreviewRendererRegistrar
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
     * Process TCA after compilation to register RteImagePreviewRenderer
     * for all types with RTE-enabled bodytext.
     *
     * Triggered by AfterTcaCompilationEvent with priority 'after: *' to ensure
     * all other TCA modifications have been applied first.
     */
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        if (!$this->isAutomaticProcessingEnabled()) {
            return;
        }

        $tca = $event->getTca();

        $includedTablesOnlyRaw = $this->extensionConfiguration['includedTablesOnly'] ?? '';
        $excludedTablesRaw     = $this->extensionConfiguration['excludedTables'] ?? '';

        $includedTablesOnly = $this->parseTableList(
            is_string($includedTablesOnlyRaw) ? $includedTablesOnlyRaw : '',
        );
        $excludedTables = $this->parseTableList(
            is_string($excludedTablesRaw) ? $excludedTablesRaw : '',
        );

        foreach ($tca as $tableName => &$tableConfig) {
            // Apply inclusion list filter (if configured)
            if ($includedTablesOnly !== [] && !in_array($tableName, $includedTablesOnly, true)) {
                continue;
            }

            // Apply exclusion list filter (only if inclusion list is not configured)
            if ($includedTablesOnly === [] && in_array($tableName, $excludedTables, true)) {
                continue;
            }

            if (!is_array($tableConfig)) {
                continue;
            }

            if (!isset($tableConfig['types'])) {
                continue;
            }

            if (!is_array($tableConfig['types'])) {
                continue;
            }

            foreach ($tableConfig['types'] as &$typeConfig) {
                if (!is_array($typeConfig)) {
                    continue;
                }

                if (!$this->hasRteEnabledBodytext($typeConfig, $tableConfig)) {
                    continue;
                }

                // Skip types that have FILE-type columns in their showitem.
                // StandardContentPreviewRenderer renders file field thumbnails
                // (e.g. textpic's image, textmedia's assets) that our renderer
                // does not handle. Overriding would lose those thumbnails (#720).
                if ($this->hasFileFieldsInShowitem($typeConfig, $tableConfig)) {
                    continue;
                }

                // Only register if no custom preview renderer is already set
                if (isset($typeConfig['previewRenderer']) && $typeConfig['previewRenderer'] !== '') {
                    continue;
                }

                $typeConfig['previewRenderer'] = RteImagePreviewRenderer::class;
            }
        }

        $event->setTca($tca);
    }

    /**
     * Check if automatic preview renderer registration is enabled.
     */
    private function isAutomaticProcessingEnabled(): bool
    {
        return (bool) ($this->extensionConfiguration['enableAutomaticPreviewRenderer'] ?? true);
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
     * Check if a type has RTE-enabled bodytext (via columnsOverrides or base column).
     *
     * Accepts untyped arrays because TCA structures are deeply nested mixed types.
     *
     * @param array<mixed> $typeConfig
     * @param array<mixed> $tableConfig
     */
    private function hasRteEnabledBodytext(array $typeConfig, array $tableConfig): bool
    {
        // Check columnsOverrides first (higher priority)
        $columnsOverrides = $typeConfig['columnsOverrides'] ?? null;

        if (is_array($columnsOverrides)) {
            $bodytextOverride = $columnsOverrides['bodytext'] ?? null;

            if (is_array($bodytextOverride)) {
                $config = $bodytextOverride['config'] ?? null;

                if (is_array($config) && isset($config['enableRichtext'])) {
                    return (bool) $config['enableRichtext'];
                }
            }
        }

        // Fall back to base column definition
        $columns = $tableConfig['columns'] ?? null;

        if (!is_array($columns)) {
            return false;
        }

        $bodytext = $columns['bodytext'] ?? null;

        if (!is_array($bodytext)) {
            return false;
        }

        $config = $bodytext['config'] ?? null;

        if (!is_array($config)) {
            return false;
        }

        return (bool) ($config['enableRichtext'] ?? false);
    }

    /**
     * Check if a type's showitem directly references any columns with TCA type "file".
     *
     * Types with file fields (e.g. textpic with "image", textmedia with "assets")
     * need StandardContentPreviewRenderer to render file thumbnails. Our renderer
     * only handles bodytext and would lose those thumbnails.
     *
     * Only checks direct (top-level) field references in showitem, not fields
     * inherited through palette references. Primary content file fields (image,
     * assets) are always direct fields, while decorative file fields (e.g.
     * bootstrap_package's background_image) are in shared palettes like "frames".
     *
     * @param array<mixed> $typeConfig
     * @param array<mixed> $tableConfig
     */
    private function hasFileFieldsInShowitem(array $typeConfig, array $tableConfig): bool
    {
        $showitem = $typeConfig['showitem'] ?? '';

        if (!is_string($showitem) || $showitem === '') {
            return false;
        }

        $columns = $tableConfig['columns'] ?? null;

        if (!is_array($columns)) {
            return false;
        }

        // Only check direct field references (skip palettes, tabs, and markers)
        foreach (explode(',', $showitem) as $part) {
            $fieldName = trim(explode(';', trim($part))[0]);

            if ($fieldName === '' || str_starts_with($fieldName, '--')) {
                continue;
            }

            $columnConfig = $columns[$fieldName] ?? null;

            if (!is_array($columnConfig)) {
                continue;
            }

            $config = $columnConfig['config'] ?? null;

            if (is_array($config) && ($config['type'] ?? null) === 'file') {
                return true;
            }
        }

        return false;
    }
}
