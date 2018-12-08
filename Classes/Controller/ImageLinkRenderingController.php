<?php

namespace Netresearch\RteCKEditorImage\Controller;

use \TYPO3\CMS\Core\Resource;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Controller to render the linked images in frontend
 *
 * PHP version 7
 *
 * @category   Netresearch
 * @package    RteCKEditor
 * @subpackage Controller
 * @author     Mathias Uhlmann <mathias.uhlmann@netresearch.de>
 * @license    http://www.gnu.de/documents/gpl-2.0.de.html GPL 2.0+
 * @link       http://www.netresearch.de
 */
class ImageLinkRenderingController extends \TYPO3\CMS\Frontend\Plugin\AbstractPlugin
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
     * @param array $conf TypoScript configuration
     * @return string HTML output
     */
    public function renderImages($conf)
    {
        // Get link inner HTML
        $linkContent = $this->cObj->getCurrentVal();
        $document = new \DomDocument;
        // Transform content to DOM elements without html and body tags
        $document->loadHTML(mb_convert_encoding($linkContent, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        // Get all images
        $parsedImages = $document->getElementsByTagName('img');
        if ($parsedImages->length === 0) {
            return $linkContent;
        }
        foreach ($parsedImages as $parsedImage) {
            $parsedImageId = $parsedImage->getAttribute('data-htmlarea-file-uid');
            $imgSearchPattern = '/<img(?=.*data-htmlarea-file-uid="' . $parsedImageId . '")(.*?)\/>/';

            if ($parsedImageId && preg_match($imgSearchPattern, $linkContent)) {
                try {
                    $systemImage = Resource\ResourceFactory::getInstance()->getFileObject($parsedImageId);
                    $parsedTitle = $parsedImage->getAttribute('title');
                    $parsedAlt = $parsedImage->getAttribute('alt');
                    $imageAttributes = [
                        'src' => $parsedImage->getAttribute('src'),
                        'title' => ($parsedTitle) ? $parsedTitle : $systemImage->getProperty('title'),
                        'alt' => ($parsedAlt) ? $parsedAlt : $systemImage->getProperty('alternative'),
                        'width' => $parsedImage->getAttribute('width'),
                        'height' => $parsedImage->getAttribute('height'),
                        'style' => $parsedImage->getAttribute('style')
                    ];
                    // Cleanup attributes
                    $unsetParams = [
                        'allParams',
                        'data-htmlarea-file-uid',
                        'data-htmlarea-file-table',
                        'data-htmlarea-zoom'
                    ];
                    $imageAttributes = array_diff_key($imageAttributes, array_flip($unsetParams));
                    // Remove empty values
                    $imageAttributes = array_diff( $imageAttributes, array(''));
                    // Image template
                    $img = '<img ' . GeneralUtility::implodeAttributes($imageAttributes, true, true) . ' />';
                    // Replace image
                    $linkContent = preg_replace($imgSearchPattern, $img, $linkContent);

                } catch (Resource\Exception\FileDoesNotExistException $fileDoesNotExistException) {
                    // Log in fact the file could not be retrieved.
                    $message = sprintf('I could not find file with uid "%s"', $parsedImageId);
                    $this->getLogger()->error($message);
                }
            }
        }
        return $linkContent;
    }

    /**
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    protected function getLogger()
    {
        /** @var $logManager \TYPO3\CMS\Core\Log\LogManager */
        $logManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class);
        return $logManager->getLogger(get_class($this));
    }
}
