<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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
}
