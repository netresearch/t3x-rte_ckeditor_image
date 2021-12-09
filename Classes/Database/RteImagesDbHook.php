<?php
namespace Netresearch\RteCKEditorImage\Database;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Html\RteHtmlParser;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Service\MagicImageService;
use TYPO3\CMS\Core\Type\File\ImageInfo;
use TYPO3\CMS\Backend\Configuration\TypoScript\ConditionMatching\ConditionMatcher;
use TYPO3\CMS\Core\Configuration\Loader\PageTsConfigLoader;
use TYPO3\CMS\Core\Configuration\Parser\PageTsConfigParser;
use TYPO3\CMS\Core\TypoScript\Parser\TypoScriptParser;
use TYPO3\CMS\Extbase\Configuration\BackendConfigurationManager;

/**
 * Class for processing of the FAL soft references on img tags inserted in RTE content
 *
 * Copied from
 * @link https://gitlab.sgalinski.de/typo3/tinymce4_rte/blob/513eeadf8c3c7ffba0936ad63b24e1e9c2eccba7/Classes/Hook/SoftReferenceHook.php
 *
 *
 * @category   Netresearch
 * @package    RteCKEditor
 * @subpackage Database
 * @author     Stefan Galinski <stefan@sgalinski.de>
 * @license    http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link       http://www.netresearch.de
 */


