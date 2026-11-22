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
            if (
                !$this->isTableSelected($tableName, $includedTablesOnly, $excludedTables)
                || !is_array($tableConfig)
                || !is_array($tableConfig['types'] ?? null)
            ) {
                continue;
            }

            foreach ($tableConfig['types'] as &$typeConfig) {
                if (is_array($typeConfig) && $this->needsPreviewRenderer($typeConfig, $tableConfig)) {
                    $typeConfig['previewRenderer'] = RteImagePreviewRenderer::class;
                }
            }
        }

        $event->setTca($tca);
    }

    /**
     * Check the table against the inclusion list or, when no inclusion list is
     * configured, against the exclusion list.
     *
     * @param string[] $includedTablesOnly
     * @param string[] $excludedTables
     */
    private function isTableSelected(int|string $tableName, array $includedTablesOnly, array $excludedTables): bool
    {
        if ($includedTablesOnly !== []) {
            return in_array($tableName, $includedTablesOnly, true);
        }

        return !in_array($tableName, $excludedTables, true);
    }

    /**
     * Check if a type should get RteImagePreviewRenderer.
     *
     * Types that have FILE-type columns in their showitem are skipped:
     * StandardContentPreviewRenderer renders file field thumbnails (e.g.
     * textpic's image, textmedia's assets) that our renderer does not handle.
     * Overriding would lose those thumbnails (#720). A custom preview renderer
     * that is already set is never replaced.
     *
     * @param array<mixed> $typeConfig
     * @param array<mixed> $tableConfig
     */
    private function needsPreviewRenderer(array $typeConfig, array $tableConfig): bool
    {
        return $this->hasRteEnabledBodytext($typeConfig, $tableConfig)
            && !$this->hasFileFieldsInShowitem($typeConfig, $tableConfig)
            && ($typeConfig['previewRenderer'] ?? '') === '';
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
        $overrideConfig = $this->getBodytextConfig($typeConfig['columnsOverrides'] ?? null);

        if (isset($overrideConfig['enableRichtext'])) {
            return (bool) $overrideConfig['enableRichtext'];
        }

        // Fall back to base column definition
        $baseConfig = $this->getBodytextConfig($tableConfig['columns'] ?? null);

        return (bool) ($baseConfig['enableRichtext'] ?? false);
    }

    /**
     * Return the config array of the bodytext column in a columns or
     * columnsOverrides structure, or an empty array when there is none.
     *
     * @return array<mixed>
     */
    private function getBodytextConfig(mixed $columns): array
    {
        $bodytext = is_array($columns) ? ($columns['bodytext'] ?? null) : null;
        $config   = is_array($bodytext) ? ($bodytext['config'] ?? null) : null;

        return is_array($config) ? $config : [];
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
        $columns  = $tableConfig['columns'] ?? null;

        if (!is_string($showitem) || $showitem === '' || !is_array($columns)) {
            return false;
        }

        // Only check direct field references — palette contents are intentionally
        // ignored so that shared decorative fields (e.g. background_image in the
        // "frames" palette) do not prevent registration.
        foreach (explode(',', $showitem) as $part) {
            $fieldName = trim(explode(';', trim($part))[0]);
            if ($fieldName === '') {
                continue;
            }

            if (str_starts_with($fieldName, '--')) {
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
