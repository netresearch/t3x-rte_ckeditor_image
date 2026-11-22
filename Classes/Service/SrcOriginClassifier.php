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

        // The first matching arm wins, so the order of the arms matters.
        return match (true) {
            str_starts_with($trimmed, 'data:') => SrcOrigin::DataUri,
            // Absolute http(s) URLs and protocol-relative URLs (//cdn.example.com/…)
            // are all external references — no FAL mapping regardless of host.
            str_starts_with($trimmed, 'http://'),
            str_starts_with($trimmed, 'https://'),
            str_starts_with($trimmed, '//') => SrcOrigin::ExternalUrl,
            // Legacy extension paths are commonly stored without a leading slash
            // (typo3conf/ext/...) or with one (/typo3conf/ext/...).
            preg_match('#^/?typo3conf/ext/#', $trimmed) === 1 => SrcOrigin::LegacyExtensionPath,
            // Secure downloads may appear mid-path (e.g. /fileadmin/securedl/...).
            str_contains($trimmed, '/securedl/')    => SrcOrigin::SecureDownload,
            str_contains($trimmed, '/_processed_/') => SrcOrigin::ProcessedVariant,
            str_starts_with($trimmed, '/'),
            str_starts_with($trimmed, 'fileadmin/') => SrcOrigin::LocalFal,
            default                                 => SrcOrigin::Unknown,
        };
    }
}
