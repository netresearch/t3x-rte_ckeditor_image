<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Controller;

use Netresearch\RteCKEditorImage\Utils\ProcessedFilesHandler;
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
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 * @link    https://www.netresearch.de
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
     * @param string|null $content Content input (not used)
     * @param mixed[]     $conf    TypoScript configuration
     * @throws FileDoesNotExistException
     *
     * @return string HTML output
     */
    public function renderImageAttributes(?string $content, array $conf = []): string
    {
        $imageAttributes = $this->getImageAttributes();
        $imageSource     = $imageAttributes['src'] ?? '';

        // It is pretty rare to be in presence of an external image as the default behaviour
        // of the RTE is to download the external image and create a local image.
        // However, it may happen if the RTE has the flag "disable"
        if (!$this->isExternalImage($imageSource)) {
            $fileUid = (int)($imageAttributes['data-htmlarea-file-uid'] ?? 0);

            if ($fileUid > 0) {
                try {
                    $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                    $systemImage     = $resourceFactory->getFileObject($fileUid);

                    // check if there is a processed variant, if not, create one
                    /**
                     * @var ProcessedFilesHandler $processedHandler
                     */
                    $processedHandler = GeneralUtility::makeInstance(ProcessedFilesHandler::class);
                    $imageConfiguration = [
                        'width'  => (int)($imageAttributes['width'] ?? $systemImage->getProperty('width') ?? 0),
                        'height' => (int)($imageAttributes['height'] ?? $systemImage->getProperty('height') ?? 0),
                    ];
                    $processedFile = $processedHandler->createProcessedFile($systemImage, $imageConfiguration);

                    $imageSource = $processedFile->getPublicUrl();

                    if ($imageSource === null) {
                        throw new FileDoesNotExistException();
                    }

                    $additionalAttributes = [
                        'src'    => $imageSource,
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
                } catch (FileDoesNotExistException) {
                    // Log in fact the file could not be retrieved
                    $this->getLogger()->log(
                        PsrLogLevel::ERROR,
                        sprintf('Unable to find file with uid "%s"', $fileUid)
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
                // Legacy zoom property
                'data-htmlarea-clickenlarge',
            ];

            $imageAttributes = array_diff_key($imageAttributes, array_flip($unsetParams));
        }

        // Add a leading slash if only a path is given
        if (
            is_string($imageSource)
            && strlen($imageSource) > 0
            && strncasecmp($imageSource, 'http', 4) !== 0
            && !str_starts_with($imageSource, '/')
            && !str_starts_with($imageSource, 'data:image')
        ) {
            $imageAttributes['src'] = '/' . $imageSource;
        }

        // Image template; empty attributes are removed by 3rd param 'false'
        $img = '<img ' . GeneralUtility::implodeAttributes($imageAttributes, true) . ' />';

        // Popup rendering (support new `zoom` and legacy `clickenlarge` attributes)
        if (
            (
                isset($imageAttributes['data-htmlarea-zoom'])
                || isset($imageAttributes['data-htmlarea-clickenlarge'])
            )
            && isset($systemImage)
        ) {
            $config = $GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['popup.'] ?? [];
            $config['enable'] = 1;

            $systemImage->updateProperties(
                [
                    'title' => $imageAttributes['title'] ?? $systemImage->getProperty('title') ?? '',
                ]
            );

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
     * Returns the lazy loading configuration.
     *
     * @return string|null
     */
    private function getLazyLoadingConfiguration(): ?string
    {
        return $GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['lazyLoading'] ?? null;
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
            || (str_starts_with($imageSource, '//'));
    }

    /**
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        return GeneralUtility::makeInstance(LogManager::class)
            ->getLogger(static::class);
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
    protected function getAttributeValue(string $attributeName, array $attributes, File $image): string
    {
        return (string)($attributes[$attributeName] ?? $image->getProperty($attributeName));
    }
}
