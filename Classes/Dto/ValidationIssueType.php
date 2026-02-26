<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Dto;

/**
 * Types of image reference issues found in RTE content.
 */
enum ValidationIssueType: string
{
    /** src points to a _processed_ URL that may not survive migration */
    case ProcessedImageSrc = 'processed_image_src';

    /** src file path does not match the FAL file's current public URL */
    case SrcMismatch = 'src_mismatch';

    /** img tag is missing the data-htmlarea-file-uid attribute entirely */
    case MissingFileUid = 'missing_file_uid';

    /** data-htmlarea-file-uid references a FAL file that no longer exists */
    case OrphanedFileUid = 'orphaned_file_uid';

    /** src contains a broken/non-existent path (no FAL file can be resolved) */
    case BrokenSrc = 'broken_src';

    /** Image is wrapped in nested <a><a><img></a></a> tags (#667) */
    case NestedLinkWrapper = 'nested_link_wrapper';
}
