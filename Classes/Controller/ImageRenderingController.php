<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller;

use Psr\Log\LogLevel as PsrLogLevel;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Service\MagicImageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * Controller to render the image tag in frontend.
 *
 * @author  Christian Opitz <christian.opitz@netresearch.de>
 * @license http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link    http://www.netresearch.de
 */
class ImageRenderingController extends AbstractPlugin
{
    /**
     * Same as class name
     *
     * @var string
     */
    public $prefixId = 'ImageRenderingController';

    /**
     * Path to this script relative to the extension dir
     *
     * @var string
     */
    public $scriptRelPath = 'Classes/Controller/ImageRenderingController.php';

    /**
     * The extension key
     *
     * @var string
     */
    public $extKey = 'rte_ckeditor_image';

    /**
     * Returns a processed image to be displayed on the Frontend.
     *
     * @param null|string  $content Content input (not used)
     * @param mixed[]      $conf    TypoScript configuration
     *
     * @return string HTML output
     */
    public function renderImageAttributes(?string $content, array $conf = []): string
    {
        $imageAttributes = $this->getImageAttributes();

        // It is pretty rare to be in presence of an external image as the default behaviour
        // of the RTE is to download the external image and create a local image.
        // However, it may happen if the RTE has the flag "disable"
        if (!$this->isExternalImage()) {
            $fileUid = (int) ($imageAttributes['data-htmlarea-file-uid'] ?? 0);

            if ($fileUid > 0) {
                try {
                    /** @var ResourceFactory $resourceFactory */
                    $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                    $systemImage = $resourceFactory->getFileObject($fileUid);

                    if ($imageAttributes['src'] !== $systemImage->getPublicUrl()) {
                        // Source file is a processed image
                        $imageConfiguration = [
                            'width' => (int)$imageAttributes['width'],
                            'height' => (int)$imageAttributes['height']
                        ];

                        $processedFile = $this->getMagicImageService()->createMagicImage($systemImage, $imageConfiguration);

                        $additionalAttributes = [
                            'src' => $processedFile->getPublicUrl(),
                            'title' => self::getAttributeValue('title', $imageAttributes, $systemImage),
                            'alt' => self::getAttributeValue('alt', $imageAttributes, $systemImage),
                            'width' => $processedFile->getProperty('width') ?? $imageConfiguration['width'],
                            'height' => $processedFile->getProperty('height') ?? $imageConfiguration['height'],
                        ];

                        if (isset($GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['lazyLoading'])) {
                            $additionalAttributes['loading'] = $GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['lazyLoading'];
                        }

                        // Remove internal attributes
                        unset($imageAttributes['data-title-override'], $imageAttributes['data-alt-override']);

                        $imageAttributes = array_merge($imageAttributes, $additionalAttributes);
                    }
                } catch (FileDoesNotExistException $fileDoesNotExistException) {
                    // Log in fact the file could not be retrieved.
                    $message = sprintf('I could not find file with uid "%s"', $fileUid);
                    $this->getLogger()->log(PsrLogLevel::ERROR, $message);
                }
            }
        }

        // Cleanup attributes
        if (!isset($imageAttributes['data-htmlarea-zoom']) && !isset($imageAttributes['data-htmlarea-clickenlarge'])) {
            $unsetParams = [
                'allParams',
                'data-htmlarea-file-uid',
                'data-htmlarea-file-table',
                'data-htmlarea-zoom',
                'data-htmlarea-clickenlarge' // Legacy zoom property
            ];
            $imageAttributes = array_diff_key($imageAttributes, array_flip($unsetParams));
        }

        if (
            $imageAttributes['src']
            && stripos($imageAttributes['src'], 'http') !== 0
            && strpos($imageAttributes['src'], '/') !== 0
        ) {
            $imageAttributes['src'] = '/' . $imageAttributes['src'];
        }

        // Image template; empty attributes are removed by 3rd param 'false'
        $img = '<img ' . GeneralUtility::implodeAttributes($imageAttributes, true) . ' />';

        // Popup rendering (support new `zoom` and legacy `clickenlarge` attributes)
        if ((($imageAttributes['data-htmlarea-zoom'] ?? false) || ($imageAttributes['data-htmlarea-clickenlarge'] ?? false)) && isset($systemImage)) {
            $config = $GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['popup.'] ?? [];
            $config['enable'] = 1;
            $systemImage->updateProperties([
                'title' => $imageAttributes['title'] ?? $systemImage->getProperty('title'),
            ]);

            if ($this->cObj !== null) {
                $this->cObj->setCurrentFile($systemImage);

                // Use $this->cObject to have access to all parameters from the image tag
                return $this->cObj->imageLinkWrap(
                    $img,
                    $systemImage,
                    $config
                );
            }
        }

        return $img;
    }

    /**
     * Returns a sanitizes array of attributes out of $this->cObj
     *
     * @return array<string, string>
     */
    protected function getImageAttributes(): array
    {
        return $this->cObj->parameters ?? [];
    }

    /**
     * Instantiates and prepares the Magic Image service.
     *
     * @return MagicImageService
     */
    protected function getMagicImageService(): MagicImageService
    {
        static $magicImageService;

        if ($magicImageService === null) {
            /** @var MagicImageService $magicImageService */
            $magicImageService = GeneralUtility::makeInstance(MagicImageService::class);

            // Get RTE configuration

            /** @var array<string, mixed[]> $pageTSConfig */
            $pageTSConfig = $this->frontendController->getPagesTSconfig();

            if (is_array($pageTSConfig['RTE.']['default.'])) {
                $magicImageService->setMagicImageMaximumDimensions($pageTSConfig['RTE.']['default.']);
            }
        }

        return $magicImageService;
    }

    /**
     * Tells whether the image URL is found to be "external".
     *
     * @return bool
     */
    protected function isExternalImage(): bool
    {
        $srcAbsoluteUrl = $this->cObj->parameters['src'] ?? '';
        if (strpos($srcAbsoluteUrl, '/typo3/image/process?token') !== false) {
            // is a 11LTS backend processing url only valid for BE users, thus reprocessing needed
            return false;
        }
        return (stripos($srcAbsoluteUrl, 'http') === 0)
            || (strpos($srcAbsoluteUrl, '//') === 0);
    }

    /**
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        /** @var LogManager $logManager */
        $logManager = GeneralUtility::makeInstance(LogManager::class);
        return $logManager->getLogger(get_class($this));
    }

    /**
     * Returns attributes value or even empty string when override mode is enabled
     *
     * @param string                $attributeName
     * @param array<string, string> $attributes
     * @param File                  $image
     *
     * @return string
     */
    protected static function getAttributeValue(string $attributeName, array $attributes, File $image): string
    {
        $attributeNameOverride = 'data-' . $attributeName . '-override';

        if (isset($attributes[$attributeNameOverride])) {
            $attributeValue = $attributes[$attributeNameOverride];
        } elseif (isset($attributes[$attributeName])) {
            $attributeValue = $attributes[$attributeName];
        } else {
            $attributeValue = $image->getProperty($attributeName);
        }

        return (string) $attributeValue;
    }
}
