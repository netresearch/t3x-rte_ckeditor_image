<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller;

use Netresearch\RteCKEditorImage\Utils\ProcessedFilesHandler;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LogLevel as PsrLogLevel;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Controller to render the image tag in frontend.
 *
 * @author  Christian Opitz <christian.opitz@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class ImageRenderingController
{
    /**
     * Same as class name.
     *
     * @var string
     */
    public $prefixId = 'ImageRenderingController';

    /**
     * Path to this script relative to the extension dir.
     *
     * @var string
     */
    public $scriptRelPath = 'Classes/Controller/ImageRenderingController.php';

    /**
     * The extension key.
     *
     * @var string
     */
    public $extKey = 'rte_ckeditor_image';

    protected ?ContentObjectRenderer $cObj = null;

    /**
     * Constructor with dependency injection.
     *
     * @param ResourceFactory       $resourceFactory       Factory for file resources
     * @param ProcessedFilesHandler $processedFilesHandler Handler for processing files
     * @param LogManager            $logManager            Log manager for error logging
     */
    public function __construct(
        private readonly ResourceFactory $resourceFactory,
        private readonly ProcessedFilesHandler $processedFilesHandler,
        private readonly LogManager $logManager,
    ) {}

    public function setContentObjectRenderer(
        ContentObjectRenderer $cObj,
    ): void {
        $this->cObj = $cObj;
    }

    /**
     * Returns a processed image to be displayed on the Frontend.
     *
     * @param string|null            $content Content input (not used)
     * @param mixed[]                $conf    TypoScript configuration
     * @param ServerRequestInterface $request
     *
     * @return string HTML output
     */
    public function renderImageAttributes(?string $content, array $conf, ServerRequestInterface $request): string
    {
        $imageAttributes = $this->getImageAttributes();
        $imageSource     = $imageAttributes['src'] ?? '';
        $systemImage     = null; // Initialize to prevent undefined variable in popup rendering check

        // It is pretty rare to be in presence of an external image as the default behaviour
        // of the RTE is to download the external image and create a local image.
        // However, it may happen if the RTE has the flag "disable"
        if (!$this->isExternalImage($imageSource)) {
            $fileUid = (int) ($imageAttributes['data-htmlarea-file-uid'] ?? 0);

            if ($fileUid > 0) {
                try {
                    $systemImage = $this->resourceFactory->getFileObject($fileUid);

                    // SECURITY: Prevent privilege escalation by checking file visibility
                    // Only process public files in frontend rendering. Non-public files must
                    // use TYPO3's protected file delivery (eID_dumpFile) which performs
                    // proper authentication checks for the current frontend user.
                    // This prevents low-privilege backend editors from exposing files
                    // outside their Filemount restrictions by manipulating file UIDs.
                    if (!$systemImage->getStorage()->isPublic()) {
                        $this->getLogger()->log(
                            PsrLogLevel::WARNING,
                            'Blocked rendering of non-public file in frontend context',
                            [
                                'fileUid'     => $fileUid,
                                'storage'     => $systemImage->getStorage()->getUid(),
                                'storageName' => $systemImage->getStorage()->getName(),
                            ],
                        );

                        // Skip processing and continue with cleanup
                        throw new FileDoesNotExistException();
                    }

                    // Read noScale configuration from TypoScript (site-wide)
                    $noScale            = (bool) ($conf['noScale'] ?? false);
                    $maxFileSizeForAuto = 0;

                    if (isset($conf['noScale.']) && is_array($conf['noScale.'])) {
                        $value = $conf['noScale.']['maxFileSizeForAuto'] ?? 0;
                        // TypoScript values can be strings or ints - type-guard before casting
                        if (is_numeric($value)) {
                            $maxFileSizeForAuto = (int) $value;
                        }
                    }

                    // Per-image noScale override: data-noscale attribute takes precedence
                    if (isset($imageAttributes['data-noscale'])) {
                        // Handle string values properly: 'false' and '0' should be false
                        $noScaleValue = $imageAttributes['data-noscale'];
                        $noScale      = !($noScaleValue === 'false' || $noScaleValue === '0' || $noScaleValue === false);
                    }

                    // Fallback: Check if data-quality is 'none' (for JS-disabled scenarios)
                    // This ensures "No Scaling" works even when JavaScript fails
                    if (($imageAttributes['data-quality'] ?? '') === 'none') {
                        $noScale = true;
                    }

                    // Get display dimensions from HTML attributes
                    $displayWidth  = (int) ($imageAttributes['width'] ?? ((int) $systemImage->getProperty('width')));
                    $displayHeight = (int) ($imageAttributes['height'] ?? ((int) $systemImage->getProperty('height')));

                    // Get quality multiplier from data-quality attribute
                    $qualityMultiplier = $this->getQualityMultiplier($imageAttributes['data-quality'] ?? '');

                    // Calculate processing dimensions: display × quality multiplier
                    // This is what TYPO3 will process the image to
                    $processingWidth  = (int) round($displayWidth * $qualityMultiplier);
                    $processingHeight = (int) round($displayHeight * $qualityMultiplier);

                    // Cap processing dimensions at original image size (never upscale)
                    $originalWidth    = (int) $systemImage->getProperty('width');
                    $originalHeight   = (int) $systemImage->getProperty('height');
                    $processingWidth  = min($processingWidth, $originalWidth);
                    $processingHeight = min($processingHeight, $originalHeight);

                    // Prepare image configuration for TYPO3 image processor
                    $imageConfiguration = [
                        'width'  => $processingWidth,
                        'height' => $processingHeight,
                    ];

                    // Check if we should skip processing and use original file
                    if ($this->shouldSkipProcessing($systemImage, $imageConfiguration, $noScale, $maxFileSizeForAuto)) {
                        // Use original file without processing
                        $imageSource = $systemImage->getPublicUrl();

                        if ($imageSource === null) {
                            throw new FileDoesNotExistException();
                        }

                        $additionalAttributes = [
                            'src'    => $imageSource,
                            'title'  => $this->getAttributeValue('title', $imageAttributes, $systemImage),
                            'alt'    => $this->getAttributeValue('alt', $imageAttributes, $systemImage),
                            'width'  => $displayWidth !== 0 ? $displayWidth : ((int) $systemImage->getProperty('width')),
                            'height' => $displayHeight !== 0 ? $displayHeight : ((int) $systemImage->getProperty('height')),
                        ];
                    } else {
                        // Process image to create variant
                        $processedFile = $this->processedFilesHandler->createProcessedFile($systemImage, $imageConfiguration);

                        $imageSource = $processedFile->getPublicUrl();

                        if ($imageSource === null) {
                            throw new FileDoesNotExistException();
                        }

                        // Always use display dimensions in HTML output, not processing dimensions
                        // This ensures the browser scales the high-quality processed image to the desired display size
                        $additionalAttributes = [
                            'src'    => $imageSource,
                            'title'  => $this->getAttributeValue('title', $imageAttributes, $systemImage),
                            'alt'    => $this->getAttributeValue('alt', $imageAttributes, $systemImage),
                            'width'  => $displayWidth,
                            'height' => $displayHeight,
                        ];
                    }

                    $lazyLoading = $this->getLazyLoadingConfiguration($request);

                    if ($lazyLoading !== null) {
                        $additionalAttributes['loading'] = $lazyLoading;
                    }

                    // Remove internal attributes
                    unset(
                        $imageAttributes['data-title-override'],
                        $imageAttributes['data-alt-override'],
                    );

                    $imageAttributes = array_merge($imageAttributes, $additionalAttributes);
                } catch (FileDoesNotExistException $exception) {
                    // SECURITY: Log without exposing internal file UIDs to prevent information disclosure
                    $this->getLogger()->log(
                        PsrLogLevel::ERROR,
                        'Unable to find requested file',
                        ['exception' => $exception],
                    );
                }
            }
        }

        // Cleanup attributes
        if (
            !isset($imageAttributes['data-htmlarea-zoom'])
            && !isset($imageAttributes['data-htmlarea-clickenlarge'])
        ) {
            $unsetParams = [
                'allParams',
                'data-htmlarea-file-uid',
                'data-htmlarea-file-table',
                'data-htmlarea-zoom',
                'data-htmlarea-clickenlarge', // Legacy zoom property
            ];

            $imageAttributes = array_diff_key($imageAttributes, array_flip($unsetParams));
        }

        // Add a leading slash if only a path is given
        if (
            is_string($imageSource)
            && $imageSource !== ''
            && strncasecmp($imageSource, 'http', 4) !== 0
            && !str_starts_with($imageSource, '/')
            && !str_starts_with($imageSource, 'data:image')
        ) {
            $imageAttributes['src'] = '/' . $imageSource;
        }

        // Ensure all attributes are strings for implodeAttributes
        $stringAttributes = array_map(fn ($value): string => (string) $value, $imageAttributes);

        // Image template; empty attributes are removed by 3rd param 'false'
        $img = '<img ' . GeneralUtility::implodeAttributes($stringAttributes, true) . ' />';

        // Popup rendering (support new `zoom` and legacy `clickenlarge` attributes)
        // Configuration is provided by Configuration/TypoScript/ImageRendering/setup.typoscript
        // which defines lib.contentElement.settings.media.popup with sensible defaults
        if (
            (isset($imageAttributes['data-htmlarea-zoom'])
                || isset($imageAttributes['data-htmlarea-clickenlarge']))
            && isset($systemImage)
        ) {
            $frontendTyposcript = $request->getAttribute('frontend.typoscript');
            if ($frontendTyposcript === null) {
                return $img;
            }

            $setupArray       = $frontendTyposcript->getSetupArray();
            $popupConfig      = $setupArray['lib.']['contentElement.']['settings.']['media.']['popup.'] ?? [];
            $config           = is_array($popupConfig) ? $popupConfig : [];
            $config['enable'] = 1;

            $systemImage->updateProperties([
                'title' => $imageAttributes['title'] ?? $systemImage->getProperty('title') ?? '',
            ]);

            if ($this->cObj instanceof ContentObjectRenderer) {
                $this->cObj->setCurrentFile($systemImage);

                // Use $this->cObject to have access to all parameters from the image tag
                return $this->cObj->imageLinkWrap(
                    $img,
                    $systemImage,
                    $config,
                );
            }
        }

        return $img;
    }

    /**
     * Returns the lazy loading configuration.
     *
     * @return string|null
     */
    private function getLazyLoadingConfiguration(ServerRequestInterface $request): ?string
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
     * Returns a sanitizes array of attributes out of $this->cObj.
     *
     * @return array<string, string>
     */
    protected function getImageAttributes(): array
    {
        return $this->cObj->parameters ?? [];
    }

    /**
     * Tells whether the image URL is found to be "external".
     *
     * @param string $imageSource The image source
     *
     * @return bool
     */
    protected function isExternalImage(string $imageSource): bool
    {
        // https://github.com/netresearch/t3x-rte_ckeditor_image/issues/187
        if (str_contains($imageSource, '/typo3/image/process?token')) {
            // is a 11LTS backend processing url only valid for BE users, thus reprocessing needed
            return false;
        }

        // Source starts with "http(s)" or a double slash
        return (strncasecmp($imageSource, 'http', 4) === 0)
            || str_starts_with($imageSource, '//');
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
     * Get quality multiplier from data-quality attribute.
     *
     * Maps quality setting to processing multiplier:
     * - none: 1.0 (but triggers noScale processing skip)
     * - low: 0.9
     * - standard: 1.0
     * - retina: 2.0
     * - ultra: 3.0
     * - print: 6.0
     *
     * @param string $quality Quality setting from data-quality attribute
     *
     * @return float Quality multiplier for image processing
     */
    private function getQualityMultiplier(string $quality): float
    {
        $multiplier = match ($quality) {
            'none', '' => 1.0, // No scaling option or empty
            'low'      => 0.9,
            'standard' => 1.0,
            'retina'   => 2.0,
            'ultra'    => 3.0,
            'print'    => 6.0,
            default    => null, // Invalid value - will log and default
        };

        if ($multiplier === null) {
            $this->getLogger()->log(
                PsrLogLevel::WARNING,
                'Invalid data-quality value received, defaulting to standard (1.0x)',
                ['qualityValue' => $quality],
            );

            return 1.0;
        }

        return $multiplier;
    }
}
