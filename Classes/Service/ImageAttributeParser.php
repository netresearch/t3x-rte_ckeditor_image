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
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
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
     * Parse attributes from <a> tag containing <img> tags.
     *
     * @param string $html HTML string containing <a><img /></a>
     *
     * @return array{link: array<string,string>, images: array<int,array{attributes: array<string,string>, originalHtml: string}>}
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
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Find first <a> element
        $links = $xpath->query('//a');

        if ($links === false || $links->length === 0) {
            return ['link' => [], 'images' => []];
        }

        /** @var DOMElement $link */
        $link = $links->item(0);

        // Extract link attributes
        $linkAttributes = $this->extractAttributes($link);

        // Find all <img> elements within the link
        $images          = $xpath->query('.//img', $link);
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
