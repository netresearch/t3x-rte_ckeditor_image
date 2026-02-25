<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\ViewHelpers;

use DOMDocument;
use DOMNode;
use DOMText;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * ViewHelper for rendering RTE image previews in backend templates.
 *
 * Strips HTML to allowed tags (default: <img> + <p>) and truncates with
 * a DOM-aware algorithm that preserves HTML structure. Replicates the
 * preview logic from RteImagePreviewRenderer for use in Content Blocks
 * and other custom backend preview templates.
 *
 * Usage:
 *   <nr:rteImagePreview html="{data.bodytext}" />
 *   <nr:rteImagePreview html="{data.bodytext}" maxLength="500" />
 *   <nr:rteImagePreview html="{data.bodytext}" allowedTags="<img><p><figure><figcaption>" />
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 */
final class RteImagePreviewViewHelper extends AbstractViewHelper
{
    /**
     * Output is already sanitized HTML, do not escape.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('html', 'string', 'RTE HTML content to preview', true);
        $this->registerArgument('maxLength', 'int', 'Max characters before truncation', false, 1500);
        $this->registerArgument('allowedTags', 'string', 'HTML tags to keep', false, '<img><p>');
    }

    public function render(): string
    {
        return self::processHtml(
            $this->arguments['html'],
            $this->arguments['maxLength'],
            $this->arguments['allowedTags'],
        );
    }

    /**
     * Process RTE HTML for backend preview: sanitize, strip tags, and truncate.
     *
     * @param mixed $html        Raw HTML content
     * @param mixed $maxLength   Maximum text length before truncation
     * @param mixed $allowedTags HTML tags to preserve (strip_tags format)
     */
    public static function processHtml(mixed $html, mixed $maxLength = 1500, mixed $allowedTags = '<img><p>'): string
    {
        if (!is_string($html) || $html === '') {
            return '';
        }

        $maxLength   = is_int($maxLength) || is_numeric($maxLength) ? max(0, (int) $maxLength) : 1500;
        $allowedTags = is_string($allowedTags) ? $allowedTags : '<img><p>';

        // Sanitize control characters (replaces invalid chars with U+FFFD)
        // - Invalid control chars: [\x00-\x08\x0B\x0C\x0E-\x1F]
        // - UTF-16 surrogates (UTF-8 encoded): \xED[\xA0-\xBF][\x80-\xBF]
        // - Non-characters U+FFFE and U+FFFF: \xEF\xBF[\xBE\xBF]
        $html = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\xED[\xA0-\xBF][\x80-\xBF]|\xEF\xBF[\xBE\xBF]/',
            "\xEF\xBF\xBD",
            $html,
        ) ?? '';

        if ($html === '') {
            return '';
        }

        // Strip to allowed tags only
        $html = strip_tags($html, $allowedTags);

        return self::truncate($html, $maxLength);
    }

    /**
     * Truncates the given text, but preserves HTML tags.
     *
     * @see https://stackoverflow.com/questions/16583676/shorten-text-without-splitting-words-or-breaking-html-tags
     */
    private static function truncate(string $html, int $length): string
    {
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_use_internal_errors($internalErrors);

        $totalLength  = 0;
        $reachedLimit = false;
        $toRemove     = [];

        self::walk($dom, $length, $totalLength, $reachedLimit, $toRemove);

        foreach ($toRemove as $child) {
            $child->parentNode?->removeChild($child);
        }

        $result = $dom->saveHTML();

        if ($result === false) {
            return '';
        }

        // Remove the XML encoding PI that was prepended for UTF-8 support
        return str_replace('<?xml encoding="UTF-8">', '', $result);
    }

    /**
     * Walk the DOM tree and collect the length of all text nodes.
     *
     * @param DOMNode   $node
     * @param int       $maxLength
     * @param int       $totalLength  Current accumulated text length (passed by reference)
     * @param bool      $reachedLimit Whether the limit has been reached (passed by reference)
     * @param DOMNode[] $toRemove     Nodes to remove after traversal (passed by reference)
     */
    private static function walk(
        DOMNode $node,
        int $maxLength,
        int &$totalLength,
        bool &$reachedLimit,
        array &$toRemove,
    ): void {
        if ($reachedLimit) {
            $toRemove[] = $node;
        } else {
            if (($node instanceof DOMText) && ($node->nodeValue !== null)) {
                $totalLength += $nodeLen = mb_strlen($node->nodeValue);

                if ($totalLength > $maxLength) {
                    $node->nodeValue = mb_substr(
                        $node->nodeValue,
                        0,
                        $nodeLen - ($totalLength - $maxLength),
                    ) . '...';

                    $reachedLimit = true;
                }
            }

            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    self::walk($child, $maxLength, $totalLength, $reachedLimit, $toRemove);
                }
            }
        }
    }
}
