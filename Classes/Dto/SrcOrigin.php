<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Dto;

/**
 * Classification of an <img src=""> value encountered in RTE content.
 *
 * The validator uses this to filter out false positives for references the
 * command cannot fix — external URLs, inline data URIs, legacy extension
 * paths and secure-download URLs are all reported as "missing file uid"
 * under naive scanning but none of them are FAL-backed.
 */
enum SrcOrigin: string
{
    /** Absolute http/https URL — external to FAL regardless of host. */
    case ExternalUrl = 'external';

    /** Inline data: URI (data:image/jpeg;base64,…). */
    case DataUri = 'data';

    /** Legacy hardcoded path under typo3conf/ext/ (no FAL mapping possible). */
    case LegacyExtensionPath = 'legacy';

    /** /securedl/ TYPO3 secure download URL (not a plain FAL public URL). */
    case SecureDownload = 'securedl';

    /** _processed_ variant URL (resized/cropped output, not a canonical src). */
    case ProcessedVariant = 'processed';

    /** Site-root-relative or plausibly local FAL path. */
    case LocalFal = 'local';

    /** Missing/empty src or unrecognised form. */
    case Unknown = 'unknown';

    /**
     * Default skip set: categories the validator cannot meaningfully fix.
     *
     * @return list<self>
     */
    public static function defaultSkipSet(): array
    {
        return [
            self::ExternalUrl,
            self::DataUri,
            self::LegacyExtensionPath,
            self::SecureDownload,
        ];
    }
}
