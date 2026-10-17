<?php

namespace FreeRoll\Ttrpg;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Allowlist sanitizer for adventure HTML stored in SQLite.
 */
final class AdventureHtml
{
    private const MAX_BYTES = 200000;

    /** @var array<string, string[]> */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'div' => [],
        'span' => ['class', 'data-roll', 'style'],
        'b' => [],
        'strong' => [],
        'i' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'strike' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'table' => ['class'],
        'thead' => [],
        'tbody' => [],
        'tr' => [],
        'td' => [],
        'th' => [],
        'blockquote' => [],
        'hr' => [],
        'a' => ['class', 'href', 'data-entry-id'],
        'img' => ['class', 'src', 'alt', 'data-src', 'data-asset-type'],
        'font' => ['size', 'color'],
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (strlen($html) > self::MAX_BYTES) {
            $html = function_exists('mb_strcut')
                ? mb_strcut($html, 0, self::MAX_BYTES, 'UTF-8')
                : substr($html, 0, self::MAX_BYTES);
        }

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="adv-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//*[@id="adv-root"]')->item(0);
        if (!$root instanceof DOMElement) {
            return '';
        }

        self::cleanChildren($root);
        $root->removeAttribute('id');

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    public static function isDiceExpr(string $expr): bool
    {
        return self::normalizeDice($expr) !== null;
    }

    private static function cleanChildren(DOMElement $parent): void
    {
        $nodes = [];
        foreach ($parent->childNodes as $child) {
            $nodes[] = $child;
        }

        foreach ($nodes as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if (!$child instanceof DOMElement) {
                $parent->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta'], true)) {
                $parent->removeChild($child);
                continue;
            }

            if (!isset(self::ALLOWED[$tag])) {
                self::unwrap($parent, $child);
                continue;
            }

            self::stripAttributes($child, $tag);
            self::cleanChildren($child);
            self::specialize($parent, $child, $tag);
        }
    }

    private static function unwrap(DOMElement $parent, DOMElement $child): void
    {
        self::cleanChildren($child);
        while ($child->firstChild) {
            $parent->insertBefore($child->firstChild, $child);
        }
        $parent->removeChild($child);
    }