class RteImagesDbHook extends RteHtmlParser
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     *
     *
     * @param array $parameters
     * @param RteHtmlParser $parserObject
     * @return array
     */
    public function transform_rte($value)
    {
        // Split content by <img> tags and traverse the resulting array for processing:
        $imgSplit = $this->splitTags('img', $value);
        if (count($imgSplit) > 1) {
            $siteUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
            $sitePath = str_replace(GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST'), '', $siteUrl);
            foreach ($imgSplit as $k => $v) {
                // Image found
                if ($k % 2) {
                    // Get the attributes of the img tag
                    list($attribArray) = $this->get_tag_attributes($v, true);
                    $absoluteUrl = trim($attribArray['src']);
                    // Transform the src attribute into an absolute url, if it not already
                    if (stripos($absoluteUrl, 'http') !== 0) {
                        // If site is in a subpath (eg. /~user_jim/) this path needs to be removed because it will be added with $siteUrl
                        $attribArray['src'] = preg_replace('#^' . preg_quote($sitePath, '#') . '#', '', $attribArray['src']);
                        $attribArray['src'] = $siteUrl . $attribArray['src'];
                    }
                    // Must have alt attribute
                    if (!isset($attribArray['alt'])) {
                        $attribArray['alt'] = '';
                    }
                    $imgSplit[$k] = '<img ' . GeneralUtility::implodeAttributes($attribArray, true, true) . ' />';
                }
            }
        }
        // Return processed content:
        return implode('', $imgSplit);
    }

    /**
     *
     *
     * @param array $parameters
     * @param RteHtmlParser $parserObject
     * @return array
     */
    public function transform_db($value)
    {
        // Split content by <img> tags and traverse the resulting array for processing:
        $imgSplit = $this->splitTags('img', $value);
        if (count($imgSplit) > 1) {
            $siteUrl = GeneralUtility::getIndpEnv('TYPO3_SITE_URL');
            $sitePath = str_replace(GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST'), '', $siteUrl);
            $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
            $magicImageService = GeneralUtility::makeInstance(MagicImageService::class);
            $pageId = GeneralUtility::makeInstance(BackendConfigurationManager::class)->getDefaultBackendStoragePid();
            $rootLine = BackendUtility::BEgetRootLine($pageId);
            $loader = GeneralUtility::makeInstance(PageTsConfigLoader::class);
            $tsConfigString = $loader->load($rootLine);

            // Parse the PageTS into an array, also applying conditions
            $parser = GeneralUtility::makeInstance(
                PageTsConfigParser::class,
                GeneralUtility::makeInstance(TypoScriptParser::class),
                GeneralUtility::makeInstance(CacheManager::class)->getCache('hash')
            );
            $matcher = GeneralUtility::makeInstance(ConditionMatcher::class, null, $pageId, $rootLine);
            $tsConfig = $parser->parse($tsConfigString, $matcher);
            $magicImageService->setMagicImageMaximumDimensions($tsConfig['RTE.']['default.']);

            foreach ($imgSplit as $k => $v) {
                // Image found, do processing:
                if ($k % 2) {
                    // Get attributes
                    list($attribArray) = $this->get_tag_attributes($v, true);
                    // It's always an absolute URL coming from the RTE into the Database.
                    $absoluteUrl = trim($attribArray['src']);
                    // Make path absolute if it is relative and we have a site path which is not '/'
                    $pI = pathinfo($absoluteUrl);
                    if ($sitePath && !$pI['scheme'] && GeneralUtility::isFirstPartOfStr($absoluteUrl, $sitePath)) {
                        // If site is in a subpath (eg. /~user_jim/) this path needs to be removed because it will be added with $siteUrl
                        $absoluteUrl = substr($absoluteUrl, strlen($sitePath));
                        $absoluteUrl = $siteUrl . $absoluteUrl;
                    }
                    // Image dimensions set in the img tag, if any
                    $imgTagDimensions = $this->getWHFromAttribs($attribArray);
                    if ($imgTagDimensions[0]) {
                        $attribArray['width'] = $imgTagDimensions[0];
                    }
                    if ($imgTagDimensions[1]) {
                        $attribArray['height'] = $imgTagDimensions[1];
                    }
                    $originalImageFile = null;
                    if ($attribArray['data-htmlarea-file-uid']) {
                        // An original image file uid is available
                        try {
                            $originalImageFile = $resourceFactory->getFileObject((int)$attribArray['data-htmlarea-file-uid']);
                        } catch (FileDoesNotExistException $fileDoesNotExistException) {
                            // Log the fact the file could not be retrieved.
                            $message = sprintf('Could not find file with uid "%s"', $attribArray['data-htmlarea-file-uid']);
                            $this->logger->error($message);
                        }
                    }
                    if ($originalImageFile instanceof File) {
                        // Public url of local file is relative to the site url, absolute otherwise
                        if ($absoluteUrl == $originalImageFile->getPublicUrl() || $absoluteUrl == $siteUrl . $originalImageFile->getPublicUrl()) {
                            // This is a plain image, i.e. reference to the original image
                            if ($this->procOptions['plainImageMode']) {
                                // "plain image mode" is configured
                                // Find the dimensions of the original image
                                $imageInfo = [
                                    $originalImageFile->getProperty('width'),
                                    $originalImageFile->getProperty('height')
                                ];
                                if (!$imageInfo[0] || !$imageInfo[1]) {
                                    $filePath = $originalImageFile->getForLocalProcessing(false);
                                    $imageInfoObject = GeneralUtility::makeInstance(ImageInfo::class, $filePath);
                                    $imageInfo = [
                                        $imageInfoObject->getWidth(),
                                        $imageInfoObject->getHeight()
                                    ];
                                }
                                $attribArray = $this->applyPlainImageModeSettings($imageInfo, $attribArray);
                            }
                        } else {
                            // Magic image case: get a processed file with the requested configuration
                            $imageConfiguration = [
                                'width' => $imgTagDimensions[0],
                                'height' => $imgTagDimensions[1]
                            ];
                            $magicImage = $magicImageService->createMagicImage($originalImageFile, $imageConfiguration);
                            $attribArray['width'] = $magicImage->getProperty('width');
                            $attribArray['height'] = $magicImage->getProperty('height');

                            $imgSrc = $magicImage->getPublicUrl();

                            // publicUrl like 'https://www.domain.xy/typo3/image/process?token=...'?
                            // -> generate img source from storage basepath and identifier instead
                            if (strpos($imgSrc, 'process?token=') !== false) {
                                $storageBasePath = $magicImage->getStorage()->getConfiguration()['basePath'];
                                $imgUrlPre = (substr($storageBasePath, -1, 1) === '/') ? substr($storageBasePath, 0, -1) : $storageBasePath;

                                $imgSrc = '/' . $imgUrlPre . $magicImage->getIdentifier();
                            }

                            $attribArray['src'] = $imgSrc;
                        }
                    } elseif (!GeneralUtility::isFirstPartOfStr($absoluteUrl, $siteUrl) && !$this->procOptions['dontFetchExtPictures'] && TYPO3_MODE === 'BE') {
                        // External image from another URL: in that case, fetch image, unless the feature is disabled or we are not in backend mode
                        // Fetch the external image
                        $externalFile = GeneralUtility::getUrl($absoluteUrl);
                        if ($externalFile) {
                            $pU = parse_url($absoluteUrl);
                            $pI = pathinfo($pU['path']);
                            $extension = strtolower($pI['extension']);
                            if ($extension === 'jpg' || $extension === 'jpeg' || $extension === 'gif' || $extension === 'png') {
                                $fileName = GeneralUtility::shortMD5($absoluteUrl) . '.' . $pI['extension'];
                                // We insert this image into the user default upload folder
                                list($table, $field) = explode(':', $this->elRef);
                                $folder = $GLOBALS['BE_USER']->getDefaultUploadFolder($this->recPid, $table, $field);
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
                        if ($filepath && @is_file($filepath)) {
                            // Treat it as a plain image
                            if ($this->procOptions['plainImageMode']) {
                                // If "plain image mode" has been configured
                                // Find the original dimensions of the image
                                $imageInfoObject = GeneralUtility::makeInstance(ImageInfo::class, $filepath);
                                $imageInfo = [
                                    $imageInfoObject->getWidth(),
                                    $imageInfoObject->getHeight()
                                ];
                                $attribArray = $this->applyPlainImageModeSettings($imageInfo, $attribArray);
                            }
                            // Let's try to find a file uid for this image
                            try {
                                $fileOrFolderObject = $resourceFactory->retrieveFileOrFolderObject($path);
                                if ($fileOrFolderObject instanceof FileInterface) {
                                    $fileIdentifier = $fileOrFolderObject->getIdentifier();
                                    $fileObject = $fileOrFolderObject->getStorage()->getFile($fileIdentifier);
                                    $fileUid = $fileObject->getUid();
                                    // if the retrieved file is a processed file, get the original file...
                                    if($fileObject->hasProperty('original')){
                                        $fileUid = $fileObject->getProperty('original');
                                    }
                                    $attribArray['data-htmlarea-file-uid'] = $fileUid;
                                }
                            } catch (ResourceDoesNotExistException $resourceDoesNotExistException) {
                                // Nothing to be done if file/folder not found
                            }
                        }
                    }
                    // Remove width and height from style attribute
                    $attribArray['style'] = preg_replace('/(?:^|[^-])(\\s*(?:width|height)\\s*:[^;]*(?:$|;))/si', '', $attribArray['style']);
                    // Must have alt attribute
                    if (!isset($attribArray['alt'])) {
                        $attribArray['alt'] = '';
                    }
                    // Convert absolute to relative url
                    if (GeneralUtility::isFirstPartOfStr($attribArray['src'], $siteUrl)) {
                        $attribArray['src'] = substr($attribArray['src'], strlen($siteUrl));
                    }
                    $imgSplit[$k] = '<img ' . GeneralUtility::implodeAttributes($attribArray, true, true) . ' />';
                }
            }
        }
        return implode('', $imgSplit);
    }

    /**
     * Finds width and height from attrib-array
     * If the width and height is found in the style-attribute, use that!
     *
     * @param array $attribArray Array of attributes from tag in which to search. More specifically the content of the key "style" is used to extract "width:xxx / height:xxx" information
     * @return array Integer w/h in key 0/1. Zero is returned if not found.
     */
    protected function getWHFromAttribs($attribArray)
    {
        $style = trim($attribArray['style']);
        $w = 0;
        $h = 0;
        if ($style) {
            $regex = '[[:space:]]*:[[:space:]]*([0-9]*)[[:space:]]*px';
            // Width
            $reg = [];
            preg_match('/width' . $regex . '/i', $style, $reg);
            $w = (int)$reg[1];
            // Height
            preg_match('/height' . $regex . '/i', $style, $reg);
            $h = (int)$reg[1];
        }
        if (!$w) {
            $w = $attribArray['width'];
        }
        if (!$h) {
            $h = $attribArray['height'];
        }
        return [(int)$w, (int)$h];
    }
}
