<?php

/*
 * Copyright (c) 2025-2026 Netresearch DTT GmbH
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Netresearch\RteCKEditorImage\Service;

use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Reduces figcaption markup to a small inline allowlist.
 *
 * Kept: br, strong, b, em, i, and a with href, target, title and rel.
 * Every other element is replaced by its text content, every other
 * attribute is dropped, comments and processing instructions are removed.
 *
 * The input is parsed with DOMDocument and the output is built as a new
 * document from the allowed nodes only; text is written as text nodes and
 * attribute values via setAttribute(), so nothing of the input is copied as
 * markup. Serialisation by DOMDocument::saveHTML() encodes <, > and & in
 * text and quotes in attribute values.
 *
 * The caller decides which link targets are allowed: $hrefFilter receives
 * the trimmed href and returns the href to write, or null to replace the
 * link by its text.
 *
 * Pure function without I/O or state, like SecurityRelComputer.
 *
 * @see https://github.com/netresearch/t3x-rte_ckeditor_image/issues/969
 *
 * @internal
 */
final class CaptionSanitizer
{
    /**
     * Inline elements kept in a caption. Everything else becomes text.
     *
     * @var list<string>
     */
    private const ALLOWED_ELEMENTS = ['br', 'strong', 'b', 'em', 'i', 'a'];

    /**
     * @param string                   $html       Inner HTML of a figcaption
     * @param Closure(string): ?string $hrefFilter Returns the href to write, or null to drop the link
     *
     * @return string Sanitized HTML, safe to print without further escaping
     */
    public static function sanitize(string $html, Closure $hrefFilter): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $source = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $source->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
            . $html,
            LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $source->getElementsByTagName('body')->item(0);

        if (!$body instanceof DOMElement) {
            return '';
        }

        $target    = new DOMDocument('1.0', 'UTF-8');
        $container = $target->createElement('div');
        $target->appendChild($container);

        self::copyChildren($body, $container, $hrefFilter, false);

        $result = '';

        foreach ($container->childNodes as $child) {
            $result .= $target->saveHTML($child);
        }

        return trim($result);
    }

    /**
     * @param Closure(string): ?string $hrefFilter
     */
    private static function copyChildren(DOMNode $from, DOMElement $to, Closure $hrefFilter, bool $insideLink): void
    {
        $document = $to->ownerDocument;

        if (!$document instanceof DOMDocument) {
            return;
        }

        foreach ($from->childNodes as $child) {
            if ($child instanceof DOMText) {
                $to->appendChild($document->createTextNode($child->data));

                continue;
            }

            if (!$child instanceof DOMElement) {
                // Comments, processing instructions: dropped
                continue;
            }

            $name = strtolower($child->nodeName);

            if (!in_array($name, self::ALLOWED_ELEMENTS, true) || ($name === 'a' && $insideLink)) {
                $to->appendChild($document->createTextNode($child->textContent));

                continue;
            }

            if ($name === 'br') {
                $to->appendChild($document->createElement('br'));

                continue;
            }

            if ($name === 'a') {
                self::copyLink($child, $to, $hrefFilter);

                continue;
            }

            $element = $document->createElement($name);
            $to->appendChild($element);
            self::copyChildren($child, $element, $hrefFilter, $insideLink);
        }
    }

    /**
     * @param Closure(string): ?string $hrefFilter
     */
    private static function copyLink(DOMElement $source, DOMElement $to, Closure $hrefFilter): void
    {
        $document = $to->ownerDocument;

        if (!$document instanceof DOMDocument) {
            return;
        }

        $href = trim($source->getAttribute('href'));
        $href = $href !== '' ? $hrefFilter($href) : null;

        if ($href === null || $href === '') {
            $to->appendChild($document->createTextNode($source->textContent));

            return;
        }

        $link = $document->createElement('a');
        $link->setAttribute('href', $href);

        $target = $source->hasAttribute('target') ? trim($source->getAttribute('target')) : '';

        if ($target !== '') {
            $link->setAttribute('target', $target);
        }

        if ($source->hasAttribute('title')) {
            $link->setAttribute('title', $source->getAttribute('title'));
        }

        $rel = SecurityRelComputer::compute(
            $target !== '' ? $target : null,
            $href,
            $source->hasAttribute('rel') ? $source->getAttribute('rel') : null,
        );

        if ($rel !== null) {
            $link->setAttribute('rel', $rel);
        }

        $to->appendChild($link);
        self::copyChildren($source, $link, $hrefFilter, true);
    }
}
