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
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * Controller to render the linked images in frontend
 *
 * @author  Mathias Uhlmann <mathias.uhlmann@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 * @link    https://www.netresearch.de
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
     * @param string|null $content Content input (not used)
     * @param mixed[]     $conf    TypoScript configuration
     *
     * @return string HTML output
     */
    public function renderImages(?string $content, array $conf = []): string
    {
        // Get link inner HTML
        $linkContent = $this->cObj instanceof ContentObjectRenderer ? $this->cObj->getCurrentVal() : null;

        // Find all images with file-uid attribute
        $imgSearchPattern = '/<p[^>]*>\s*<img(?=.*src).*?\/>\s*<\/p>/';
        $passedImages = [];
        $parsedImages = [];

        // Extract all TYPO3 images from link content
        preg_match_all($imgSearchPattern, (string)$linkContent, $passedImages);

        $passedImages = $passedImages[0];

        if (\count($passedImages) === 0) {
            return $linkContent;
        }

        foreach ($passedImages as $passedImage) {
            $imageAttributes = $this->getImageAttributes($passedImage);

            // The image is already parsed by netresearch linkrenderer, which removes custom attributes,
            // so it will never match this condition.
            //
            // But we leave this as fallback for older render versions.
            if (($imageAttributes !== []) && isset($imageAttributes['data-htmlarea-file-uid'])) {
                $fileUid = (int)($imageAttributes['data-htmlarea-file-uid']);

                if ($fileUid > 0) {
                    try {
                        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                        $systemImage = $resourceFactory->getFileObject($fileUid);

                        $imageConfiguration = [
                            'width'  => (int)($imageAttributes['width'] ?? $systemImage->getProperty('width') ?? 0),
                            'height' => (int)($imageAttributes['height'] ?? $systemImage->getProperty('height') ?? 0),
                        ];

                        $processedFile = $this->getMagicImageService()
                            ->createMagicImage($systemImage, $imageConfiguration);

                        $additionalAttributes = [
                            'src'    => $processedFile->getPublicUrl(),
                            'title'  => $this->getAttributeValue('title', $imageAttributes, $systemImage),
                            'alt'    => $this->getAttributeValue('alt', $imageAttributes, $systemImage),
                            'width'  => $processedFile->getProperty('width') ?? $imageConfiguration['width'],
                            'height' => $processedFile->getProperty('height') ?? $imageConfiguration['height'],
                        ];

                        $lazyLoading = $this->getLazyLoadingConfiguration();

                        if ($lazyLoading !== null) {
                            $additionalAttributes['loading'] = $lazyLoading;
                        }

                        // Remove internal attributes
                        unset(
                            $imageAttributes['data-title-override'],
                            $imageAttributes['data-alt-override']
                        );

                        $imageAttributes = array_merge($imageAttributes, $additionalAttributes);

                        // Cleanup attributes; disable zoom images within links
                        $unsetParams = [
                            'data-htmlarea-file-uid',
                            'data-htmlarea-file-table',
                            'data-htmlarea-zoom',
                            // Legacy zoom property
                            'data-htmlarea-clickenlarge',
                        ];

                        $imageAttributes = array_diff_key($imageAttributes, array_flip($unsetParams));

                        // Image template; empty attributes are removed by 3rd param 'false'
                        $parsedImages[] = '<img ' . GeneralUtility::implodeAttributes($imageAttributes, true) . ' />';
                    } catch (FileDoesNotExistException) {
                        $parsedImages[] = strip_tags($passedImage, '<img>');

                        // Log in fact the file could not be retrieved
                        $this->getLogger()->log(
                            PsrLogLevel::ERROR,
                            sprintf('Unable to find file with uid "%s"', $fileUid)
                        );
                    }
                }
            } else {
                $parsedImages[] = strip_tags($passedImage, '<img>');
            }
        }

        // Replace original images with parsed
        return str_replace($passedImages, $parsedImages, $linkContent);
    }

    /**
     * Returns a sanitizes array of attributes out $passedImage
     *
     *
     * @return string[]
     */
    protected function getImageAttributes(string $passedImage): array
    {
        // Get image attributes
        preg_match_all(
            '/([a-zA-Z0-9-]+)=["]([^"]*)"|([a-zA-Z0-9-]+)=[\']([^\']*)\'/',
            $passedImage,
            $imageAttributes
        );

        /**
         * @var false|string[] $result
         */
        $result = array_combine($imageAttributes[1], $imageAttributes[2]);

        return \is_array($result) ? $result : [];
    }

    /**
     * Returns the lazy loading configuration.
     */
    private function getLazyLoadingConfiguration(): ?string
    {
        return $GLOBALS['TYPO3_REQUEST']->getAttribute('frontend.typoscript')->getSetupArray()['lib.']['contentElement.']['settings.']['media.']['lazyLoading'] ?? null;
    }

    /**
     * Instantiates and prepares the Magic Image service.
     */
    protected function getMagicImageService(): MagicImageService
    {
        static $magicImageService;

        if ($magicImageService === null) {
            $magicImageService = GeneralUtility::makeInstance(MagicImageService::class);

            // Get RTE configuration

            /**
             * @var array<string, mixed[]> $pageTSConfig
             */
            $pageTSConfig = $this->frontendController->getPagesTSconfig();

            if (\is_array($pageTSConfig['RTE.']['default.'])) {
                $magicImageService->setMagicImageMaximumDimensions($pageTSConfig['RTE.']['default.']);
            }
        }

        return $magicImageService;
    }

    protected function getLogger(): Logger
    {
        return GeneralUtility::makeInstance(LogManager::class)
            ->getLogger(static::class);
    }

    /**
     * Returns attributes value or even empty string when override mode is enabled
     *
     * @param array<string, string> $attributes
     */
    protected function getAttributeValue(string $attributeName, array $attributes, File $file): string
    {
        return (string)($attributes[$attributeName] ?? $file->getProperty($attributeName));
    }
}
