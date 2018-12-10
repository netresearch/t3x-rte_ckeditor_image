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
        $imgSearchPattern = '/<img(?=.*data-htmlarea-file-uid=)(.*?)\/>/'; // Images with data-uid
        //$imgSearchPattern = '(</?img[^>]*\>)i';  // All images
        $attrPattern = '/(\w+)=[\'"]([^\'"]*)/';
        $passedImages = array();
        $parsedImages = array();

        // Extract all images from link content
        preg_match_all($imgSearchPattern, $linkContent, $passedImages);
        $passedImages = $passedImages[0];

        if (count($passedImages) === 0) {
            return $linkContent;
        }

        foreach ($passedImages as $passedImage) {
            // Get image attributes
            preg_match_all($attrPattern, $passedImage, $passedAttributes);
            $passedAttributes = array_combine($passedAttributes[1], $passedAttributes[2]);
            // Remove empty values
            $passedAttributes = array_diff( $passedAttributes, array(''));

            if ($passedAttributes['uid']) {
                try {
                    $systemImage = Resource\ResourceFactory::getInstance()->getFileObject($passedAttributes['uid']);
                    $imageAttributes = [
                        'src' => $passedAttributes['src'],
                        'title' => ($passedAttributes['title']) ? $passedAttributes['title'] : $systemImage->getProperty('title'),
                        'alt' => ($passedAttributes['alt']) ? $passedAttributes['alt'] : $systemImage->getProperty('alternative'),
                        'width' => ($passedAttributes['width']) ? $passedAttributes['width'] : $systemImage->getProperty('width'),
                        'height' => ($passedAttributes['height']) ? $passedAttributes['height'] : $systemImage->getProperty('height'),
                        'style' => ($passedAttributes['style']) ? $passedAttributes['style'] : $systemImage->getProperty('style'),
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
                    $parsedImage = '<img ' . GeneralUtility::implodeAttributes($imageAttributes, true, true) . ' />';
                    $parsedImages[] = $parsedImage;

                } catch (Resource\Exception\FileDoesNotExistException $fileDoesNotExistException) {
                    $parsedImages[] = '';
                    // Log in fact the file could not be retrieved.
                    $message = sprintf('I could not find file with uid "%s"', $passedAttributes['uid']);
                    $this->getLogger()->error($message);
                }
            }
        }
        // Replace original images with parsed
        $linkContent = str_replace($passedImages, $parsedImages, $linkContent);

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
