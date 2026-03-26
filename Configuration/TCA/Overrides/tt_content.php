<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\GeneralUtility;

defined('TYPO3') || exit;

/**
 * TCA override for tt_content table.
 *
 * tt_content is a core table; bodytext is a core field. The TCA structure
 * is guaranteed to exist at this point. @phpstan-var satisfies PHPStan level 10.
 */
call_user_func(static function (): void {
    /** @phpstan-var array{tt_content: array{columns: array{bodytext: array{config: array<string, mixed>}}}} $tca */
    $tca = &$GLOBALS['TCA'];

    $softrefStr = is_string($tca['tt_content']['columns']['bodytext']['config']['softref'] ?? null)
        ? $tca['tt_content']['columns']['bodytext']['config']['softref']
        : '';

    $cleanSoftReferences = GeneralUtility::trimExplode(',', $softrefStr, true);

    // Remove obsolete soft reference key 'images', the references from RTE content to the original
    // images are handled with the key 'rtehtmlarea_images'
    $cleanSoftReferences   = array_diff($cleanSoftReferences, ['images']);
    $cleanSoftReferences[] = 'rtehtmlarea_images';

    // Set up soft reference index parsing for RTE images
    $tca['tt_content']['columns']['bodytext']['config']['softref'] = implode(',', $cleanSoftReferences);

    // Preview renderer is now registered dynamically via RtePreviewRendererRegistrar
    // for ALL CTypes with RTE-enabled bodytext (not just CType "text").
    // See: Classes/Listener/TCA/RtePreviewRendererRegistrar.php
});