    private static function stripAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];
        $names = [];
        if ($el->hasAttributes()) {
            foreach ($el->attributes as $attr) {
                $names[] = $attr->name;
            }
        }
        foreach ($names as $name) {
            if (!in_array(strtolower($name), $allowed, true)) {
                $el->removeAttribute($name);
            }
        }
    }

    private static function specialize(DOMElement $parent, DOMElement $el, string $tag): void
    {
        if ($tag === 'a') {
            $id = (int) $el->getAttribute('data-entry-id');
            if ($id < 1) {
                self::unwrap($parent, $el);
                return;
            }
            $el->setAttribute('data-entry-id', (string) $id);
            $el->setAttribute('class', 'adv-entry-link');
            $el->setAttribute('href', '#');
            $el->setAttribute('contenteditable', 'false');
            return;
        }

        if ($tag === 'img') {
            $rel = self::safeAssetSrc($el->getAttribute('data-src'));
            if ($rel === null) {
                $rel = self::safeAssetSrc($el->getAttribute('src'));
            }
            if ($rel === null) {
                $rel = self::extractRelative($el->getAttribute('src'));
            }
            if ($rel === null) {
                $rel = self::extractRelative($el->getAttribute('data-src'));
            }
            if ($rel === null) {
                $parent->removeChild($el);
                return;
            }
            $type = strtolower($el->getAttribute('data-asset-type'));
            if (!in_array($type, ['map', 'token', 'background'], true)) {
                $type = self::guessAssetType($rel);
            }
            $alt = trim($el->getAttribute('alt'));
            if (function_exists('mb_substr')) {
                $alt = mb_substr($alt, 0, 120);
            } else {
                $alt = substr($alt, 0, 120);
            }
            while ($el->attributes->length > 0) {
                $el->removeAttribute($el->attributes->item(0)->name);
            }
            $el->setAttribute('class', 'adv-ref');
            $el->setAttribute('src', $rel);
            $el->setAttribute('data-src', $rel);
            $el->setAttribute('data-asset-type', $type);
            $el->setAttribute('contenteditable', 'false');
            if ($alt !== '') {
                $el->setAttribute('alt', $alt);
            }
            return;
        }

        if ($tag === 'span') {
            $expr = self::normalizeDice($el->getAttribute('data-roll'));
            if ($el->hasAttribute('data-roll')) {
                if ($expr === null) {
                    self::unwrap($parent, $el);
                    return;
                }
                while ($el->firstChild) {
                    $el->removeChild($el->firstChild);
                }
                $el->appendChild($el->ownerDocument->createTextNode($expr));
                $el->setAttribute('class', 'adv-roll');
                $el->setAttribute('data-roll', $expr);
                $el->removeAttribute('style');
                $el->setAttribute('contenteditable', 'false');
                return;
            }
            $style = self::safeStyle($el->getAttribute('style'));
            if ($style === '') {
                $el->removeAttribute('style');
            } else {
                $el->setAttribute('style', $style);
            }
            $el->removeAttribute('class');
            $el->removeAttribute('data-roll');
            return;
        }

        if ($tag === 'table') {
            $el->setAttribute('class', 'swysiwyg-table');
            return;
        }

        if ($tag === 'font') {
            $size = $el->getAttribute('size');
            if (!preg_match('/^[1-7]$/', $size)) {
                $el->removeAttribute('size');
            }
            $color = self::safeColor($el->getAttribute('color'));
            if ($color === null) {
                $el->removeAttribute('color');
            } else {
                $el->setAttribute('color', $color);
            }
        }
    }

    private static function normalizeDice(string $expr): ?string
    {
        $expr = trim($expr);
        if ($expr === '') {
            return null;
        }
        if (!preg_match('/^(?:\d*d\d+)(?:\s*[+-]\s*(?:\d*d\d+|\d+))*$/i', $expr)) {
            return null;
        }
        return preg_replace('/\s+/', '', $expr);
    }

    private static function safeAssetSrc(string $src): ?string
    {
        $src = str_replace('\\', '/', trim($src));
        if ($src === '' || strpos($src, '..') !== false) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $src)) {
            return null;
        }
        if ($src[0] === '/' || $src[0] === '\\' || $src[0] === '?') {
            return null;
        }
        if (!preg_match('#^backend/assets/(map|tokens|backgrounds)/#', $src)) {
            return null;
        }
        if (!preg_match('#\.(png|jpe?g|gif|webp)$#i', $src)) {
            return null;
        }
        if (preg_match('#[\x00-\x1F<>"\']#', $src)) {
            return null;
        }
        return $src;
    }

    private static function extractRelative(string $src): ?string
    {
        $src = str_replace('\\', '/', $src);
        $pos = strpos($src, 'backend/assets/');
        if ($pos === false) {
            return null;
        }
        return self::safeAssetSrc(substr($src, $pos));
    }

    private static function guessAssetType(string $src): string
    {
        if (strpos($src, '/tokens/') !== false) {
            return 'token';
        }
        if (strpos($src, '/backgrounds/') !== false) {
            return 'background';
        }
        return 'map';
    }

    private static function safeStyle(string $style): string
    {
        $out = [];
        foreach (explode(';', $style) as $part) {
            $part = trim($part);
            if ($part === '' || strpos($part, ':') === false) {
                continue;
            }
            $bits = explode(':', $part, 2);
            $key = strtolower(trim($bits[0]));
            $value = trim($bits[1]);
            if ($key === 'color') {
                $color = self::safeColor($value);
                if ($color !== null) {
                    $out[] = 'color: ' . $color;
                }
            } elseif ($key === 'font-size' && preg_match('/^(?:xx-small|x-small|small|medium|large|x-large|xx-large|[1-7]|\d{1,2}px)$/i', $value)) {
                $out[] = 'font-size: ' . $value;
            }
        }
        return implode('; ', $out);
    }

    /**
     * Replace data-entry-id tokens. Unknown tokens become 0 so sanitize unwraps the link.
     *
     * @param array<string, int|string> $map
     */
    public static function remapEntryIds(string $html, array $map): string
    {
        $rewritten = preg_replace_callback(
            '/\bdata-entry-id=(["\'])([^"\']+)\1/',
            static function (array $match) use ($map): string {
                $token = $match[2];
                if (array_key_exists($token, $map)) {
                    return 'data-entry-id=' . $match[1] . $map[$token] . $match[1];
                }
                return 'data-entry-id=' . $match[1] . '0' . $match[1];
            },
            $html
        );
        if (!is_string($rewritten)) {
            return $html;
        }
        return $rewritten;
    }

    private static function safeColor(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)) {
            return $value;
        }
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $value, $m)) {
            foreach ([1, 2, 3] as $i) {
                if ((int) $m[$i] > 255) {
                    return null;
                }
            }
            return 'rgb(' . (int) $m[1] . ', ' . (int) $m[2] . ', ' . (int) $m[3] . ')';
        }
        return null;
    }
}
