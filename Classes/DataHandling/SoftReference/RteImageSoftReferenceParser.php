<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\DataHandling\SoftReference;

use TYPO3\CMS\Core\DataHandling\SoftReference\AbstractSoftReferenceParser;
use TYPO3\CMS\Core\DataHandling\SoftReference\SoftReferenceParserResult;
use TYPO3\CMS\Core\Html\HtmlParser;

/**
 * Class for processing of the FAL soft references on img tags inserted in RTE content.
 *
 * @author  Stefan Galinski <stefan@sgalinski.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 * @link    https://www.netresearch.de
 */
class RteImageSoftReferenceParser extends AbstractSoftReferenceParser
{
    /**
     * TYPO3 HTML parser.
     *
     * @var HtmlParser
     */
    private readonly HtmlParser $htmlParser;

    /**
     * @var array<int, string>
     */
    private array $splitContentTags;

    /**
     * Constructor.
     *
     * @param HtmlParser $htmlParser
     */
    public function __construct(HtmlParser $htmlParser)
    {
        $this->htmlParser = $htmlParser;
    }

    /**
     * Main function through which all processing happens.
     *
     * @param string $table         Database table name
     * @param string $field         Field name for which processing occurs
     * @param int    $uid           UID of the record
     * @param string $content       The content/value of the field
     * @param string $structurePath If running from inside a FlexForm structure, this is the path of the tag.
     *
     * @return SoftReferenceParserResult
     */
    public function parse(
        string $table,
        string $field,
        int $uid,
        string $content,
        string $structurePath = ''
    ): SoftReferenceParserResult {
        $this->setTokenIdBasePrefix($table, (string)$uid, $field, $structurePath);

        if ($this->parserKey === 'rtehtmlarea_images') {
            $retVal = $this->findImageTags($content);
        } else {
            $retVal = [];
        }

        return SoftReferenceParserResult::create($content, $retVal);
    }

    /**
     * Finds image tags with "data-htmlarea-file-uid" attribute in the content. All images that have
     * a "data-htmlarea-file-uid" attribute will be returned with an info text.
     *
     * @param string $content The input content to analyse
     *
     * @return array<string, array<array<string, mixed>>|string>
     */
    private function findImageTags(string $content): array
    {
        // Content split into images and other elements
        $this->splitContentTags = $this->htmlParser->splitTags(
            'img',
            $content
        );

        $images = $this->findImagesWithDataUid();

        if (\count($images) === 0) {
            return [];
        }

        return $images;
    }

    /**
     * Checks for image tags.
     *
     * @param string $element
     *
     * @return bool
     */
    private function isImageTag(string $element): bool
    {
        return (bool)preg_match('/^<img/', $element);
    }

    /**
     * Finding image tags with "data-htmlarea-file-uid" attribute in the content. All images that have
     * a "data-htmlarea-file-uid" attribute will be returned with an info text.
     *
     * @return array<array<string, mixed>>
     */
    private function findImagesWithDataUid(): array
    {
        $images = [];

        // Traverse split parts
        foreach ($this->splitContentTags as $key => $htmlTag) {
            if (!$this->isImageTag($htmlTag)) {
                continue;
            }

            // Get FAL uid reference
            $attribs = $this->htmlParser->get_tag_attributes($htmlTag);
            $fileUid = $attribs[0]['data-htmlarea-file-uid'] ?? false;

            // If there is a file uid, continue. Otherwise, ignore this img tag.
            if ($fileUid === false) {
                continue;
            }

            // Initialize the element entry with info text here
            $tokenID = $this->makeTokenID((string)$key);

            $images[$key] = [];
            $images[$key]['matchString'] = $htmlTag;

            // Token and substitute value
            $this->splitContentTags[$key] = str_replace(
                'data-htmlarea-file-uid="' . $fileUid . '"',
                'data-htmlarea-file-uid="{softref:' . $tokenID . '}"',
                $htmlTag
            );

            $images[$key]['subst'] = [
                'type'       => 'db',
                'recordRef'  => 'sys_file:' . $fileUid,
                'tokenID'    => $tokenID,
                'tokenValue' => $fileUid,
            ];
        }

        return $images;
    }
}
