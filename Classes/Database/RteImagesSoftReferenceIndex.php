<?php
namespace Netresearch\RteCKEditorImage\Database;

use TYPO3\CMS\Core\Database\SoftReferenceIndex;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
class RteImagesSoftReferenceIndex extends SoftReferenceIndex
{
    /**
     * Token prefix
     */
    public $tokenID_basePrefix = '';

    /**
     * Content splitted into images and other elements
     */
    public $splittedContentTags = array();

    /**
     * TYPO3 HTML Parser
     */
    public $htmlParser;

    /**
     * Main function through which all processing happens
     *
     * @param string $table         Database table name
     * @param string $field         Field name for which processing occurs
     * @param int    $uid           UID of the record
     * @param string $content       The content/value of the field
     * @param string $spKey         The softlink parser key. This is only interesting if more than one parser is grouped in the same class. That is the case with this parser.
     * @param array  $spParams      Parameters of the softlink parser. Basically this is the content inside optional []-brackets after the softref keys. Parameters are exploded by ";
     * @param string $structurePath If running from inside a FlexForm structure, this is the path of the tag.
     *
     * @return array|boolean Result array on positive matches. Otherwise FALSE
     */
    public function findRef($table, $field, $uid, $content, $spKey, $spParams, $structurePath = '')
    {
        $this->tokenID_basePrefix = $table . ':' . $uid . ':' . $field . ':' . $structurePath . ':' . $spKey;

        switch ($spKey) {
            case 'rtehtmlarea_images':
                $retVal = $this->findRef_rtehtmlarea_images($content);
                break;
            default:
                $retVal = false;
        }

        return $retVal;
    }

    /**
     * Parses Content
     * Finds image tags with data-htmlarea-file-uid attribute in the content.
     * All images that have an data-htmlarea-file-uid attribute will be returned with an info text
     *
     * @param string $content  The input content to analyse
     *
     * @return array|boolean  Result array on positive matches, see description above. Otherwise FALSE
     */
    public function findRef_rtehtmlarea_images($content)
    {
        $retVal = false;
        // Start HTML parser and split content by image tag
        $this->htmlParser = GeneralUtility::makeInstance(HtmlParser::class);
        $this->splittedContentTags = $this->htmlParser->splitTags('img', $content);

        $images = $this->findImagesWithDataUid();

        // Assemble result array
        if (!empty($images)) {
            $retVal = array(
                'content' => implode('', $this->splittedContentTags),
                'elements' => $images
            );
        }

        return $retVal;
    }

    /**
     * Checks for image tags
     *
     * @param $element
     * @return int
     */
    private function hasImageTag($element)
    {
        $pattern = "/^<img/";
        return preg_match($pattern, $element);
    }

    /**
     * Finding image tags with data-htmlarea-file-uid attribute in the content.
     * All images that have an data-htmlarea-file-uid attribute will be returned with an info text
     *
     * @return array
     */
    private function findImagesWithDataUid()
    {
        $images = array();

        // Traverse splitted parts
        foreach ($this->splittedContentTags as $k => $v) {

            if ($this->hasImageTag($v)) {

                // Get FAL uid reference
                $attribs = $this->htmlParser->get_tag_attributes($v);
                $fileUid = isset($attribs[0]['data-htmlarea-file-uid']) ? $attribs[0]['data-htmlarea-file-uid'] : false;

                // If there is a file uid, continue. Otherwise ignore this img tag.
                if ($fileUid) {
                    // Initialize the element entry with info text here
                    $tokenID = $this->makeTokenID($k);
                    $images[$k] = array();
                    $images[$k]['matchString'] = $v;
                    // Token and substitute value
                    $this->splittedContentTags[$k] = str_replace('data-htmlarea-file-uid="' . $fileUid . '"', 'data-htmlarea-file-uid="{softref:' . $tokenID . '}"', $this->splittedContentTags[$k]);
                    $images[$k]['subst'] = array(
                        'type' => 'db',
                        'recordRef' => 'sys_file:' . $fileUid,
                        'tokenID' => $tokenID,
                        'tokenValue' => $fileUid
                    );
                }
            }
        }

        return $images;
    }

}
