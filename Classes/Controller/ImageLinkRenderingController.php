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
 * Controller to render the linked images in frontend
 *
 * @author  Mathias Uhlmann <mathias.uhlmann@netresearch.de>
 * @license http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link    http://www.netresearch.de
 */
class ImageLinkRenderingController extends AbstractPlugin
{
    /**
     * Same as class name
     *
     * @var string
     */
    public $prefixId = 'ImageLinkRenderingController';

    /**
     * Path to this script relative to the extension dir
     *
     * @var string
     */
    public $scriptRelPath = 'Classes/Controller/ImageLinkRenderingController.php';

    /**
     * The extension key.
     *
     * @var string
     */
    public $extKey = 'rte_ckeditor_image';

    /**
     * Returns a processed image to be displayed on the Frontend.
     *
     * @param null|string $content Content input (not used)
     * @param mixed[]     $conf    TypoScript configuration
     *
     * @return string HTML output
     */
    public function renderImages(?string $content, array $conf = []): string
    {
        // Get link inner HTML
        $linkContent = $this->cObj !== null ? $this->cObj->getCurrentVal() : null;
        // Find all images with file-uid attribute
        $imgSearchPattern = '/<p\><img(?=.*src).*?\/><\/p>/';
        $attrSearchPattern = '/([a-zA-Z0-9-]+)=["]([^"]*)"|([a-zA-Z0-9-]+)=[\']([^\']*)\'/';
        $passedImages = [];
        $parsedImages = [];

        // Extract all TYPO3 images from link content
        preg_match_all($imgSearchPattern, $linkContent, $passedImages);
        $passedImages = $passedImages[0];

        if (count($passedImages) === 0) {
            return $linkContent;
        }

        foreach ($passedImages as $passedImage) {
            // Get image attributes
            preg_match_all($attrSearchPattern, $passedImage, $passedAttributes);
            $passedAttributes = array_combine($passedAttributes[1], $passedAttributes[2]);

            // The image is already parsed by netresearch linkrenderer, which removes custom attributes, so it will never match this condition.
            // But we leave this as fallback for older render versions.
            if (isset($passedAttributes['data-htmlarea-file-uid'])) {
                try {
                    /** @var ResourceFactory $resourceFactory */
                    $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                    $systemImage = $resourceFactory->getFileObject($passedAttributes['data-htmlarea-file-uid']);

                    $imageConfiguration = [
                        'width' => $passedAttributes['width'] ?? $systemImage->getProperty('width'),
                        'height' => $passedAttributes['height'] ?? $systemImage->getProperty('height')
                    ];

                    $processedFile = $this->getMagicImageService()->createMagicImage($systemImage, $imageConfiguration);
                    $additionalAttributes = [
                        'src' => $processedFile->getPublicUrl(),
                        'title' => self::getAttributeValue('title', $passedAttributes, $systemImage),
                        'alt' => self::getAttributeValue('alt', $passedAttributes, $systemImage),
                        'width' => $passedAttributes['width'] ?? $systemImage->getProperty('width'),
                        'height' => $passedAttributes['height'] ?? $systemImage->getProperty('height')
                    ];

                    if (isset($GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['lazyLoading'])) {
                        $additionalAttributes['loading'] = $GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['lazyLoading'];
                    }

                    // Remove internal attributes
                    unset($passedAttributes['data-title-override'], $passedAttributes['data-alt-override']);

                    // Add original attributes, if not already parsed
                    $imageAttributes = array_merge($additionalAttributes, $passedAttributes);

                    // Cleanup attributes; disable zoom images within links
                    $unsetParams = [
                        'data-htmlarea-file-uid',
                        'data-htmlarea-file-table',
                        'data-htmlarea-zoom',
                        'data-htmlarea-clickenlarge' // Legacy zoom property
                    ];
                    $imageAttributes = array_diff_key($imageAttributes, array_flip($unsetParams));
                    // Image template; empty attributes are removed by 3rd param 'false'
                    $parsedImages[] = '<img ' . GeneralUtility::implodeAttributes($imageAttributes, true) . ' />';
                } catch (FileDoesNotExistException $fileDoesNotExistException) {
                    $parsedImages[] = strip_tags($passedImage , '<img>');
                    // Log in fact the file could not be retrieved.
                    $message = sprintf('I could not find file with uid "%s"', $passedAttributes['data-htmlarea-file-uid']);
                    $this->getLogger()->log(PsrLogLevel::ERROR,$message);
                }
            } else {
                $parsedImages[] = strip_tags($passedImage , '<img>');
            }
        }
        // Replace original images with parsed
        return str_replace($passedImages, $parsedImages, $linkContent);
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
     * @param string                        $attributeName
     * @param array<string, string>         $attributes
     * @param File $image
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
