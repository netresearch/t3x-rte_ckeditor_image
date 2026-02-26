<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Database;

use Netresearch\RteCKEditorImage\Service\Processor\RteImageProcessorInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\ArrayUtility;

/**
 * DataHandler hook for processing images in RTE fields.
 *
 * Intercepts RTE field saves to process img tags:
 * - Resolves file UIDs for existing images
 * - Fetches and imports external images (if enabled)
 * - Processes images to requested dimensions
 *
 * @author  Stefan Galinski <stefan@sgalinski.de>
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class RteImagesDbHook
{
    public function __construct(
        private readonly RteImageProcessorInterface $imageProcessor,
    ) {}

    /**
     * Process the modified text from TCA text field before its stored in the database.
     *
     * @param string               $status      The status (new/update)
     * @param string               $table       The table name
     * @param string               $id          The record ID
     * @param array<string, mixed> $fieldArray  The field values (by reference)
     * @param DataHandler          $dataHandler The DataHandler instance
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        string $id,
        array &$fieldArray,
        DataHandler &$dataHandler,
    ): void {
        foreach ($fieldArray as $field => $fieldValue) {
            // Skip fields not defined in TCA
            if (!isset($GLOBALS['TCA'][$table]['columns'][$field])) {
                continue;
            }

            // Get TCA configuration for the field
            $tcaFieldConf = $this->resolveFieldConfiguration($dataHandler, $table, $field);

            // Only process text fields with RTE enabled
            if (!$this->isRteTextField($tcaFieldConf)) {
                continue;
            }

            // Skip null values
            if ($fieldValue === null) {
                continue;
            }

            // Process the RTE field content
            $fieldArray[$field] = $this->imageProcessor->process((string) $fieldValue);
        }
    }

    /**
     * Check if TCA field configuration is for an RTE text field.
     *
     * @param array<string, mixed> $tcaFieldConf The TCA field configuration
     *
     * @return bool True if RTE text field
     */
    private function isRteTextField(array $tcaFieldConf): bool
    {
        if (!isset($tcaFieldConf['type'])) {
            return false;
        }

        if ($tcaFieldConf['type'] !== 'text') {
            return false;
        }

        if (!isset($tcaFieldConf['enableRichtext'])) {
            return false;
        }

        return $tcaFieldConf['enableRichtext'] === true;
    }

    /**
     * Resolve TCA field configuration respecting columnsOverrides.
     *
     * @param DataHandler $dataHandler The DataHandler instance
     * @param string      $table       The table name
     * @param string      $field       The field name
     *
     * @return array<string, mixed> The resolved TCA field configuration
     */
    private function resolveFieldConfiguration(
        DataHandler $dataHandler,
        string $table,
        string $field,
    ): array {
        $tcaFieldConf = $GLOBALS['TCA'][$table]['columns'][$field]['config'] ?? [];

        if (!is_array($tcaFieldConf)) {
            return [];
        }

        $recordType = BackendUtility::getTCAtypeValue($table, $dataHandler->checkValue_currentRecord);

        $columnsOverrides = $GLOBALS['TCA'][$table]['types'][$recordType]['columnsOverrides'][$field]['config'] ?? null;

        if (is_array($columnsOverrides)) {
            ArrayUtility::mergeRecursiveWithOverrule($tcaFieldConf, $columnsOverrides);
        }

        return $tcaFieldConf;
    }
}
