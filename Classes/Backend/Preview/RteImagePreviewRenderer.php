<?php

/**
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Backend\Preview;

use DOMDocument;
use DOMNode;
use DOMText;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;
use TYPO3\CMS\Frontend\Preview\TextPreviewRenderer;

/**
 * Renders the preview of TCA "text" elements. This class overrides the
 * default \TYPO3\CMS\Frontend\Preview\TextPreviewRenderer and extends its functionality to
 * include images in the preview.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 * @link    https://www.netresearch.de
 */
class RteImagePreviewRenderer extends TextPreviewRenderer
{
    private bool $reachedLimit = false;
    private int $totalLength = 0;
    private array $toRemove = [];

    /**
     * Dedicated method for rendering preview body HTML for the page module only.
     * Receives the GridColumnItem that contains the record for which a preview should be
     * rendered and returned.
     *
     * @param GridColumnItem $item
     *
     * @return string
     */
    public function renderPageModulePreviewContent(GridColumnItem $item): string
    {
        $row = $item->getRecord();

        return $this
            ->linkEditContent(
                $this->renderTextWithHtml($row['bodytext'] ?? ''),
                $row
            )
            . '<br />';
    }

    /**
     * Processing of larger amounts of text (usually from RTE/bodytext fields) with word wrapping etc.
     *
     * @param string $input Input string
     * @return string Output string
     */
    protected function renderTextWithHtml(string $input): string
    {
        // Allow only <img> and <p>-tags in preview, to prevent possible HTML mismatch
        $input = strip_tags($input, '<img><p>');

        return $this->truncate($input, 1500);
    }

    /**
     * Truncates the given text, but preserves HTML tags.
     *
     * @param string $html
     * @param int    $length
     *
     * @return string
     *
     * @see https://stackoverflow.com/questions/16583676/shorten-text-without-splitting-words-or-breaking-html-tags
     */
    private function truncate(string $html, int $length): string
    {
        $dom = new DOMDocument();
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $toRemove = $this->walk($dom, $length);

        // Remove any nodes that exceed limit
        foreach ($toRemove as $child) {
            $child->parentNode->removeChild($child);
        }

        $result = $dom->saveHTML();

        return $result === false ? '' : $result;
    }

    /**
     * Walk the DOM tree and collect the length of all text nodes.
     *
     * @param DOMNode $node
     * @param int     $maxLength
     *
     * @return array
     */
    private function walk(DOMNode $node, int $maxLength): array
    {
        if ($this->reachedLimit) {
            $this->toRemove[] = $node;
        } else {
            // Only text nodes should have text, so do the splitting here
            if (($node instanceof DOMText) && ($node->nodeValue !== null)) {
                $this->totalLength += $nodeLen = mb_strlen($node->nodeValue);

                if ($this->totalLength > $maxLength) {
                    $node->nodeValue = mb_substr(
                        $node->nodeValue,
                        0,
                        $nodeLen - ($this->totalLength - $maxLength)
                    ) . '...';

                    $this->reachedLimit = true;
                }
            }

            // If node has children, walk its child elements
            if ($node->childNodes->count() > 0) {
                foreach ($node->childNodes as $child) {
                    $this->walk($child, $maxLength);
                }
            }
        }

        return $this->toRemove;
    }
}
