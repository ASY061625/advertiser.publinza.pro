<?php

declare(strict_types=1);

namespace App\Domain\System\Support;

/**
 * The changelog body, reduced to a tag set that cannot execute anything.
 *
 * Entries are rich text written by staff in the admin panel, so this is not a
 * defence against advertisers — it is a defence against the admin panel. A
 * changelog entry is the one piece of staff-authored HTML that renders inside
 * every advertiser's session, which makes it the single most valuable place in
 * the product to land a script tag: one compromised staff account, or one
 * paste of copied markup carrying an `onerror`, and it runs in everybody's
 * browser at once.
 *
 * Sanitised on read rather than on write, deliberately. Rows already in the
 * table were written before this existed, and a sanitiser that only runs in the
 * authoring path protects nothing that is already stored.
 */
final class ChangelogHtml
{
    /** Everything a release note needs and nothing that can carry behaviour. */
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li', 'code', 'a', 'h3', 'h4'];

    /** The only attribute that survives, and only on a link. */
    private const LINK_SCHEMES = ['http', 'https', 'mailto'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        // Plain text authored without markup still needs to render as
        // paragraphs rather than as one run-on line.
        if (! str_contains($html, '<')) {
            return self::paragraphs($html);
        }

        $document = new \DOMDocument;

        // Fragment, not a page: the wrapper gives libxml a single root and the
        // meta charset stops it reading UTF-8 as Latin-1 and mangling anything
        // with an accent in it.
        $loaded = @$document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="publinza-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET,
        );

        if ($loaded === false) {
            return self::paragraphs(strip_tags($html));
        }

        $root = $document->getElementById('publinza-root');

        if ($root === null) {
            return self::paragraphs(strip_tags($html));
        }

        self::scrub($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Walks the tree, unwrapping what is not allowed and stripping attributes.
     *
     * Disallowed elements are *unwrapped* rather than deleted, so a body
     * wrapped in a stray `<div>` keeps its text instead of vanishing. The two
     * exceptions are script and style, whose content is the danger.
     */
    private static function scrub(\DOMNode $node): void
    {
        // Snapshotted first: removing a node during iteration of a live
        // DOMNodeList silently skips its neighbour.
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($child->nodeName);

            if (in_array($name, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            self::scrub($child);

            if (! in_array($name, self::ALLOWED, true)) {
                self::unwrap($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $keep = $name === 'a'
                    && strtolower($attribute->nodeName) === 'href'
                    && self::isSafeHref($attribute->nodeValue);

                if (! $keep) {
                    $child->removeAttribute($attribute->nodeName);
                }
            }

            if ($name === 'a') {
                // Every changelog link leaves the app, and a target without a
                // rel hands the opener to whatever it opened.
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    private static function unwrap(\DOMElement $element): void
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

    private static function isSafeHref(?string $value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        // Relative links stay in the app and carry no scheme to abuse.
        if (str_starts_with($value, '/')) {
            return true;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), self::LINK_SCHEMES, true);
    }

    private static function paragraphs(string $text): string
    {
        $blocks = preg_split('/\n\s*\n/', trim($text)) ?: [];

        return implode('', array_map(
            static fn (string $block): string => '<p>'.nl2br(e(trim($block))).'</p>',
            array_filter($blocks, static fn (string $block): bool => trim($block) !== ''),
        ));
    }
}
