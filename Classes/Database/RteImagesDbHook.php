<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Database;

use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching\ConditionMatcher;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\Loader\PageTsConfigLoader;
use TYPO3\CMS\Core\Configuration\Parser\PageTsConfigParser;
use TYPO3\CMS\Core\Html\RteHtmlParser;
use TYPO3\CMS\Core\Resource\AbstractFile;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Service\MagicImageService;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;

/**
 * Class for processing of the FAL soft references on img tags inserted in RTE content
 *
 * @author  Stefan Galinski <stefan@sgalinski.de>
 * @license http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link    http://www.netresearch.de
 *
 * @deprecated in TYPO3 v12
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ApiOverview/Rte/Transformations/CustomApi.html
 * @see https://docs.typo3.org/m/typo3/reference-tsconfig/10.4/en-us/PageTsconfig/Rte.html#pagetsrte
 */
class RteImagesDbHook extends RteHtmlParser
{
    /**
     * @var bool
     */
    protected bool $fetchExternalImages;

    /**
     * Constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @param ExtensionConfiguration   $extensionConfiguration
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ExtensionConfiguration $extensionConfiguration
    ) {
        parent::__construct($eventDispatcher);

        $this->fetchExternalImages = (bool) $extensionConfiguration->get('rte_ckeditor_image', 'fetchExternalImages');
    }

    /**
     *
     *
     * @param string $value
     *
     * @return string
     */
    public function transform_rte(string $value): string
    {
        // Split content by <img> tags and traverse the resulting array for processing:

        /** @var array<int, string> $imgSplit */
        $imgSplit = $this->splitTags('img', $value);

        if (count($imgSplit) > 1) {
            $siteUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
            $sitePath = str_replace(GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST'), '', $siteUrl);

            foreach ($imgSplit as $key => $v) {
                // Image found
                if (($key % 2) === 1) {
                    // Get the attributes of the img tag
                    [$attribArray] = $this->get_tag_attributes($v, true);
                    $absoluteUrl = trim($attribArray['src']);
                    // Transform the src attribute into an absolute url, if it not already
                    if (stripos($absoluteUrl, 'http') !== 0) {
                        // If site is in a sub path (e.g. /~user_jim/) this path needs to be removed because it will be added with $siteUrl
                        $attribArray['src'] = preg_replace('#^' . preg_quote($sitePath, '#') . '#', '', $attribArray['src']);
                        $attribArray['src'] = $siteUrl . $attribArray['src'];
                    }
                    // Must have alt attribute
                    if (!isset($attribArray['alt'])) {
                        $attribArray['alt'] = '';
                    }
                    $imgSplit[$key] = '<img ' . GeneralUtility::implodeAttributes($attribArray, true, true) . ' />';
                }
            }
        }
        // Return processed content:
        return implode('', $imgSplit);
    }

    /**
     *
     *
     * @param string $value
     *
     * @return string
     *
     * @throws NoSuchCacheException
     */
    public function transform_db(string $value): string
    {
        // Split content by <img> tags and traverse the resulting array for processing:
        $imgSplit = $this->splitTags('img', $value);
        if (count($imgSplit) > 1) {
            $siteUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');

            /** @var string $sitePath */
            $sitePath = str_replace(GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST'), '', $siteUrl);

            /** @var ResourceFactory $resourceFactory */
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

            /** @var MagicImageService $magicImageService */
            $magicImageService = GeneralUtility::makeInstance(MagicImageService::class);

            /** @var BackendConfigurationManager $backendConfigurationManager */
            $backendConfigurationManager = GeneralUtility::makeInstance(BackendConfigurationManager::class);

            $pageId = $backendConfigurationManager->getDefaultBackendStoragePid();
            $rootLine = BackendUtility::BEgetRootLine($pageId);

            /** @var PageTsConfigLoader $loader */
            $loader = GeneralUtility::makeInstance(PageTsConfigLoader::class);

            /** @var CacheManager $cacheManager */
            $cacheManager = GeneralUtility::makeInstance(CacheManager::class);

            /** @var TypoScriptParser $typoScriptParser */
            $typoScriptParser = GeneralUtility::makeInstance(TypoScriptParser::class);

            $tsConfigString = $loader->load($rootLine);

            // Parse the PageTS into an array, also applying conditions

            /** @var PageTsConfigParser $parser */
            $parser = GeneralUtility::makeInstance(
                PageTsConfigParser::class,
                $typoScriptParser,
                $cacheManager->getCache('hash')
            );

            /** @var ConditionMatcher $matcher */
            $matcher = GeneralUtility::makeInstance(ConditionMatcher::class, null, $pageId, $rootLine);

            $tsConfig = $parser->parse($tsConfigString, $matcher);
            $magicImageService->setMagicImageMaximumDimensions($tsConfig['RTE.']['default.']);

            foreach ($imgSplit as $key => $v) {
                // Image found, do processing:
                if (($key % 2) === 1) {
                    // Get attributes
                    [$attribArray] = $this->get_tag_attributes($v, true);
                    // It's always an absolute URL coming from the RTE into the Database.
                    $absoluteUrl = trim($attribArray['src']);
                    // Make path absolute if it is relative, and we have a site path which is not '/'
                    $pI = pathinfo($absoluteUrl);

                    if (($sitePath !== '') && GeneralUtility::isFirstPartOfStr($absoluteUrl, $sitePath)) {
                        // If site is in a subpath (e.g. /~user_jim/) this path needs to be removed because it will be added with $siteUrl
                        $absoluteUrl = substr($absoluteUrl, strlen($sitePath));
                        $absoluteUrl = $siteUrl . $absoluteUrl;
                    }

                    // Get image dimensions set in the image tag, if any
                    $imageWidth  = $this->getImageWidthFromAttributes($attribArray);
                    $imageHeight = $this->getImageHeightFromAttributes($attribArray);

                    if ($imageWidth > 0) {
                        $attribArray['width'] = $imageWidth;
                    }

                    if ($imageHeight > 0) {
                        $attribArray['height'] = $imageHeight;
                    }

                    $originalImageFile = null;
                    if ($attribArray['data-htmlarea-file-uid'] ?? false) {
                        // An original image file uid is available
                        try {
                            $originalImageFile = $resourceFactory->getFileObject((int)$attribArray['data-htmlarea-file-uid']);
                        } catch (FileDoesNotExistException $fileDoesNotExistException) {
                            if ($this->logger !== null) {
                                // Log the fact the file could not be retrieved.
                                $message = sprintf('Could not find file with uid "%s"', $attribArray['data-htmlarea-file-uid']);
                                $this->logger->error($message);
                            }
                        }
                    }
                    if ($originalImageFile instanceof File) {
                        // Build public URL to image, remove trailing slash from site URL
                        $imageFileUrl = rtrim($siteUrl, '/') . $originalImageFile->getPublicUrl();

                        // Public url of local file is relative to the site url, absolute otherwise
                        if (($absoluteUrl !== $imageFileUrl) && ($absoluteUrl !== $originalImageFile->getPublicUrl())) {
                            // Magic image case: get a processed file with the requested configuration
                            $imageConfiguration = [
                                'width' => $imageWidth,
                                'height' => $imageHeight,
                            ];
                            $magicImage = $magicImageService->createMagicImage($originalImageFile, $imageConfiguration);
                            $attribArray['width'] = $magicImage->getProperty('width');
                            $attribArray['height'] = $magicImage->getProperty('height');

                            $imgSrc = $magicImage->getPublicUrl();

                            // publicUrl like 'https://www.domain.xy/typo3/image/process?token=...'?
                            // -> generate img source from storage basepath and identifier instead
                            if ($imgSrc !== null && strpos($imgSrc, 'process?token=') !== false) {
                                $storageBasePath = $magicImage->getStorage()->getConfiguration()['basePath'];
                                $imgUrlPre = (substr($storageBasePath, -1, 1) === '/') ? substr($storageBasePath, 0, -1) : $storageBasePath;

                                $imgSrc = '/' . $imgUrlPre . $magicImage->getIdentifier();
                            }

                            $attribArray['src'] = $imgSrc;
                        }
                    } elseif (!GeneralUtility::isFirstPartOfStr($absoluteUrl, $siteUrl)
                        && !($this->procOptions['dontFetchExtPictures'] ?? false)
                        && TYPO3_MODE === 'BE'
                        && $this->fetchExternalImages
                    ) {
                        // External image from another URL: in that case, fetch image, unless the feature is disabled, or we are not in backend mode
                        // Fetch the external image
                        $externalFile = null;
                        try {
                            $externalFile = GeneralUtility::getUrl($absoluteUrl);
                        } catch (Throwable $e) {
                            // do nothing, further image processing will be skipped
                        }
                        if ($externalFile !== null) {
                            $pU = parse_url($absoluteUrl);
                            $path = is_array($pU) ? ($pU['path'] ?? '') : '';
                            $pI = pathinfo($path);
                            $extension = strtolower($pI['extension'] ?? '');
                            if ($extension === 'jpg' || $extension === 'jpeg' || $extension === 'gif' || $extension === 'png') {
                                $fileName = GeneralUtility::shortMD5($absoluteUrl) . '.' . ($pI['extension'] ?? '');
                                // We insert this image into the user default upload folder
                                $folder = $GLOBALS['BE_USER']->getDefaultUploadFolder();
                                $fileObject = $folder->createFile($fileName)->setContents($externalFile);
                                $imageConfiguration = [
                                    'width' => $attribArray['width'],
                                    'height' => $attribArray['height']
                                ];
                                $magicImage = $magicImageService->createMagicImage($fileObject, $imageConfiguration);
                                $attribArray['width'] = $magicImage->getProperty('width');
                                $attribArray['height'] = $magicImage->getProperty('height');
                                $attribArray['data-htmlarea-file-uid'] = $fileObject->getUid();
                                $attribArray['src'] = $magicImage->getPublicUrl();
                            }
                        }
                    } elseif (GeneralUtility::isFirstPartOfStr($absoluteUrl, $siteUrl)) {
                        // Finally, check image as local file (siteURL equals the one of the image)
                        // Image has no data-htmlarea-file-uid attribute
                        // Relative path, rawurldecoded for special characters.
                        $path = rawurldecode(substr($absoluteUrl, strlen($siteUrl)));
                        // Absolute filepath, locked to relative path of this project
                        $filepath = GeneralUtility::getFileAbsFileName($path);
                        // Check file existence (in relative directory to this installation!)
                        if (($filepath !== '') && @is_file($filepath)) {
                            // Let's try to find a file uid for this image
                            try {
                                $fileOrFolderObject = $resourceFactory->retrieveFileOrFolderObject($path);
                                if ($fileOrFolderObject instanceof FileInterface) {
                                    $fileIdentifier = $fileOrFolderObject->getIdentifier();
                                    $fileObject = $fileOrFolderObject->getStorage()->getFile($fileIdentifier);
                                    if ($fileObject instanceof AbstractFile) {
                                        $fileUid = $fileObject->getUid();
                                        // if the retrieved file is a processed file, get the original file...
                                        if($fileObject->hasProperty('original')){
                                            $fileUid = $fileObject->getProperty('original');
                                        }
                                        $attribArray['data-htmlarea-file-uid'] = $fileUid;
                                    }
                                }
                            } catch (ResourceDoesNotExistException $resourceDoesNotExistException) {
                                // Nothing to be done if file/folder not found
                            }
                        }
                    }
                    if (!$attribArray) {
                        // some error occurred, leave the img tag as is
                        continue;
                    }
                    // Remove width and height from style attribute
                    $attribArray['style'] = preg_replace('/(?:^|[^-])(\\s*(?:width|height)\\s*:[^;]*(?:$|;))/si', '', $attribArray['style'] ?? "");
                    // Must have alt attribute
                    if (!isset($attribArray['alt'])) {
                        $attribArray['alt'] = '';
                    }
                    // Convert absolute to relative url
                    if (GeneralUtility::isFirstPartOfStr($attribArray['src'], $siteUrl)) {
                        $attribArray['src'] = substr($attribArray['src'], strlen($siteUrl));
                    }
                    $imgSplit[$key] = '<img ' . GeneralUtility::implodeAttributes($attribArray, true, true) . ' />';
                }
            }
        }
        return implode('', $imgSplit);
    }

    /**
     * Finds width and height from attrib-array
     * If the width and height is found in the style-attribute, use that!
     *
     * @param string $styleAttribute The image style attribute
     * @param string $imageAttribute The image attribute to match in the style attribute (e.g. width, height)
     *
     * @return null|mixed
     */
    private function matchStyleAttribute(string $styleAttribute, string $imageAttribute)
    {
        $regex   = '[[:space:]]*:[[:space:]]*([0-9]*)[[:space:]]*px';
        $matches = [];

        if (preg_match('/' . $imageAttribute . $regex . '/i', $styleAttribute, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extracts the given image attribute value from the image tag attributes. If a style attribute
     * exists the information is extracted from this one.
     *
     * @param string[] $attributes     The attributes of the image tag
     * @param string   $imageAttribute The image attribute to extract (e.g. width, height)
     *
     * @return null|mixed
     */
    private function extractFromAttributeValueOrStyle(array $attributes, string $imageAttribute)
    {
        $style = trim($attributes['style'] ?? '');

        if ($style !== '') {
            $value = $this->matchStyleAttribute($style, $imageAttribute);

            // Return value from style attribute
            if ($value !== null) {
                return $value;
            }
        }

        // Returns value from tag attributes
        return $attributes[$imageAttribute] ?? null;
    }

    /**
     * Returns the width of the image from the image tag attributes. If a style attribute exists
     * the information is extracted from this one.
     *
     * @param string[] $attributes The attributes of the image tag
     *
     * @return int
     */
    private function getImageWidthFromAttributes(array $attributes): int
    {
        return (int) $this->extractFromAttributeValueOrStyle($attributes, 'width');
    }

    /**
     * Returns the height of the image from the image tag attributes. If a style attribute exists
     * the information is extracted from this one.
     *
     * @param string[] $attributes The attributes of the image tag
     *
     * @return int
     */
    private function getImageHeightFromAttributes(array $attributes): int
    {
        return (int) $this->extractFromAttributeValueOrStyle($attributes, 'height');
    }
}
