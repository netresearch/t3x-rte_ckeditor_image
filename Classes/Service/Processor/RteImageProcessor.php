<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Processor;

use Netresearch\RteCKEditorImage\Service\Builder\ImageTagBuilder;
use Netresearch\RteCKEditorImage\Service\Environment\EnvironmentInfoInterface;
use Netresearch\RteCKEditorImage\Service\Fetcher\ExternalImageFetcher;
use Netresearch\RteCKEditorImage\Service\Parser\ImageTagParser;
use Netresearch\RteCKEditorImage\Service\Resolver\ImageFileResolver;
use Netresearch\RteCKEditorImage\Service\Security\SecurityValidatorInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\FileProcessingAspect;
use TYPO3\CMS\Core\Resource\DefaultUploadFolderResolver;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;

/**
 * Main orchestrator for RTE image processing.
 *
 * Coordinates the processing of img tags in RTE content:
 * - Parses HTML to extract img tags
 * - Resolves files (by UID, path, or external URL)
 * - Processes images (resize, crop)
 * - Rebuilds img tags with updated attributes
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class RteImageProcessor implements RteImageProcessorInterface
{
    public function __construct(
        private readonly ImageTagParser $parser,
        private readonly ImageTagBuilder $builder,
        private readonly ImageFileResolver $fileResolver,
        private readonly ExternalImageFetcher $externalFetcher,
        private readonly EnvironmentInfoInterface $environmentInfo,
        private readonly SecurityValidatorInterface $securityValidator,
        private readonly Context $context,
        private readonly DefaultUploadFolderResolver $uploadFolderResolver,
        private readonly LoggerInterface $logger,
        private readonly bool $fetchExternalImages = false,
    ) {}

    /**
     * Process all img tags in HTML content.
     *
     * @param string $html The HTML content to process
     *
     * @return string The processed HTML content
     */
    public function process(string $html): string
    {
        // Skip processing in non-backend contexts
        if (!$this->environmentInfo->isBackendRequest()) {
            return $html;
        }

        $segments = $this->parser->splitByImageTags($html);

        if (count($segments) <= 1) {
            return $html;
        }

        $siteUrl  = $this->environmentInfo->getSiteUrl();
        $sitePath = $this->parser->calculateSitePath(
            $siteUrl,
            $this->environmentInfo->getRequestHost(),
        );

        foreach ($segments as $key => $segment) {
            // Odd indices contain img tags
            if (($key % 2) !== 1) {
                continue;
            }

            $processedTag = $this->processImageTag($segment, $siteUrl, $sitePath);
            if ($processedTag !== null) {
                $segments[$key] = $processedTag;
            }
        }

        return implode('', $segments);
    }

    /**
     * Process a single img tag.
     *
     * @param string $imgTag   The img tag string
     * @param string $siteUrl  The site URL
     * @param string $sitePath The site path
     *
     * @return string|null The processed img tag or null to keep original
     */
    private function processImageTag(string $imgTag, string $siteUrl, string $sitePath): ?string
    {
        $attributes = $this->parser->extractAttributes($imgTag);
        if (empty($attributes)) {
            return null;
        }

        $src = trim($attributes['src'] ?? '');
        if ($src === '') {
            return null;
        }

        // Normalize image source URL
        $absoluteUrl = $this->parser->normalizeImageSrc($src, $siteUrl, $sitePath);

        // Try to resolve existing file by UID
        $originalFile = $this->resolveOriginalFile($attributes);

        // Get dimensions from attributes or file
        $width  = $this->parser->getDimension($attributes, 'width');
        $height = $this->parser->getDimension($attributes, 'height');

        if ($width === 0 && $originalFile !== null) {
            $width = (int) $originalFile->getProperty('width');
        }

        if ($height === 0 && $originalFile !== null) {
            $height = (int) $originalFile->getProperty('height');
        }

        $attributes['width']  = $width;
        $attributes['height'] = $height;

        // Process based on file source
        if ($originalFile !== null) {
            $attributes = $this->processExistingFile($originalFile, $attributes, $absoluteUrl, $siteUrl);
        } elseif ($this->isExternalUrl($absoluteUrl, $siteUrl)) {
            $attributes = $this->processExternalImage($absoluteUrl, $attributes);
        } elseif ($this->isLocalUrl($absoluteUrl, $siteUrl)) {
            $attributes = $this->processLocalImage($absoluteUrl, $attributes, $siteUrl);
        }

        if (empty($attributes)) {
            return null;
        }

        // Convert absolute URL to relative
        $attributes['src'] = $this->builder->makeRelativeSrc((string) $attributes['src'], $siteUrl);

        return $this->builder->build($attributes);
    }

    /**
     * Resolve original file from attributes.
     *
     * @param array<string, mixed> $attributes The img tag attributes
     *
     * @return File|null The resolved file or null
     */
    private function resolveOriginalFile(array $attributes): ?File
    {
        if (!isset($attributes['data-htmlarea-file-uid'])) {
            return null;
        }

        $fileUid = (int) $attributes['data-htmlarea-file-uid'];

        return $this->fileResolver->resolveByUid($fileUid);
    }

    /**
     * Process an existing file (with UID).
     *
     * @param File                 $file        The file object
     * @param array<string, mixed> $attributes  The current attributes
     * @param string               $absoluteUrl The absolute image URL
     * @param string               $siteUrl     The site URL
     *
     * @return array<string, mixed> The updated attributes
     */
    private function processExistingFile(
        File $file,
        array $attributes,
        string $absoluteUrl,
        string $siteUrl,
    ): array {
        $imageFileUrl = rtrim($siteUrl, '/') . $file->getPublicUrl();

        // Check if image needs processing (dimensions changed)
        if ($absoluteUrl !== $imageFileUrl && $absoluteUrl !== $file->getPublicUrl()) {
            $width  = (int) $attributes['width'];
            $height = (int) $attributes['height'];

            if ($width > 0 && $height > 0) {
                // Ensure we get a processed file
                $this->context->setAspect('fileProcessing', new FileProcessingAspect(false));

                $processedFile = $this->fileResolver->processImage($file, $width, $height);

                if ($processedFile !== null) {
                    $imgSrc = $this->getProcessedFileUrl($file, $processedFile);

                    return $this->builder->withProcessedImage(
                        $attributes,
                        (int) $processedFile->getProperty('width'),
                        (int) $processedFile->getProperty('height'),
                        $imgSrc ?? '',
                    );
                }
            }
        }

        return $attributes;
    }

    /**
     * Get the public URL for a processed file.
     *
     * @param File          $originalFile  The original file
     * @param ProcessedFile $processedFile The processed file
     *
     * @return string|null The public URL
     */
    private function getProcessedFileUrl(File $originalFile, ProcessedFile $processedFile): ?string
    {
        $imgSrc = $processedFile->getPublicUrl();

        // Handle image processing URLs (process?token=...)
        if ($imgSrc !== null && str_contains($imgSrc, 'process?token=')) {
            $imgSrc = $originalFile->getStorage()->getPublicUrl($processedFile);
        }

        return $imgSrc;
    }

    /**
     * Check if URL is external (not from this site).
     *
     * @param string $url     The URL to check
     * @param string $siteUrl The site URL
     *
     * @return bool True if external
     */
    private function isExternalUrl(string $url, string $siteUrl): bool
    {
        if (!$this->externalFetcher->isExternalUrl($url)) {
            return false;
        }

        return !str_starts_with($url, $siteUrl);
    }

    /**
     * Check if URL is local (from this site).
     *
     * @param string $url     The URL to check
     * @param string $siteUrl The site URL
     *
     * @return bool True if local
     */
    private function isLocalUrl(string $url, string $siteUrl): bool
    {
        return str_starts_with($url, $siteUrl);
    }

    /**
     * Process an external image.
     *
     * @param string               $url        The external URL
     * @param array<string, mixed> $attributes The current attributes
     *
     * @return array<string, mixed> The updated attributes (empty on failure)
     */
    private function processExternalImage(string $url, array $attributes): array
    {
        if (!$this->fetchExternalImages) {
            return $attributes;
        }

        // Fetch and import the external image
        try {
            $backendUser = $this->environmentInfo->getBackendUser();
            if ($backendUser === null) {
                $this->logger->warning('No backend user available for external image import', [
                    'url' => $url,
                ]);

                return $attributes;
            }

            $folder = $this->uploadFolderResolver->resolve($backendUser);

            $content = $this->externalFetcher->fetch($url);
            if ($content === null) {
                return [];
            }

            // Generate filename from URL
            $filename = $this->generateExternalFilename($url);

            // Create file in upload folder
            $createdFile = $folder->createFile($filename);
            $fileObject  = $createdFile->setContents($content);

            if (!$fileObject instanceof File) {
                $this->logger->error('Created file is not a File instance', [
                    'url' => $url,
                ]);

                return [];
            }

            $width  = (int) $attributes['width'];
            $height = (int) $attributes['height'];

            if ($width > 0 && $height > 0) {
                $processedFile = $this->fileResolver->processImage($fileObject, $width, $height);

                if ($processedFile !== null) {
                    return $this->builder->withProcessedImage(
                        $attributes,
                        (int) $processedFile->getProperty('width'),
                        (int) $processedFile->getProperty('height'),
                        $processedFile->getPublicUrl() ?? '',
                        $fileObject->getUid(),
                    );
                }
            }

            // Return with file UID even if not processed
            $attributes['data-htmlarea-file-uid'] = $fileObject->getUid();

            return $attributes;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to process external image', [
                'url'       => $url,
                'exception' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Process a local image (no UID, but on same site).
     *
     * @param string               $url        The local URL
     * @param array<string, mixed> $attributes The current attributes
     * @param string               $siteUrl    The site URL
     *
     * @return array<string, mixed> The updated attributes
     */
    private function processLocalImage(string $url, array $attributes, string $siteUrl): array
    {
        // Get relative path from site URL
        $path = rawurldecode(substr($url, strlen($siteUrl)));

        // Try to resolve file by path
        $file = $this->fileResolver->resolveByPath($path);

        if ($file !== null) {
            $fileUid = $file->getUid();

            // Check for processed file - get original UID
            if ($file->hasProperty('original')) {
                $originalUid = $file->getProperty('original');
                if ($originalUid !== null) {
                    $fileUid = (int) $originalUid;
                }
            }

            $attributes['data-htmlarea-file-uid'] = $fileUid;
        }

        return $attributes;
    }

    /**
     * Generate a filename from external URL.
     *
     * Uses SecurityValidator to verify extension is in allowed list.
     *
     * @param string $url The external URL
     *
     * @return string The generated filename
     */
    private function generateExternalFilename(string $url): string
    {
        $path      = parse_url($url, PHP_URL_PATH);
        $pathInfo  = is_string($path) ? pathinfo($path) : [];
        $extension = strtolower($pathInfo['extension'] ?? 'jpg');

        // Validate extension using centralized security validator
        if (!$this->securityValidator->isAllowedExtension($extension)) {
            $extension = 'jpg';
        }

        return substr(md5($url), 0, 10) . '.' . $extension;
    }
}
