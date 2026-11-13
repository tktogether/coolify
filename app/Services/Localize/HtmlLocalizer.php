<?php

namespace App\Services\Localize;

class HtmlLocalizer
{
    /**
     * Tags whose entire content must pass through untranslated.
     */
    private const SKIP_TAGS = ['script', 'style', 'pre', 'code', 'textarea'];

    /**
     * Captures a whole tag so the text between tags can be translated separately.
     *
     * The pattern is quote-aware: quoted attribute values are consumed whole, so
     * a ">" inside a quoted value can never split a tag mid-attribute and turn a
     * non whitelisted attribute value into translatable text. An unterminated
     * quoted value ends the tag at the next ">" instead, which degrades
     * fail-safe: the malformed tag passes through untranslated and
     * byte-identical, and no content is ever dropped or restructured.
     */
    private const TAG_SPLIT = '/(<\/?[a-zA-Z][^\s>]*+(?:"[^"]*+"|\'[^\']*\'|"[^>"]*+|\'[^>\']*+|[^>"\'])*+>)/';

    /**
     * Captures a complete HTML comment so it can be preserved verbatim.
     */
    private const COMMENT_SPLIT = '/(<!--.*?-->)/s';

    /**
     * Matches a whitelisted attribute assignment with a single- or double-quoted
     * value; the value is captured for translation.
     */
    private const TRANSLATABLE_ATTRIBUTE = '~(\s(?:title|placeholder|aria-label)\s*=\s*)(["\'])(.*?)\2~is';

    /**
     * Translate English phrases inside an HTML fragment.
     *
     * Only text nodes and the whitelisted attributes (title, placeholder,
     * aria-label) are translated, and only outside script/style/pre/code/textarea
     * regions. Everything else — unknown text, comments, script bodies, non
     * whitelisted attribute values — passes through byte-identical. A skip
     * region spans HTML comments and only ends when every open skip tag,
     * including same-name nested ones, has been closed. Each text segment goes
     * through a single strtr() pass; a miss returns the original text untouched.
     *
     * @param  array<string, string>  $map  Full English phrase => translation.
     */
    public static function translate(string $html, array $map): string
    {
        if ($html === '' || $map === []) {
            return $html;
        }

        $parts = preg_split(self::COMMENT_SPLIT, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }

        $skipStack = [];
        $result = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, '<!--')) {
                $result .= $part;
            } else {
                $result .= self::translateSegments($part, $map, $skipStack);
            }
        }

        return $result;
    }

    /**
     * Walk the tag/text segments of an HTML chunk, continuing the skip-region
     * stack that spans chunks (comments are interleaved and preserved verbatim).
     *
     * @param  array<string, string>  $map
     * @param  list<string>  $skipStack
     */
    private static function translateSegments(string $html, array $map, array &$skipStack): string
    {
        $segments = preg_split(self::TAG_SPLIT, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false) {
            return $html;
        }

        $result = '';

        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }

            if ($index % 2 === 1) {
                $result .= self::translateTag($segment, $map, $skipStack);
            } elseif ($skipStack === []) {
                $result .= strtr($segment, $map);
            } else {
                $result .= $segment;
            }
        }

        return $result;
    }

    /**
     * Update the skip-region state for one tag segment and translate its
     * whitelisted attributes when the tag is outside a skip region. The opening
     * tag that starts a skip region is itself outside the region, so its
     * whitelisted attributes are still translated; every skip tag encountered
     * inside a region — including same-name nested ones — is pushed as well, so
     * the region only ends when all of them have been closed. A closing tag
     * closes the innermost matching open tag and everything opened after it,
     * which recovers from mismatched nesting.
     *
     * @param  array<string, string>  $map
     * @param  list<string>  $skipStack
     */
    private static function translateTag(string $tag, array $map, array &$skipStack): string
    {
        if (preg_match('/^<\/\s*([a-zA-Z][a-zA-Z0-9:_-]*)/', $tag, $matches) === 1) {
            $keys = array_keys($skipStack, strtolower($matches[1]), true);
            if ($keys !== []) {
                $skipStack = array_slice($skipStack, 0, (int) end($keys));
            }

            return $tag;
        }

        if (preg_match('/^<([a-zA-Z][a-zA-Z0-9:_-]*)/', $tag, $matches) === 1) {
            $inSkipRegion = $skipStack !== [];
            $name = strtolower($matches[1]);

            if (preg_match('~/\s*>$~', $tag) !== 1 && in_array($name, self::SKIP_TAGS, true)) {
                $skipStack[] = $name;
            }

            if ($inSkipRegion) {
                return $tag;
            }
        }

        return self::translateAttributes($tag, $map);
    }

    /**
     * Translate the values of whitelisted attributes inside one tag segment.
     *
     * @param  array<string, string>  $map
     */
    private static function translateAttributes(string $tag, array $map): string
    {
        return preg_replace_callback(
            self::TRANSLATABLE_ATTRIBUTE,
            fn (array $matches): string => $matches[1].$matches[2].strtr($matches[3], $map).$matches[2],
            $tag,
        ) ?? $tag;
    }
}
