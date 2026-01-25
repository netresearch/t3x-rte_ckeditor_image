<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Security;

use finfo;

/**
 * Security validation service for image operations.
 *
 * Consolidates all security-related validation logic:
 * - SSRF protection for external URLs
 * - MIME type validation for uploaded content
 * - Path traversal protection for local files
 * - File extension validation
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class SecurityValidator implements SecurityValidatorInterface
{
    /**
     * Blocked cloud metadata endpoints (SSRF protection).
     *
     * @var string[]
     */
    private const BLOCKED_HOSTS = [
        '169.254.169.254',
        'metadata.google.internal',
        'instance-data',
    ];

    /**
     * Allowed MIME types for external images.
     *
     * Note: SVG is intentionally excluded - SVG sanitization is TYPO3 Core/FAL
     * responsibility per ADR-003 Security Responsibility Boundaries.
     *
     * @var string[]
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Allowed file extensions for external images.
     *
     * @var string[]
     */
    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'gif',
        'png',
        'webp',
    ];

    /**
     * Validate URL for external fetch and return safe IP.
     *
     * Implements SSRF protection by:
     * - Validating URL protocol (http/https only)
     * - Resolving hostname to IP address
     * - Normalizing IP addresses to canonical form
     * - Blocking private/reserved IP ranges
     * - Blocking IPv6 loopback and IPv4-mapped addresses
     * - Blocking cloud metadata endpoints
     *
     * Returns the validated IP address to prevent DNS rebinding attacks.
     *
     * @param string $url The URL to validate
     *
     * @return string|null The validated IP address or null if validation fails
     */
    public function getValidatedIpForUrl(string $url): ?string
    {
        $parsedUrl = parse_url($url);
        if (!is_array($parsedUrl) || !isset($parsedUrl['host'])) {
            return null;
        }

        // Only allow http/https protocols
        $scheme = strtolower($parsedUrl['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $host = $parsedUrl['host'];

        // Resolve hostname to IP address
        $ip = gethostbyname($host);
        if ($ip === $host) {
            // DNS resolution failed or is already an IP
            $ip = $host;
        }

        // Normalize IP to canonical form using inet_pton/inet_ntop
        // This handles octal, hexadecimal, and decimal IP representations
        $normalizedIp = $this->normalizeIpAddress($ip);
        if ($normalizedIp === null) {
            return null;
        }

        // Check for IPv6 loopback and IPv4-mapped addresses
        if ($this->isBlockedIpv6Address($normalizedIp)) {
            return null;
        }

        // Validate IP is not in private/reserved ranges
        if (filter_var($normalizedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            // Block private IPv4 ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)
            // Block loopback (127.0.0.0/8)
            // Block link-local (169.254.0.0/16)
            if (filter_var($normalizedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        } elseif (filter_var($normalizedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            // Block private IPv6 ranges
            if (filter_var($normalizedIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        } else {
            // Invalid IP format
            return null;
        }

        // Additional check: block cloud metadata endpoints
        foreach (self::BLOCKED_HOSTS as $blockedHost) {
            if (stripos($host, $blockedHost) !== false || stripos($normalizedIp, $blockedHost) !== false) {
                return null;
            }
        }

        return $normalizedIp;
    }

    /**
     * Normalize IP address to canonical form.
     *
     * Handles octal, hexadecimal, and decimal representations.
     *
     * @param string $ip The IP address to normalize
     *
     * @return string|null The normalized IP or null if invalid
     */
    private function normalizeIpAddress(string $ip): ?string
    {
        // Remove brackets from IPv6 addresses
        $ip = trim($ip, '[]');

        // Try to convert to binary and back to get canonical form
        $binary = @inet_pton($ip);
        if ($binary === false) {
            return null;
        }

        $normalized = inet_ntop($binary);

        return $normalized !== false ? $normalized : null;
    }

    /**
     * Check if IPv6 address is a blocked address.
     *
     * Blocks:
     * - IPv6 loopback (::1)
     * - IPv4-mapped IPv6 addresses (::ffff:x.x.x.x)
     *
     * @param string $ip The normalized IP address
     *
     * @return bool True if the address is blocked
     */
    private function isBlockedIpv6Address(string $ip): bool
    {
        // IPv6 loopback
        if ($ip === '::1') {
            return true;
        }

        // IPv4-mapped IPv6 addresses (::ffff:127.0.0.1, etc.)
        if (str_starts_with(strtolower($ip), '::ffff:')) {
            // Extract the IPv4 portion and validate it
            $ipv4 = substr($ip, 7);
            if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate file content MIME type.
     *
     * Uses finfo to detect MIME type from content (not extension).
     *
     * @param string $content The file content to validate
     *
     * @return bool True if MIME type is allowed
     */
    public function isAllowedImageMimeType(string $content): bool
    {
        if ($content === '') {
            return false;
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content);

        return in_array($mimeType, self::ALLOWED_MIME_TYPES, true);
    }

    /**
     * Validate local file path is within allowed directory.
     *
     * Prevents directory traversal attacks by:
     * - Sanitizing path (removing ../, ..\, null bytes)
     * - Resolving realpath
     * - Verifying path is within public directory
     *
     * @param string $path       The path to validate
     * @param string $publicPath The allowed public path root
     *
     * @return string|null The validated realpath or null if validation fails
     */
    public function validateLocalPath(string $path, string $publicPath): ?string
    {
        if ($path === '' || $publicPath === '') {
            return null;
        }

        // Sanitize path to prevent directory traversal
        $sanitizedPath = str_replace(['../', '..\\', "\0"], '', $path);

        // Build absolute path
        $absolutePath = rtrim($publicPath, '/') . '/' . ltrim($sanitizedPath, '/');

        $realpath = realpath($absolutePath);
        if ($realpath === false) {
            return null;
        }

        // Ensure realpath is within public path
        if (!str_starts_with($realpath, $publicPath)) {
            return null;
        }

        return $realpath;
    }

    /**
     * Validate file extension is in allowed list.
     *
     * @param string $extension The file extension (without dot)
     *
     * @return bool True if extension is allowed
     */
    public function isAllowedExtension(string $extension): bool
    {
        return in_array(strtolower($extension), self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * Get allowed MIME types.
     *
     * @return string[]
     */
    public function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    /**
     * Get allowed extensions.
     *
     * @return string[]
     */
    public function getAllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }
}
