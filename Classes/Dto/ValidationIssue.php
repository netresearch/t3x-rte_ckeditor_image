<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
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
