<?php
namespace Netresearch\RteCKEditorImage\Database;

/**
 * See class comment
 *
 * PHP version 7
 *
 * @category   Netresearch
 * @package    RteCKEditor
 * @subpackage Database
 * @author     Stefan Galinski <stefan@sgalinski.de>
 * @license    http://www.netresearch.de Netresearch Copyright
 * @link       http://www.netresearch.de
 */

use TYPO3\CMS\Core\Database\SoftReferenceIndex;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class for processing of the FAL soft references on img tags inserted in RTE content
 *
 * Copied from
 * @link https://gitlab.sgalinski.de/typo3/tinymce4_rte/blob/513eeadf8c3c7ffba0936ad63b24e1e9c2eccba7/Classes/Hook/SoftReferenceHook.php
 *
 * PHP version 5
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
    // Token prefix
    public $tokenID_basePrefix = '';

    /**
     * Main function through which all processing happens
     *
     * @param string Database table name
     * @param string Field name for which processing occurs
     * @param int UID of the record
     * @param string The content/value of the field
     * @param string The softlink parser key. This is only interesting if more than one parser is grouped in the same class. That is the case with this parser.
     * @param array Parameters of the softlink parser. Basically this is the content inside optional []-brackets after the softref keys. Parameters are exploded by ";
     * @param string If running from inside a FlexForm structure, this is the path of the tag.
     * @return array Result array on positive matches. Otherwise FALSE
     */
    public function findRef($table, $field, $uid, $content, $spKey, $spParams, $structurePath = '')
    {
        $retVal = false;
        $this->tokenID_basePrefix = $table . ':' . $uid . ':' . $field . ':' . $structurePath . ':' . $spKey;

        switch ($spKey) {
            case 'rtehtmlarea_images':
                $retVal = $this->findRef_rtehtmlarea_images($content, $spParams);
                break;
            default:
                $retVal = false;
        }

        return $retVal;
    }

    /**
     * Finding image tags with data-htmlarea-file-uid attribute in the content.
     * All images that have an data-htmlarea-file-uid attribute will be returned with an info text
     *
     * @param string The input content to analyse
     * @param array Parameters set for the softref parser key in TCA/columns
     * @return array Result array on positive matches, see description above. Otherwise FALSE
     */
    public function findRef_rtehtmlarea_images($content, $spParams)
    {
        $retVal = false;
        // Start HTML parser and split content by image tag
        $htmlParser = GeneralUtility::makeInstance(HtmlParser::class);
        $imgTags = $htmlParser->splitTags('img', $content);
        $elements = array();

        // Traverse splitted parts
        foreach ($imgTags as $k => $v) {
            if ($k % 2) {
                // Get FAL uid reference
                $attribs = $htmlParser->get_tag_attributes($v);
                $fileUid = $attribs[0]['data-htmlarea-file-uid'];

                // If there is a file uid, continue. Otherwise ignore this img tag.
                if ($fileUid) {
                    // Initialize the element entry with info text here
                    $tokenID = $this->makeTokenID($k);
                    $elements[$k] = array();
                    $elements[$k]['matchString'] = $v;
                    // Token and substitute value
                    $imgTags[$k] = str_replace('data-htmlarea-file-uid="' . $fileUid . '"', 'data-htmlarea-file-uid="{softref:' . $tokenID . '}"', $imgTags[$k]);
                    $elements[$k]['subst'] = array(
                        'type' => 'db',
                        'recordRef' => 'sys_file:' . $fileUid,
                        'tokenID' => $tokenID,
                        'tokenValue' => $fileUid
                    );
                }
            }
        }

        // Assemble result array
        if (!empty($elements)) {
            $retVal = array(
                'content' => implode('', $imgTags),
                'elements' => $elements
            );
        }

        return $retVal;
    }
}
