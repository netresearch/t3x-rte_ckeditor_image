<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service;

use Netresearch\RteCKEditorImage\Dto\SrcOrigin;

/**
 * Classifies an <img src=""> value into a {@see SrcOrigin}.
 *
 * Pure, stateless, no HTTP — pattern-matching only. The validator uses this
 * to filter out references it cannot fix (external URLs, data URIs, legacy
 * paths, secure-download URLs) from its "missing file uid" report.
 */
final class SrcOriginClassifier
{
    public function classify(?string $src): SrcOrigin
    {
        if ($src === null || trim($src) === '') {
            return SrcOrigin::Unknown;
        }

        $trimmed = ltrim($src);

        if (str_starts_with($trimmed, 'data:')) {
            return SrcOrigin::DataUri;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return SrcOrigin::ExternalUrl;
        }

        // Legacy extension paths are commonly stored without a leading slash
        // (typo3conf/ext/...) or with one (/typo3conf/ext/...).
        if (preg_match('#^/?typo3conf/ext/#', $trimmed) === 1) {
            return SrcOrigin::LegacyExtensionPath;
        }

        // Secure downloads may appear mid-path (e.g. /fileadmin/securedl/...).
        if (str_contains($trimmed, '/securedl/')) {
            return SrcOrigin::SecureDownload;
        }

        if (str_contains($trimmed, '/_processed_/')) {
            return SrcOrigin::ProcessedVariant;
        }

        if (str_starts_with($trimmed, '/') || str_starts_with($trimmed, 'fileadmin/')) {
            return SrcOrigin::LocalFal;
        }

        return SrcOrigin::Unknown;
    }
}
