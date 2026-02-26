<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Updates;

use Netresearch\RteCKEditorImage\Service\RteImageReferenceValidator;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Upgrade wizard that validates and fixes RTE image references.
 *
 * Detects and repairs:
 * - Processed image URLs (_processed_) that won't survive migration
 * - Stale src attributes after file moves/renames
 * - Orphaned file UIDs referencing deleted FAL files
 * - Broken/empty src attributes
 *
 * Repeatable: Can be run multiple times safely.
 *
 * Uses Install\Updates namespace for v13 compatibility (deprecated in v14,
 * see Deprecation-106947). Migrate to Core\Upgrades when dropping v13 support.
 */
#[UpgradeWizard('rteImageReferenceValidation')]
final readonly class ValidateRteImageReferencesWizard implements UpgradeWizardInterface, RepeatableInterface
{
    public function __construct(
        private RteImageReferenceValidator $validator,
    ) {}

    public function getTitle(): string
    {
        return 'Validate RTE image references';
    }

    public function getDescription(): string
    {
        return 'Scans RTE content fields for broken image references (stale src attributes, '
            . 'processed image URLs, orphaned file UIDs) and fixes them by resolving the '
            . 'current public URL from FAL.';
    }

    public function updateNecessary(): bool
    {
        return $this->validator->validate()->hasIssues();
    }

    public function executeUpdate(): bool
    {
        $result = $this->validator->validate();

        if (!$result->hasIssues()) {
            return true;
        }

        $this->validator->fix($result);

        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }
}
