<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LogLevel as PsrLogLevel;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Abstract base controller for image rendering in frontend.
 *
 * Provides shared functionality for image rendering controllers including:
 * - Lazy loading configuration retrieval
 * - Image attribute value resolution with fallback
 * - Processing skip logic for SVG/noScale/auto-optimization
 * - File visibility security validation
 * - Logger instance management
 *
 * @author  Sebastian Mendel <sebastian.mendel@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
abstract class AbstractImageRenderingController
{
    protected ?ContentObjectRenderer $cObj = null;

    /**
     * Constructor with dependency injection.
     *
     * @param LogManager $logManager Log manager for error logging
     */
    public function __construct(
        protected readonly LogManager $logManager,
    ) {}

    public function setContentObjectRenderer(
        ContentObjectRenderer $cObj,
    ): void {
        $this->cObj = $cObj;
    }

    /**
     * Returns the lazy loading configuration.
     *
     * @return string|null
     */
    protected function getLazyLoadingConfiguration(ServerRequestInterface $request): ?string
    {
        $frontendTyposcript = $request->getAttribute('frontend.typoscript');
        if ($frontendTyposcript === null) {
            return null;
        }

        $setupArray = $frontendTyposcript->getSetupArray();

        $lazyLoading = $setupArray['lib.']['contentElement.']['settings.']['media.']['lazyLoading'] ?? null;

        return is_string($lazyLoading) ? $lazyLoading : null;
    }

    /**
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        return $this->logManager->getLogger(static::class);
    }

    /**
     * Returns attributes value or even empty string when override mode is enabled.
     *
     * @param non-empty-string      $attributeName
     * @param array<string, string> $attributes
     * @param File                  $image
     *
     * @return string
     */
    protected function getAttributeValue(string $attributeName, array $attributes, File $image): string
    {
        return (string) ($attributes[$attributeName] ?? $image->getProperty($attributeName));
    }

    /**
     * Determine if image processing should be skipped.
     *
     * Skips processing when:
     * 1. SVG files (vector graphics don't benefit from raster processing)
     * 2. noScale is explicitly enabled in TypoScript configuration
     * 3. Auto-optimization: Requested dimensions match original file dimensions exactly
     * 4. No dimensions requested (use original)
     *
     * Auto-optimization respects file size threshold to prevent serving oversized originals.
     *
     * @param File    $originalFile       The original file
     * @param mixed[] $imageConfiguration Requested image configuration (width, height)
     * @param bool    $noScale            noScale setting from TypoScript configuration
     * @param int     $maxFileSizeForAuto Maximum file size in bytes for auto-optimization (0 = no limit)
     *
     * @return bool True if processing should be skipped and original file used
     */
    protected function shouldSkipProcessing(
        File $originalFile,
        array $imageConfiguration,
        bool $noScale,
        int $maxFileSizeForAuto = 0,
    ): bool {
        // SVG files: Always skip processing (vector graphics don't need raster processing)
        // SVG scaling is handled by the browser, and ImageMagick would rasterize them
        if (strtolower($originalFile->getExtension()) === 'svg') {
            return true;
        }

        // Explicit noScale = 1 in TypoScript configuration
        if ($noScale) {
            return true;
        }

        // Auto-optimization: Get original dimensions
        $originalWidth   = (int) ($originalFile->getProperty('width') ?? 0);
        $originalHeight  = (int) ($originalFile->getProperty('height') ?? 0);
        $requestedWidth  = (int) ($imageConfiguration['width'] ?? 0);
        $requestedHeight = (int) ($imageConfiguration['height'] ?? 0);

        // If no dimensions requested, use original file
        if ($requestedWidth === 0 && $requestedHeight === 0) {
            return true;
        }

        // If dimensions match exactly, check file size threshold before auto-optimizing
        if ($requestedWidth === $originalWidth && $requestedHeight === $originalHeight) {
            // Check file size threshold if configured
            if ($maxFileSizeForAuto > 0) {
                $fileSize = $originalFile->getSize();

                // If file exceeds threshold, process it to potentially reduce size
                if ($fileSize > $maxFileSizeForAuto) {
                    return false;
                }
            }

            // Dimensions match and within size threshold - skip processing
            return true;
        }

        // Different dimensions requested - processing needed
        return false;
    }

    /**
     * Validate file visibility for frontend rendering.
     *
     * SECURITY: Prevent privilege escalation by checking file visibility.
     * Only process public files in frontend rendering. Non-public files must
     * use TYPO3's protected file delivery (eID_dumpFile) which performs
     * proper authentication checks for the current frontend user.
     * This prevents low-privilege backend editors from exposing files
     * outside their Filemount restrictions by manipulating file UIDs.
     *
     * @param File   $file    The file to validate
     * @param int    $fileUid The file UID for logging
     * @param string $context Context description for logging (e.g., "frontend context", "linked image context")
     *
     * @return bool True if file is public and can be rendered, false otherwise
     */
    protected function validateFileVisibility(File $file, int $fileUid, string $context): bool
    {
        if (!$file->getStorage()->isPublic()) {
            $this->getLogger()->log(
                PsrLogLevel::WARNING,
                sprintf('Blocked rendering of non-public file in %s', $context),
                [
                    'fileUid'     => $fileUid,
                    'storage'     => $file->getStorage()->getUid(),
                    'storageName' => $file->getStorage()->getName(),
                ],
            );

            return false;
        }

        return true;
    }
}
