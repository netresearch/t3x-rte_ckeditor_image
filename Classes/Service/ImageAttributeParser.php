<?php

/*
 * This file is part of the package netresearch/rte-ckeditor-image.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Pure HTML attribute parser using DOMDocument.
 *
 * RESPONSIBILITY: Extract raw attributes from HTML strings only.
 * NO business logic, NO validation, NO sanitization - just parsing.
 *
 * @author  Netresearch DTT GmbH
 * @license https://www.gnu.org/licenses/agpl-3.0.de.html
 *
 * @see    https://www.netresearch.de
 */
class ImageAttributeParser
{
    /**
     * Parse attributes from <img> tag HTML string.
     *
     * @param string $html HTML string containing <img> tag
     *
     * @return array<string,string> Attribute name => value pairs
     */
    public function parseImageAttributes(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        // Create DOMDocument with error suppression for HTML5 tags
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        // Load HTML fragment - wrap in div to ensure proper parsing
        $dom->loadHTML(
            '<div>' . $html . '</div>',
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();

        // Find first <img> element
        $xpath  = new DOMXPath($dom);
        $images = $xpath->query('//img');

        if ($images === false || $images->length === 0) {
            return [];
        }

        /** @var DOMElement $img */
        $img = $images->item(0);

        return $this->extractAttributes($img);
    }

    /**
     * Parse attributes from <a> tag containing <img> tags, or standalone img tags.
     *
     * When TypoScript tags.a.preUserFunc calls this method, getCurrentVal()
     * returns only the inner content of the link (just <img>), not <a><img></a>.
     * This method handles both cases: full link HTML or just inner content.
     *
     * @param string $html HTML string containing <a><img /></a> or just <img />
     *
     * @return array{link: array<string,string>, images: array<int,array{attributes: array<string,string>, originalHtml: string}>}
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/546
     */
    public function parseLinkWithImages(string $html): array
    {
        if (trim($html) === '') {
            return ['link' => [], 'images' => []];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<div>' . $html . '</div>',
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Find first <a> element (if present)
        $links          = $xpath->query('//a');
        $searchContext  = null;
        $linkAttributes = [];

        if ($links !== false && $links->length > 0) {
            /** @var DOMElement $link */
            $link           = $links->item(0);
            $linkAttributes = $this->extractAttributes($link);
            $searchContext  = $link;
        }

        // Find all <img> elements - either within the link or at document level
        // When called from tags.a.preUserFunc, getCurrentVal() returns only inner
        // content (just <img>), so we need to search the whole document if no link found.
        $images = $searchContext !== null
            ? $xpath->query('.//img', $searchContext)
            : $xpath->query('//img');

        $imageAttributes = [];

        if ($images !== false) {
            foreach ($images as $img) {
                if ($img instanceof DOMElement) {
                    $imageAttributes[] = [
                        'attributes'   => $this->extractAttributes($img),
                        'originalHtml' => $this->getOuterHtml($img),
                    ];
                }
            }
        }

        return [
            'link'   => $linkAttributes,
            'images' => $imageAttributes,
        ];
    }

    /**
     * Get the outer HTML of a DOM element.
     *
     * @param DOMElement $element DOM element
     *
     * @return string Outer HTML string
     */
    private function getOuterHtml(DOMElement $element): string
    {
        $doc = $element->ownerDocument;

        if (!$doc instanceof DOMDocument) {
            return '';
        }

        $html = $doc->saveHTML($element);

        return $html !== false ? $html : '';
    }

    /**
     * Parse image attributes, caption, link, and figure class from <figure> wrapped images.
     *
     * Extracts caption text from <figcaption> element if present.
     * Extracts link attributes when image is wrapped in <a> inside figure.
     * Extracts figure class for alignment (image-left, image-center, image-right).
     * This handles CKEditor 5 output format: <figure><img/><figcaption>...</figcaption></figure>
     * And linked images: <figure><a href="..."><img/></a><figcaption>...</figcaption></figure>
     *
     * @param string $html HTML string containing figure-wrapped image
     *
     * @return array{attributes: array<string,string>, caption: string, link: array<string,string>, figureClass: string}
     *
     * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/555
     */
    public function parseFigureWithCaption(string $html): array
    {
        if (trim($html) === '') {
            return ['attributes' => [], 'caption' => '', 'link' => [], 'figureClass' => ''];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<div>' . $html . '</div>',
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Find first <figure> element
        $figures = $xpath->query('//figure');

        if ($figures === false || $figures->length === 0) {
            return ['attributes' => [], 'caption' => '', 'link' => [], 'figureClass' => ''];
        }

        /** @var DOMElement $figure */
        $figure = $figures->item(0);

        // Extract figure class (for alignment: image-left, image-center, image-right)
        $figureClass = $figure->getAttribute('class') ?? '';

        // Extract image attributes
        $images     = $xpath->query('.//img', $figure);
        $attributes = [];

        if ($images !== false && $images->length > 0) {
            /** @var DOMElement $img */
            $img        = $images->item(0);
            $attributes = $this->extractAttributes($img);
        }

        // Extract caption from figcaption
        $captions = $xpath->query('.//figcaption', $figure);
        $caption  = '';

        if ($captions !== false && $captions->length > 0) {
            /** @var DOMElement $figcaptionElement */
            $figcaptionElement = $captions->item(0);
            $caption           = trim($figcaptionElement->textContent ?? '');
        }

        // Extract link attributes if image is wrapped in <a>
        // Query for <a> elements that contain an <img> descendant (not just any <a>)
        $linksWithImage = $xpath->query('.//a[.//img]', $figure);
        $linkAttributes = [];

        if ($linksWithImage !== false && $linksWithImage->length > 0) {
            /** @var DOMElement $link */
            $link           = $linksWithImage->item(0);
            $linkAttributes = $this->extractAttributes($link);
        }

        return ['attributes' => $attributes, 'caption' => $caption, 'link' => $linkAttributes, 'figureClass' => $figureClass];
    }

    /**
     * Check if HTML contains a figure-wrapped image structure.
     *
     * Uses DOM parsing to ensure the img is actually inside the figure element,
     * not just co-existing in the same HTML string.
     *
     * @param string $html HTML to check
     *
     * @return bool True if figure wrapper with nested img detected
     */
    public function hasFigureWrapper(string $html): bool
    {
        if (trim($html) === '') {
            return false;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);

        $dom->loadHTML(
            '<div>' . $html . '</div>',
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Check for any <figure> that contains an <img> descendant
        $figuresWithImages = $xpath->query('//figure[.//img]');

        return $figuresWithImages !== false && $figuresWithImages->length > 0;
    }

    /**
     * Extract all attributes from a DOM element.
     *
     * @param DOMElement $element DOM element to extract attributes from
     *
     * @return array<string,string> Attribute name => value pairs
     */
    private function extractAttributes(DOMElement $element): array
    {
        $attributes = [];

        if (!$element->hasAttributes()) {
            return $attributes;
        }

        foreach ($element->attributes as $attr) {
            // DOMAttr properties name and value are always strings (never null)
            $attributes[$attr->name] = $attr->value;
        }

        return $attributes;
    }
}
