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
     */
    private const TAG_SPLIT = '/(<[^>]+>)/';

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
     * whitelisted attribute values — passes through byte-identical. Each text
     * segment goes through a single strtr() pass; a miss returns the original
     * text untouched.
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

        $result = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $result .= str_starts_with($part, '<!--')
                ? $part
                : self::translateSegments($part, $map);
        }

        return $result;
    }

    /**
     * Walk the tag/text segments of a comment-free HTML chunk, tracking skip
     * regions with a stack of open skip-tag names.
     *
     * @param  array<string, string>  $map
     */
    private static function translateSegments(string $html, array $map): string
    {
        $segments = preg_split(self::TAG_SPLIT, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false) {
            return $html;
        }

        $skipStack = [];
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
     * whitelisted attributes when the tag is outside a skip region.
     *
     * @param  array<string, string>  $map
     * @param  list<string>  $skipStack
     */
    private static function translateTag(string $tag, array $map, array &$skipStack): string
    {
        if (preg_match('/^<\/\s*([a-zA-Z][a-zA-Z0-9:_-]*)/', $tag, $matches) === 1) {
            $key = array_search(strtolower($matches[1]), $skipStack, true);
            if ($key !== false) {
                $skipStack = array_slice($skipStack, 0, (int) $key);
            }

            return $tag;
        }

        if (preg_match('/^<([a-zA-Z][a-zA-Z0-9:_-]*)/', $tag, $matches) === 1) {
            $inSkipRegion = $skipStack !== [];

            if ($inSkipRegion === false && preg_match('~/\s*>$~', $tag) !== 1
                && in_array(strtolower($matches[1]), self::SKIP_TAGS, true)) {
                $skipStack[] = strtolower($matches[1]);
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
