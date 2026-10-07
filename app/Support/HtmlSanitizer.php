<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/*
 * Keeps article HTML that arrives from outside (RankYak) to the tags an article
 * needs and nothing that can run: no scripts, styles, frames, forms, event
 * handlers or javascript: links. Anything unknown is unwrapped, keeping its text.
 */
class HtmlSanitizer
{
    /** tag => attributes it may keep */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [],
        'h2' => ['id'], 'h3' => ['id'], 'h4' => ['id'], 'h5' => ['id'], 'h6' => ['id'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'mark' => [], 'small' => [], 'sub' => [], 'sup' => [],
        'a' => ['href', 'title', 'rel', 'target'],
        'ul' => [], 'ol' => ['start'], 'li' => [],
        'blockquote' => ['cite'], 'q' => ['cite'], 'cite' => [],
        'code' => [], 'pre' => [], 'kbd' => [],
        'figure' => [], 'figcaption' => [], 'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'], 'caption' => [],
        'aside' => ['class'], 'div' => [], 'span' => [], 'section' => [],
        'dl' => [], 'dt' => [], 'dd' => [], 'details' => [], 'summary' => [],
    ];

    /** Removed along with everything inside them */
    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript', 'frame', 'frameset'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('__root');
        if (! $root) {
            return e(strip_tags($html));
        }

        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED, true)) {
                $node->removeChild($child);

                continue;
            }

            /* The page already has the title as its h1, so headings inside the article start at h2 */
            if ($tag === 'h1') {
                $heading = $child->ownerDocument->createElement('h2');
                while ($child->firstChild) {
                    $heading->appendChild($child->firstChild);
                }
                $node->replaceChild($heading, $child);
                $child = $heading;
                $tag = 'h2';
            }

            self::walk($child);

            if (! array_key_exists($tag, self::ALLOWED)) {
                /* Unknown wrapper (h1, font, center…): keep what is inside, lose the tag */
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            self::cleanAttributes($child, self::ALLOWED[$tag]);
        }
    }

    private static function cleanAttributes(DOMElement $element, array $allowed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (in_array($name, ['href', 'src', 'cite'], true) && ! self::safeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if (strtolower($element->tagName) === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        if (strtolower($element->tagName) === 'img' && ! $element->hasAttribute('loading')) {
            $element->setAttribute('loading', 'lazy');
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url)));

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        return (bool) preg_match('#^(https?:|mailto:|tel:)#i', $url);
    }
}
