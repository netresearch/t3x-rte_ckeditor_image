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
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
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
class ImageRenderingController extends AbstractImageRenderingController
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
        LogManager $logManager,
    ) {
        parent::__construct($logManager);
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

                    // SECURITY: Validate file visibility to prevent privilege escalation
                    if (!$this->validateFileVisibility($systemImage, $fileUid, 'frontend context')) {
                        throw new FileDoesNotExistException();
                    }

                    // Read maxFileSizeForAuto configuration from TypoScript
                    $maxFileSizeForAuto = 0;

                    if (isset($conf['noScale.']) && is_array($conf['noScale.'])) {
                        $value = $conf['noScale.']['maxFileSizeForAuto'] ?? 0;
                        // TypoScript values can be strings or ints - type-guard before casting
                        if (is_numeric($value)) {
                            $maxFileSizeForAuto = (int) $value;
                        }
                    }

                    // Determine noScale with proper priority order:
                    // Priority 1: data-quality="none" (quality dropdown "No Scaling" option)
                    if (($imageAttributes['data-quality'] ?? '') === 'none') {
                        $noScale = true;
                    }
                    // Priority 2: data-noscale attribute (backward compatibility with existing content)
                    elseif (isset($imageAttributes['data-noscale'])) {
                        // Handle string values properly: 'false' and '0' should be false
                        $noScaleValue = $imageAttributes['data-noscale'];
                        $noScale      = !in_array($noScaleValue, ['false', '0', false], true);
                    }
                    // Priority 3: TypoScript site-wide default
                    else {
                        $noScale = (bool) ($conf['noScale'] ?? false);
                    }

                    // Get display dimensions from HTML attributes with type guards
                    $widthValue = $imageAttributes['width'] ?? $systemImage->getProperty('width');
                    $displayWidth = is_numeric($widthValue) ? (int) $widthValue : 0;

                    $heightValue = $imageAttributes['height'] ?? $systemImage->getProperty('height');
                    $displayHeight = is_numeric($heightValue) ? (int) $heightValue : 0;

                    // Get quality multiplier from data-quality attribute
                    $qualityMultiplier = $this->getQualityMultiplier($imageAttributes['data-quality'] ?? '');

                    // Calculate processing dimensions: display × quality multiplier
                    // This is what TYPO3 will process the image to
                    $processingWidth  = (int) round($displayWidth * $qualityMultiplier);
                    $processingHeight = (int) round($displayHeight * $qualityMultiplier);

                    // Cap processing dimensions at original image size (never upscale) with type guards
                    $originalWidthProperty = $systemImage->getProperty('width');
                    $originalWidth = is_numeric($originalWidthProperty) ? (int) $originalWidthProperty : 0;

                    $originalHeightProperty = $systemImage->getProperty('height');
                    $originalHeight = is_numeric($originalHeightProperty) ? (int) $originalHeightProperty : 0;

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
        $stringAttributes = array_map(fn (int|string $value): string => (string) $value, $imageAttributes);

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

            $setupArray = $frontendTyposcript->getSetupArray();

            // Type-safe TypoScript array access for popup configuration
            $popupConfig = [];
            if (
                is_array($setupArray['lib.'] ?? null)
                && is_array($setupArray['lib.']['contentElement.'] ?? null)
                && is_array($setupArray['lib.']['contentElement.']['settings.'] ?? null)
                && is_array($setupArray['lib.']['contentElement.']['settings.']['media.'] ?? null)
                && is_array($setupArray['lib.']['contentElement.']['settings.']['media.']['popup.'] ?? null)
            ) {
                $popupConfig = $setupArray['lib.']['contentElement.']['settings.']['media.']['popup.'];
            }

            $config = $popupConfig;
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
