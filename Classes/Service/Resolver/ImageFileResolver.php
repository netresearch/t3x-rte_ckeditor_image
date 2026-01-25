<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service\Resolver;

use Netresearch\RteCKEditorImage\Service\Environment\EnvironmentInfoInterface;
use Netresearch\RteCKEditorImage\Service\Fetcher\ExternalImageFetcher;
use Netresearch\RteCKEditorImage\Service\Security\SecurityValidatorInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Service for resolving and processing image files.
 *
 * Handles:
 * - Resolving file UIDs to File objects
 * - Processing images (resize, crop)
 * - Fetching and importing external images
 * - Resolving local file paths
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class ImageFileResolver
{
    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly SecurityValidatorInterface $securityValidator,
        private readonly ExternalImageFetcher $externalImageFetcher,
        private readonly EnvironmentInfoInterface $environmentInfo,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Resolve a file UID to a File object.
     *
     * @param int $fileUid The file UID
     *
     * @return File|null The File object or null if not found
     */
    public function resolveByUid(int $fileUid): ?File
    {
        if ($fileUid <= 0) {
            return null;
        }

        try {
            $file = $this->resourceFactory->getFileObject($fileUid);

            return $file instanceof File ? $file : null;
        } catch (Throwable $exception) {
            $this->logger->warning('Could not resolve file by UID', [
                'fileUid'   => $fileUid,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve a local file path to a File object.
     *
     * @param string $path The file path (relative to public path)
     *
     * @return File|null The File object or null if not found
     */
    public function resolveByPath(string $path): ?File
    {
        if (trim($path) === '') {
            return null;
        }

        $publicPath = $this->environmentInfo->getPublicPath();

        // Validate path for security (prevent directory traversal)
        $validatedPath = $this->securityValidator->validateLocalPath($path, $publicPath);
        if ($validatedPath === null) {
            $this->logger->warning('Local image path failed security validation', [
                'path' => $path,
            ]);

            return null;
        }

        try {
            // Try to retrieve by combined identifier
            $file = $this->resourceFactory->retrieveFileOrFolderObject($path);

            return $file instanceof File ? $file : null;
        } catch (Throwable $exception) {
            $this->logger->warning('Could not resolve file by path', [
                'path'      => $path,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

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
    ): ?ProcessedFile {
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        try {
            $processingInstructions = array_merge([
                'width'  => $width,
                'height' => $height,
            ], $processingOptions);

            $processedFile = $file->process(
                ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                $processingInstructions,
            );

            return $processedFile instanceof ProcessedFile ? $processedFile : null;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to process image', [
                'fileUid'   => $file->getUid(),
                'width'     => $width,
                'height'    => $height,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Import an external image and return the File object.
     *
     * Fetches an external image URL and imports it into the TYPO3 FAL system.
     * The image is validated for MIME type before import.
     *
     * @param string $url          The external URL to fetch the image from
     * @param string $targetFolder TYPO3 combined identifier (storage:path) for target folder.
     *                             Default '1:/_temp_/' refers to storage ID 1, folder '_temp_'.
     *                             Use DefaultUploadFolderResolver to get user-specific folders.
     *
     * @return File|null The imported File object or null on validation/import failure
     */
    public function importExternalImage(string $url, string $targetFolder = '1:/_temp_/'): ?File
    {
        $content = $this->externalImageFetcher->fetch($url);
        if ($content === null) {
            return null;
        }

        try {
            // Generate a safe filename from URL
            $filename = $this->generateFilenameFromUrl($url);

            $folderObject = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($targetFolder);
            if (!$folderObject instanceof Folder) {
                $this->logger->error('Could not resolve target folder', [
                    'targetFolder' => $targetFolder,
                ]);

                return null;
            }

            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'rte_img_');
            if ($tempFile === false) {
                throw new RuntimeException('Could not create temporary file');
            }

            file_put_contents($tempFile, $content);

            try {
                $file = $folderObject->addFile($tempFile, $filename);

                return $file instanceof File ? $file : null;
            } finally {
                // Clean up temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        } catch (Throwable $exception) {
            $this->logger->error('Failed to import external image', [
                'url'       => $url,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if a file exists and is accessible.
     *
     * @param int $fileUid The file UID
     *
     * @return bool True if file exists and is accessible
     */
    public function fileExists(int $fileUid): bool
    {
        return $this->resolveByUid($fileUid) !== null;
    }

    /**
     * Generate a safe filename from URL.
     *
     * @param string $url The URL
     *
     * @return string The generated filename
     */
    private function generateFilenameFromUrl(string $url): string
    {
        $path     = parse_url($url, PHP_URL_PATH);
        $basename = is_string($path) ? basename($path) : '';

        // Extract extension
        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        if ($extension === '' || !$this->securityValidator->isAllowedExtension($extension)) {
            $extension = 'jpg';
        }

        // Generate unique filename
        $hash = substr(md5($url . microtime()), 0, 12);

        return 'external_' . $hash . '.' . strtolower($extension);
    }
}
