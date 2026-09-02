<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Reduces untrusted HTML to a small allowlist of formatting tags.
 *
 * Article bodies are written by publishers and rendered inside the
 * advertiser's authenticated session on app.publinza.pro. Anything that
 * reaches the page unescaped runs with the reader's cookies, so an article is
 * hostile input until it has been through here.
 *
 * Allowlist, not blocklist. A blocklist has to anticipate every vector, and
 * loses to the next one; an allowlist only has to know what an article
 * legitimately contains, which is a dozen tags.
 */
final class HtmlSanitizer
{
    /** tag => permitted attributes. */
    private const ALLOWED = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [], 'code' => [], 'pre' => [], 'hr' => [],
        'a' => ['href', 'title'], 'img' => ['src', 'alt', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];

    /**
     * Everything else is stripped, including <script>, <style>, <iframe>,
     * <form>, every on* handler and every attribute not named above.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $document = new DOMDocument;

        // Errors are expected: publishers paste half-closed markup from word
        // processors, and libxml complains. The parse still yields a tree.
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="publinza-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('publinza-root');

        if ($root === null) {
            return '';
        }

        // Comments can carry conditional-comment payloads and are never wanted.
        $xpath = new DOMXPath($document);

        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        self::walk($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        // Snapshot first: the loop removes and unwraps nodes, and iterating a
        // live DOMNodeList while mutating it skips siblings.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->nodeName);

            if (! array_key_exists($tag, self::ALLOWED)) {
                // A disallowed tag loses its markup but keeps its text, except
                // for the ones whose text is itself the payload.
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg'], true)) {
                    $child->parentNode?->removeChild($child);
                } else {
                    self::walk($child);
                    self::unwrap($child);
                }

                continue;
            }

            self::cleanAttributes($child, $tag);
            self::walk($child);
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            if (! in_array(strtolower($attribute->nodeName), $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        foreach (['href' => 'a', 'src' => 'img'] as $attribute => $onTag) {
            if ($tag === $onTag && $element->hasAttribute($attribute)
                && ! self::safeUrl($element->getAttribute($attribute))) {
                $element->removeAttribute($attribute);
            }
        }

        // Every surviving link leaves the app in a new tab with no window
        // handle back to it and no referrer leaking the advertiser's path.
        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /** Replaces an element with its children, keeping the text. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /**
     * http, https, mailto and relative only.
     *
     * `javascript:`, `data:` and `vbscript:` all execute; whitespace and
     * control characters inside a scheme are a classic way to smuggle one past
     * a naive check, so they come out before the comparison.
     */
    private static function safeUrl(string $url): bool
    {
        $normalised = strtolower(preg_replace('/[\s\x00-\x1F\x7F]+/', '', $url) ?? '');

        if ($normalised === '') {
            return false;
        }

        if (! str_contains(explode('/', $normalised)[0] ?? '', ':')) {
            return true; // Relative.
        }

        return (bool) preg_match('#^(https?:|mailto:)#', $normalised);
    }
}
