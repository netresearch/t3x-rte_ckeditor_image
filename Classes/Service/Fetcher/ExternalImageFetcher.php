<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Fetcher;

use Netresearch\RteCKEditorImage\Service\Security\SecurityValidatorInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Service for fetching external images with security validation.
 *
 * Handles:
 * - Fetching images from external URLs
 * - SSRF protection through IP validation
 * - MIME type validation of fetched content
 * - Error handling and logging
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final readonly class ExternalImageFetcher
{
    /**
     * Default timeout for external requests in seconds.
     */
    private const DEFAULT_TIMEOUT = 10;

    /**
     * Maximum content length to fetch (10MB).
     */
    private const MAX_CONTENT_LENGTH = 10 * 1024 * 1024;

    public function __construct(
        private SecurityValidatorInterface $securityValidator,
        private RequestFactory $requestFactory,
        private LoggerInterface $logger,
    ) {}

    /**
     * Fetch an external image with security validation.
     *
     * Validates the URL for SSRF attacks, fetches the content,
     * and validates the MIME type before returning.
     *
     * @param string $url The external image URL
     *
     * @return string|null The image content or null on failure
     */
    public function fetch(string $url): ?string
    {
        if (trim($url) === '') {
            return null;
        }

        // Validate URL and get safe IP for request
        $validatedIp = $this->securityValidator->getValidatedIpForUrl($url);
        if ($validatedIp === null) {
            $this->logger->warning('External image URL failed security validation', [
                'url' => $this->sanitizeUrlForLog($url),
            ]);

            return null;
        }

        try {
            $content = $this->fetchWithValidatedIp($url, $validatedIp);
            if ($content === null) {
                return null;
            }

            // Validate MIME type of fetched content
            if (!$this->securityValidator->isAllowedImageMimeType($content)) {
                $this->logger->warning('External image has invalid MIME type', [
                    'url' => $this->sanitizeUrlForLog($url),
                ]);

                return null;
            }

            return $content;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to fetch external image', [
                'url'       => $this->sanitizeUrlForLog($url),
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if a URL is an external URL (not a data URI or local path).
     *
     * @param string $url The URL to check
     *
     * @return bool True if the URL is external
     */
    public function isExternalUrl(string $url): bool
    {
        $trimmedUrl = trim($url);

        if ($trimmedUrl === '') {
            return false;
        }

        // Data URIs are not external
        if (str_starts_with($trimmedUrl, 'data:')) {
            return false;
        }

        // Check for HTTP(S) URLs
        return str_starts_with($trimmedUrl, 'http://') || str_starts_with($trimmedUrl, 'https://');
    }

    /**
     * Fetch content from URL using validated IP to prevent DNS rebinding.
     *
     * @param string $url         The original URL
     * @param string $validatedIp The validated IP address
     *
     * @return string|null The fetched content or null on failure
     */
    private function fetchWithValidatedIp(string $url, string $validatedIp): ?string
    {
        $parsedUrl = parse_url($url);
        if (!is_array($parsedUrl) || !isset($parsedUrl['host'])) {
            return null;
        }

        // Build URL with validated IP to prevent DNS rebinding
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $port   = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $path   = $parsedUrl['path'] ?? '';
        $query  = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';

        $ipUrl = $scheme . '://' . $validatedIp . $port . $path . $query;

        $response = $this->requestFactory->request($ipUrl, 'GET', [
            'headers' => [
                'Host' => $parsedUrl['host'],
            ],
            'timeout'         => self::DEFAULT_TIMEOUT,
            'allow_redirects' => false,
            'verify'          => true,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->logger->info('External image request returned non-success status', [
                'url'        => $this->sanitizeUrlForLog($url),
                'statusCode' => $statusCode,
            ]);

            return null;
        }

        $content = $response->getBody()->getContents();

        // Check content length
        if (strlen($content) > self::MAX_CONTENT_LENGTH) {
            $this->logger->warning('External image exceeds maximum size', [
                'url'  => $this->sanitizeUrlForLog($url),
                'size' => strlen($content),
            ]);

            return null;
        }

        return $content;
    }

    /**
     * Sanitize URL for safe logging (remove credentials).
     *
     * @param string $url The URL to sanitize
     *
     * @return string The sanitized URL
     */
    private function sanitizeUrlForLog(string $url): string
    {
        $parsed = parse_url($url);

        if (!is_array($parsed)) {
            return '[invalid URL]';
        }

        // Remove user/pass from URL for logging
        unset($parsed['user'], $parsed['pass']);

        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host   = $parsed['host'] ?? '';
        $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path   = $parsed['path'] ?? '';
        $query  = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        return $scheme . $host . $port . $path . $query;
    }
}
