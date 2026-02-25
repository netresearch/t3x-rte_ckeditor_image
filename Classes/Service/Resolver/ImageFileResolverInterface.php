<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Resolver;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;

/**
 * Interface for resolving and processing image files.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
interface ImageFileResolverInterface
{
    /**
     * Resolve a file UID to a File object.
     *
     * @param int $fileUid The file UID
     *
     * @return File|null The File object or null if not found
     */
    public function resolveByUid(int $fileUid): ?File;

    /**
     * Resolve a local file path to a File object.
     *
     * @param string $path The file path
     *
     * @return File|null The File object or null if not found
     */
    public function resolveByPath(string $path): ?File;

    /**
     * Process an image file (resize/crop).
     *
     * @param File                $file              The file to process
     * @param int                 $width             The target width
     * @param int                 $height            The target height
     * @param array<string,mixed> $processingOptions Additional processing options
     *
     * @return ProcessedFile|null The processed file or null on failure
     */
    public function processImage(
        File $file,
        int $width,
        int $height,
        array $processingOptions = [],
    ): ?ProcessedFile;

    /**
     * Import an external image.
     *
     * @param string $url          The external URL
     * @param string $targetFolder The target folder identifier
     *
     * @return File|null The imported file or null on failure
     */
    public function importExternalImage(string $url, string $targetFolder = '1:/_temp_/'): ?File;

    /**
     * Check if a file exists.
     *
     * @param int $fileUid The file UID
     *
     * @return bool True if file exists
     */
    public function fileExists(int $fileUid): bool;
}
