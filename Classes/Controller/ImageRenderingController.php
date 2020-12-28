<?php
/**
 * See class comment
 *
 * PHP version 7
 *
 * @category   Netresearch
 * @package    RteCKEditor
 * @subpackage Controller
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */


namespace Netresearch\RteCKEditorImage\Controller;

use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use \TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\Service\MagicImageService;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Controller to render the image tag in frontend
 *
 * Copied from
 * @link https://github.com/FriendsOfTYPO3/rtehtmlarea/blob/master/Classes/Controller/ImageRenderingController.php
 *
 * PHP version 7
 *
 * @category   Netresearch
 * @package    RteCKEditor
 * @subpackage Controller
 * @author     Christian Opitz <christian.opitz@netresearch.de>
 * @license    http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link       http://www.netresearch.de
 */
class ImageRenderingController extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin
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
     * Configuration
     *
     * @var array
     */
    public $conf = [];

    /**
     * cObj object
     *
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    public $cObj;

    /**
     * Returns a processed image to be displayed on the Frontend.
     *
     * @param string $content Content input (not used).
     * @param array $conf TypoScript configuration
     * @return string HTML output
     */
    public function renderImageAttributes($content = '', $conf)
    {
        $imageAttributes = $this->getImageAttributes();

        // It is pretty rare to be in presence of an external image as the default behaviour
        // of the RTE is to download the external image and create a local image.
        // However, it may happen if the RTE has the flag "disable"
        if (!$this->isExternalImage()) {

            $fileUid = (int)$imageAttributes['data-htmlarea-file-uid'];
            if ($fileUid) {
                try {
                    $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($fileUid);
                    if ($imageAttributes['src'] !== $file->getPublicUrl()) {
                        // Source file is a processed image
                        $imageConfiguration = [
                            'width' => (int)$imageAttributes['width'],
                            'height' => (int)$imageAttributes['height']
                        ];
                        $processedFile = $this->getMagicImageService()->createMagicImage($file, $imageConfiguration);
                        $additionalAttributes = [
                            'src' => $processedFile->getPublicUrl(),
                            'title' => ($imageAttributes['title']) ? $imageAttributes['title'] : $file->getProperty('title'),
                            'alt' => ($imageAttributes['alt']) ? $imageAttributes['alt'] : $file->getProperty('alternative'),
                            'width' => ($processedFile->getProperty('width')) ? $processedFile->getProperty('width') : $imageConfiguration['width'],
                            'height' => ($processedFile->getProperty('height')) ? $processedFile->getProperty('height') : $imageConfiguration['height'],
                        ];

                        if (!empty($GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['lazyLoading'])) {
                            $additionalAttributes['loading'] = $GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['lazyLoading'];
                        }

                        $imageAttributes = array_merge($imageAttributes, $additionalAttributes);
                    }
                } catch (FileDoesNotExistException $fileDoesNotExistException) {
                    // Log in fact the file could not be retrieved.
                    $message = sprintf('I could not find file with uid "%s"', $fileUid);
                    $this->getLogger()->log(LogLevel::ERROR, $message);
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

        // Image template; empty attributes are removed by 3nd param 'false'
        $img = '<img ' . GeneralUtility::implodeAttributes($imageAttributes, true, false) . ' />';

        // Popup rendering (support new `zoom` and legacy `clickenlarge` attributes)
        if (($imageAttributes['data-htmlarea-zoom'] || $imageAttributes['data-htmlarea-clickenlarge']) && isset($file) && $file) {
            $config = $GLOBALS['TSFE']->tmpl->setup['lib.']['contentElement.']['settings.']['media.']['popup.'];
            $config['enable'] = 1;
            $file->updateProperties(array('title'=>($imageAttributes['title']) ? $imageAttributes['title'] : $file->getProperty('title')));
            $this->cObj->setCurrentFile($file);

            // Use $this->cObject to have access to all parameters from the image tag
            return $this->cObj->imageLinkWrap(
                $img,
                $file,
                $config
            );
        }
        return $img;
    }

    /**
     * Returns a sanitizes array of attributes out of $this->cObj
     *
     * @return array
     */
    protected function getImageAttributes()
    {
        return $this->cObj->parameters;
    }

    /**
     * Instantiates and prepares the Magic Image service.
     *
     * @return \TYPO3\CMS\Core\Resource\Service\MagicImageService
     */
    protected function getMagicImageService()
    {
        /** @var $magicImageService MagicImageService */
        static $magicImageService;
        if (!$magicImageService) {
            $magicImageService = GeneralUtility::makeInstance(MagicImageService::class);
            // Get RTE configuration
            $pageTSConfig = $this->frontendController->getPagesTSconfig();
            if (is_array($pageTSConfig) && is_array($pageTSConfig['RTE.']['default.'])) {
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
    protected function isExternalImage()
    {
        $srcAbsoluteUrl = $this->cObj->parameters['src'];
        return strtolower(substr($srcAbsoluteUrl, 0, 4)) === 'http' || substr($srcAbsoluteUrl, 0, 2) === '//';
    }

    /**
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    private function getLogger()
    {
        /** @var $logManager LogManager */
        $logManager = GeneralUtility::makeInstance(LogManager::class);
        return $logManager->getLogger(get_class($this));
    }
}
