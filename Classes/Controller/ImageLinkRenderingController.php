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
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Controller to render the linked images in frontend.
 *
 * @author  Mathias Uhlmann <mathias.uhlmann@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class ImageLinkRenderingController
{
    /**
     * Same as class name.
     *
     * @var string
     */
    public $prefixId = 'ImageLinkRenderingController';

    /**
     * Path to this script relative to the extension dir.
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

    protected ?ContentObjectRenderer $cObj = null;

    public function setContentObjectRenderer(
        ContentObjectRenderer $cObj,
    ): void {
        $this->cObj = $cObj;
    }

    /**
     * Returns a processed image to be displayed on the Frontend.
     *
     * @param string|null $content Content input (not used)
     * @param mixed[]     $conf    TypoScript configuration
     *
     * @return string HTML output
     */
    public function renderImages(?string $content, array $conf, ServerRequestInterface $request): string
    {
        // Get link inner HTML
        $linkContent = $this->cObj instanceof ContentObjectRenderer ? $this->cObj->getCurrentVal() : null;

        // Find all images with file-uid attribute
        // SECURITY: Use atomic groups to prevent ReDoS attacks via catastrophic backtracking
        $imgSearchPattern = '/<p[^>]*+>\s*+<img(?>[^>]*)src(?>[^>]*)\/>\s*+<\/p>/';
        $passedImages     = [];
        $parsedImages     = [];

        // SECURITY: Set PCRE limits to prevent ReDoS
        ini_set('pcre.backtrack_limit', '100000');
        ini_set('pcre.recursion_limit', '100000');

        // Extract all TYPO3 images from link content
        preg_match_all($imgSearchPattern, (string) $linkContent, $passedImages);

        $passedImages = $passedImages[0];

        if ($passedImages === []) {
            return $linkContent;
        }

        foreach ($passedImages as $passedImage) {
            $imageAttributes = $this->getImageAttributes($passedImage);

            // The image is already parsed by netresearch linkrenderer, which removes custom attributes,
            // so it will never match this condition.
            //
            // But we leave this as fallback for older render versions.
            if (($imageAttributes !== []) && isset($imageAttributes['data-htmlarea-file-uid'])) {
                $fileUid = (int) $imageAttributes['data-htmlarea-file-uid'];

                if ($fileUid > 0) {
                    try {
                        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
                        $systemImage     = $resourceFactory->getFileObject($fileUid);

                        // SECURITY: Prevent privilege escalation by checking file visibility
                        // Only process public files in frontend rendering. Non-public files must
                        // use TYPO3's protected file delivery (eID_dumpFile) which performs
                        // proper authentication checks for the current frontend user.
                        // This prevents low-privilege backend editors from exposing files
                        // outside their Filemount restrictions by manipulating file UIDs.
                        if (!$systemImage->getStorage()->isPublic()) {
                            $this->getLogger()->log(
                                PsrLogLevel::WARNING,
                                'Blocked rendering of non-public file in linked image context',
                                [
                                    'fileUid'     => $fileUid,
                                    'storage'     => $systemImage->getStorage()->getUid(),
                                    'storageName' => $systemImage->getStorage()->getName(),
                                ],
                            );

                            // Skip processing and continue with next image
                            throw new FileDoesNotExistException();
                        }

                        $imageConfiguration = [
                            'width'  => (int) ($imageAttributes['width'] ?? $systemImage->getProperty('width') ?? 0),
                            'height' => (int) ($imageAttributes['height'] ?? $systemImage->getProperty('height') ?? 0),
                        ];

                        $processedFile = $systemImage->process(
                            ProcessedFile::CONTEXT_IMAGECROPSCALEMASK,
                            $imageConfiguration,
                        );

                        $additionalAttributes = [
                            'src'    => $processedFile->getPublicUrl(),
                            'title'  => $this->getAttributeValue('title', $imageAttributes, $systemImage),
                            'alt'    => $this->getAttributeValue('alt', $imageAttributes, $systemImage),
                            'width'  => $processedFile->getProperty('width') ?? $imageConfiguration['width'],
                            'height' => $processedFile->getProperty('height') ?? $imageConfiguration['height'],
                        ];

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

                        // Cleanup attributes; disable zoom images within links
                        $unsetParams = [
                            'data-htmlarea-file-uid',
                            'data-htmlarea-file-table',
                            'data-htmlarea-zoom',
                            'data-htmlarea-clickenlarge', // Legacy zoom property
                        ];

                        $imageAttributes = array_diff_key($imageAttributes, array_flip($unsetParams));

                        // Ensure all attributes are strings for implodeAttributes
                        $stringAttributes = array_map(fn ($value): string => (string) $value, $imageAttributes);

                        // Image template; empty attributes are removed by 3rd param 'false'
                        $parsedImages[] = '<img ' . GeneralUtility::implodeAttributes($stringAttributes, true) . ' />';
                    } catch (FileDoesNotExistException) {
                        $parsedImages[] = strip_tags($passedImage, '<img>');

                        // SECURITY: Don't expose file UIDs in error messages (information disclosure)
                        // Move sensitive data to structured context array instead
                        $this->getLogger()->log(
                            PsrLogLevel::ERROR,
                            'Unable to find requested file',
                            ['fileUid' => $fileUid],
                        );
                    }
                }
            } else {
                $parsedImages[] = strip_tags($passedImage, '<img>');
            }
        }

        // Replace original images with parsed
        $result = str_replace($passedImages, $parsedImages, $linkContent);

        return is_string($result) ? $result : (string) $linkContent;
    }

    /**
     * Returns a sanitizes array of attributes out $passedImage.
     *
     * @param string $passedImage
     *
     * @return string[]
     */
    protected function getImageAttributes(string $passedImage): array
    {
        // Get image attributes
        // SECURITY: Use atomic groups to prevent ReDoS attacks
        // Use PREG_SET_ORDER to get matched pairs and avoid array_combine() mismatch issues
        preg_match_all(
            '/([a-zA-Z0-9-]++)=["]([^"]*)"|([a-zA-Z0-9-]++)=[\']([^\']*)\'/',
            $passedImage,
            $imageAttributes,
            PREG_SET_ORDER,
        );

        $attributes = [];
        foreach ($imageAttributes as $match) {
            // $match[1] and $match[2] are for double quotes, $match[3] and $match[4] are for single quotes
            // When double-quoted: $match[1] = name, $match[2] = value, $match[3] = '', $match[4] = ''
            // When single-quoted: $match[1] = '', $match[2] = '', $match[3] = name, $match[4] = value
            $key              = ($match[1] ?? '') !== '' ? $match[1] : ($match[3] ?? '');
            $value            = ($match[1] ?? '') !== '' ? ($match[2] ?? '') : ($match[4] ?? '');
            $attributes[$key] = $value;
        }

        return $attributes;
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
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        return GeneralUtility::makeInstance(LogManager::class)
            ->getLogger(static::class);
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
}
