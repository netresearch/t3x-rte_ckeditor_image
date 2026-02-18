<?php

/*
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
use Netresearch\RteCKEditorImage\Dto\ValidationIssue;
use Netresearch\RteCKEditorImage\Dto\ValidationIssueType;
use Netresearch\RteCKEditorImage\Service\RteImageReferenceValidator;
use TYPO3\CMS\Backend\Preview\StandardContentPreviewRenderer;
use TYPO3\CMS\Backend\View\BackendLayout\Grid\GridColumnItem;

/**
 * Renders the preview of TCA "text" elements. This class overrides the
 * default \TYPO3\CMS\Frontend\Preview\TextPreviewRenderer and extends its functionality to
 * include images in the preview.
 *
 * Additionally detects broken image references and shows a warning callout
 * in the page module preview.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class RteImagePreviewRenderer extends StandardContentPreviewRenderer
{
    private bool $reachedLimit = false;

    private int $totalLength = 0;

    /** @var DOMNode[] */
    private array $toRemove = [];

    public function __construct(
        private readonly ?RteImageReferenceValidator $validator = null,
    ) {}

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
        $record = $item->getRecord();

        // TYPO3 v14+ uses getRow() for array access, v13 uses getRecord() which returns array directly
        /** @var array<string, mixed> $row */
        $row  = method_exists($item, 'getRow') ? $item->getRow() : $record;
        $html = $row['bodytext'] ?? '';

        // Sanitize HTML (replaces invalid chars with U+FFFD).
        // - Invalid control chars: [\x00-\x08\x0B\x0C\x0E-\x1F]
        // - UTF-16 surrogates (UTF-8 encoded): \xED[\xA0-\xBF][\x80-\xBF]
        // - Non-characters U+FFFE and U+FFFF: \xEF\xBF[\xBE\xBF]
        $html = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F]|\xED[\xA0-\xBF][\x80-\xBF]|\xEF\xBF[\xBE\xBF]/',
            "\xEF\xBF\xBD",
            $html,
        ) ?? '';

        $warning = $this->detectIssuesAndRenderWarning($html, $row);

        return $warning
            . $this
                ->linkEditContent(
                    $this->renderTextWithHtml($html),
                    $record,
                )
            . '<br />';
    }

    /**
     * Processing of larger amounts of text (usually from RTE/bodytext fields) with word wrapping etc.
     *
     * @param string $input Input string
     *
     * @return string Output string
     */
    protected function renderTextWithHtml(string $input): string
    {
        // Allow only <img> and <p>-tags in preview, to prevent possible HTML mismatch
        $input = strip_tags($input, '<img><p>');

        return $this->truncate($input, 1500);
    }

    /**
     * Detect broken image references and render a warning callout if issues exist.
     *
     * @param string|null          $html Sanitized HTML from bodytext
     * @param array<string, mixed> $row  The database row
     */
    private function detectIssuesAndRenderWarning(?string $html, array $row): string
    {
        if (!$this->validator instanceof RteImageReferenceValidator) {
            return '';
        }

        if ($html === null || $html === '' || !str_contains($html, '<img')) {
            return '';
        }

        $rawUid = $row['uid'] ?? 0;

        if (is_int($rawUid)) {
            $uid = $rawUid;
        } elseif (is_string($rawUid)) {
            $uid = (int) $rawUid;
        } else {
            $uid = 0;
        }

        $issues = $this->validator->validateHtml($html, 'tt_content', $uid, 'bodytext');

        if ($issues === []) {
            return '';
        }

        return $this->renderIssueWarning($issues);
    }

    /**
     * Render a warning callout summarizing the detected issues.
     *
     * @param list<ValidationIssue> $issues
     */
    private function renderIssueWarning(array $issues): string
    {
        $counts = [];

        foreach ($issues as $issue) {
            $label = match ($issue->type) {
                ValidationIssueType::OrphanedFileUid   => 'orphaned file reference(s)',
                ValidationIssueType::SrcMismatch       => 'outdated src path(s)',
                ValidationIssueType::ProcessedImageSrc => 'processed image URL(s)',
                ValidationIssueType::MissingFileUid    => 'missing file UID(s)',
                ValidationIssueType::BrokenSrc         => 'broken src attribute(s)',
            };

            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        $parts = [];

        foreach ($counts as $label => $count) {
            $parts[] = $count . ' ' . $label;
        }

        $summary = implode(', ', $parts);

        return '<div class="callout callout-warning">'
            . '<div class="callout-title">Image reference issues detected</div>'
            . '<div class="callout-body">'
            . '<p>' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '.</p>'
            . '<p>Run the upgrade wizard <strong>rteImageReferenceValidation</strong> or CLI command '
            . '<code>bin/typo3 rte_ckeditor_image:validate --fix</code> to repair.</p>'
            . '</div>'
            . '</div>';
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
        // Reset state from previous invocations (instance may be reused by DI)
        $this->reachedLimit = false;
        $this->totalLength  = 0;
        $this->toRemove     = [];

        // Set error level
        $internalErrors = libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        // Restore error level
        libxml_use_internal_errors($internalErrors);

        $toRemove = $this->walk($dom, $length);

        // Remove any nodes that exceed limit
        foreach ($toRemove as $child) {
            $child->parentNode?->removeChild($child);
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
     * @return DOMNode[]
     */
    private function walk(DOMNode $node, int $maxLength): array
    {
        if ($this->reachedLimit) {
            $this->toRemove[] = $node;
        } else {
            // Only text nodes should have a text, so do the splitting here
            if (($node instanceof DOMText) && ($node->nodeValue !== null)) {
                $this->totalLength += $nodeLen = mb_strlen($node->nodeValue);

                if ($this->totalLength > $maxLength) {
                    $node->nodeValue = mb_substr(
                        $node->nodeValue,
                        0,
                        $nodeLen - ($this->totalLength - $maxLength),
                    ) . '...';

                    $this->reachedLimit = true;
                }
            }

            // We need to explizitly check hasChildNodes() to circumvent a bug in PHP < 7.4.4
            // which results in childNodes being NULL https://bugs.php.net/bug.php?id=79271
            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    $this->walk($child, $maxLength);
                }
            }
        }

        return $this->toRemove;
    }
}
