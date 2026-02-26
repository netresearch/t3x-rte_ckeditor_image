<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Dto;

/**
 * A single image reference issue found during validation.
 */
final readonly class ValidationIssue
{
    public function __construct(
        public ValidationIssueType $type,
        public string $table,
        public int $uid,
        public string $field,
        public ?int $fileUid,
        public ?string $currentSrc,
        public ?string $expectedSrc,
        public int $imgIndex,
    ) {}

    /**
     * Whether this issue can be automatically fixed.
     *
     * MissingFileUid cannot be fixed because there is no way to determine
     * which FAL file the image should reference.
     */
    public function isFixable(): bool
    {
        return $this->type !== ValidationIssueType::MissingFileUid;
    }
}
