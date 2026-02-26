<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Security;

/**
 * Interface for security validation service.
 *
 * Enables mocking in unit tests and allows alternative security implementations.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
interface SecurityValidatorInterface
{
    /**
     * Validate URL for external fetch and return safe IP.
     *
     * Implements SSRF protection by:
     * - Resolving hostname to IP address
     * - Blocking private/reserved IP ranges
     * - Blocking cloud metadata endpoints
     *
     * @param string $url The URL to validate
     *
     * @return string|null The validated IP address or null if validation fails
     */
    public function getValidatedIpForUrl(string $url): ?string;

    /**
     * Validate file content MIME type.
     *
     * Uses finfo to detect MIME type from content (not extension).
     *
     * @param string $content The file content to validate
     *
     * @return bool True if MIME type is allowed
     */
    public function isAllowedImageMimeType(string $content): bool;

    /**
     * Validate local file path is within allowed directory.
     *
     * @param string $path       The path to validate
     * @param string $publicPath The allowed public path root
     *
     * @return string|null The validated realpath or null if validation fails
     */
    public function validateLocalPath(string $path, string $publicPath): ?string;

    /**
     * Validate file extension is in allowed list.
     *
     * @param string $extension The file extension (without dot)
     *
     * @return bool True if extension is allowed
     */
    public function isAllowedExtension(string $extension): bool;

    /**
     * Get allowed MIME types.
     *
     * @return string[]
     */
    public function getAllowedMimeTypes(): array;

    /**
     * Get allowed extensions.
     *
     * @return string[]
     */
    public function getAllowedExtensions(): array;
}
