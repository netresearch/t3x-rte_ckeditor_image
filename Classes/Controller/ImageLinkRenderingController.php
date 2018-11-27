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
        $document->loadHTML($linkContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        // Get all images
        $parsedImages = $document->getElementsByTagName('img');

        if (!$parsedImages) {
            return $linkContent;
        }

        foreach ($parsedImages as $parsedImage) {
            $parsedImageId = $parsedImage->getAttribute('data-htmlarea-file-uid');
            if ($parsedImageId) {
                try {
                    $systemImage = Resource\ResourceFactory::getInstance()->getFileObject($parsedImageId);
                    $parsedTitle = $parsedImage->getAttribute('title');
                    $parsedAlt = $parsedImage->getAttribute('alt');
                    $parsedStyle = $parsedImage->getAttribute('style');

                    // Get parsed attributes or fallback
                    $finalTitle = ($parsedTitle) ? $parsedTitle : $systemImage->getProperty('title');
                    $finalAlttext = ($parsedAlt) ? $parsedAlt : $systemImage->getProperty('alternative');
                    // Set final attributes
                    $parsedImage->setAttribute('title', $finalTitle);
                    $parsedImage->setAttribute('alt', $finalAlttext);
                    // Remove empty style attr
                    if (!$parsedStyle) {
                        $parsedImage->removeAttribute('style');
                    }

                } catch (Resource\Exception\FileDoesNotExistException $fileDoesNotExistException) {
                    // Log in fact the file could not be retrieved.
                    $message = sprintf('I could not find file with uid "%s"', $parsedImageId);
                    $this->getLogger()->error($message);
                }
            }
        }
        $linkContent = $document->saveHTML();
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
