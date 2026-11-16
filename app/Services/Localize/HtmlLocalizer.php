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
    private const TRANSLATABLE_ATTRIBUTE = '~(\s(?:title|placeholder|aria-label|data-tooltip)\s*=\s*)(["\'])(.*?)\2~is';

    /**
     * Captures the leading whitespace, the trimmed body, and the trailing
     * whitespace of a text segment, so a whole-segment exact match can keep the
     * original spacing around the translation.
     */
    private const TRIMMED_SEGMENT = '~^(\s*)(.*?)(\s*)$~s';

    /**
     * Translate English phrases inside an HTML fragment.
     *
     * Only text nodes and the whitelisted attributes (title, placeholder,
     * aria-label) are translated, and only outside script/style/pre/code/textarea
     * regions. Everything else — unknown text, comments, script bodies, non
     * whitelisted attribute values — passes through byte-identical. A skip
     * region spans HTML comments and only ends when every open skip tag,
     * including same-name nested ones, has been closed.
     *
     * Dictionary keys are applied in two tiers: a key without spaces matches a
     * whole text node or attribute value only (trimmed, keeping the original
     * leading and trailing whitespace), so single words like "Dashboard" can
     * never bleed into "Dashboards" or other longer words; a key containing
     * spaces is applied inside the segment through one strtr() pass, which
     * prefers the longest matching phrase. A miss returns the original text
     * untouched.
     *
     * @param  array<string, string>  $map  Full English phrase => translation.
     */
    public static function translate(string $html, array $map): string
    {
        if ($html === '' || $map === []) {
            return $html;
        }

        [$exact, $phrases, $index] = self::splitMap($map);

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
                $result .= self::translateSegments($part, $exact, $phrases, $index, $skipStack);
            }
        }

        return $result;
    }

    /**
     * Split the dictionary into whole-segment exact matches (keys without
     * spaces), in-segment phrase replacements (keys containing spaces), and an
     * index from a phrase's first word to the keys that start with it.
     *
     * The index keeps a text node cheap: a page has thousands of nodes and the
     * dictionary thousands of phrases, so scanning every phrase for every node is
     * what made a page take seconds. A key can only match a node that contains
     * its first word, so the node's own words pick the few keys worth testing.
     *
     * @param  array<string, string>  $map
     * @return array{0: array<string, string>, 1: array<string, string>, 2: array<string, list<string>>}
     */
    private static function splitMap(array $map): array
    {
        $exact = [];
        $phrases = [];

        foreach ($map as $english => $translated) {
            $english = (string) $english;
            $isPhrase = str_contains($english, ' ');
            // Blade's {{ }} escapes the value, so the HTML a browser receives
            // holds entities where the template had characters. A key has to be
            // registered in that escaped form too, or it can never match: a
            // sentence containing an apostrophe reached the page as
            // "Traefik&#039;s …" while the dictionary said "Traefik's …".
            foreach (self::entityVariants($english) as $variant) {
                if ($isPhrase) {
                    $phrases[$variant] = $translated;
                } else {
                    $exact[$variant] = $translated;
                }
            }
        }

        return [$exact, $phrases, self::indexPhrases($phrases)];
    }

    /**
     * The raw key plus every escaped form Blade can produce for it.
     *
     * `e()` (called by `{{ }}`) uses htmlspecialchars with ENT_QUOTES, which
     * turns `&`, `'`, and `"` into entities. A quoted attribute value and a text
     * node go through the same escaping, so both forms are registered.
     *
     * @return list<string>
     */
    private static function entityVariants(string $english): array
    {
        if (preg_match('/[&\'"]/', $english) !== 1) {
            return [$english];
        }

        $escaped = htmlspecialchars($english, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true);

        return $escaped === $english ? [$english] : [$english, $escaped];
    }

    /**
     * Map a phrase's first word to the keys that start with it.
     *
     * A key can only match a segment that contains its first word, so one map
     * lookup per word of the segment replaces a scan over every phrase.
     *
     * @param  array<string, string>  $phrases
     * @return array<string, list<string>>
     */
    private static function indexPhrases(array $phrases): array
    {
        $index = [];

        foreach (array_keys($phrases) as $key) {
            $key = (string) $key;
            if (preg_match('/^[^\p{L}\p{N}]*([\p{L}\p{N}_]+)/u', $key, $matches) !== 1) {
                continue;
            }
            $index[mb_strtolower($matches[1])][] = $key;
        }

        return $index;
    }

    /**
     * Translate one text segment or whitelisted attribute value: a whole-value
     * exact match first (keeping the original surrounding whitespace), then one
     * phrase pass over the value.
     *
     * A sentence that the source wraps over several lines reaches the browser
     * with newlines and indentation inside one text node, so a key written with
     * single spaces never matches it. When the raw pass finds nothing, the
     * whitespace runs are collapsed and the pass repeats; only a match takes the
     * collapsed text (HTML renders the runs as one space anyway), so anything
     * unmatched stays byte-identical.
     *
     * @param  array<string, string>  $exact
     * @param  array<string, string>  $phrases
     * @param  array<string, list<string>>  $index
     */
    private static function translateText(string $text, array $exact, array $phrases, array $index): string
    {
        if ($exact !== [] && preg_match(self::TRIMMED_SEGMENT, $text, $matches) === 1 && isset($exact[$matches[2]])) {
            return $matches[1].$exact[$matches[2]].$matches[3];
        }

        $translated = self::replacePhrases($text, $phrases, $index);

        if ($translated !== $text || $phrases === []) {
            return $translated;
        }

        if (preg_match(self::TRIMMED_SEGMENT, $text, $matches) !== 1) {
            return $text;
        }

        $collapsedBody = preg_replace('/\s+/', ' ', $matches[2]);

        if ($collapsedBody === null || $collapsedBody === $matches[2]) {
            return $text;
        }

        $collapsed = $matches[1].$collapsedBody.$matches[3];
        $translated = self::replacePhrases($collapsed, $phrases, $index);

        return $translated === $collapsed ? $text : $translated;
    }

    /**
     * Replace every phrase key in the text, honouring word boundaries, and let the
     * longest key win.
     *
     * Plain strtr() replaces on byte boundaries, so a key such as
     * "This resource" also hit the first two words of
     * "This resource cannot be restored …" and left the rest of the sentence in
     * English. The same rule made "API Token" replace the stem of "API Tokens" and
     * leave a stray "s". A key now only matches when the character before it and
     * the character after it are not part of a word, and when no longer key
     * covers the same span.
     *
     * Only the keys whose first word occurs in the text are considered, so the
     * cost follows the length of the text rather than the size of the dictionary.
     *
     * @param  array<string, string>  $phrases
     * @param  array<string, list<string>>  $index
     */
    private static function replacePhrases(string $text, array $phrases, array $index): string
    {
        if ($phrases === [] || $text === '') {
            return $text;
        }

        $candidates = [];

        // One lookup per distinct word of the text.
        if (preg_match_all('/[\p{L}\p{N}_]+/u', $text, $words) > 0) {
            foreach (array_unique($words[0]) as $word) {
                $keys = $index[mb_strtolower($word)] ?? null;
                if ($keys !== null) {
                    foreach ($keys as $key) {
                        $candidates[$key] = true;
                    }
                }
            }
        }

        if ($candidates === []) {
            return $text;
        }

        // Longest first: a sentence key must beat the fragment key it contains.
        $keys = array_keys($candidates);
        usort($keys, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $result = $text;

        foreach ($keys as $key) {
            if ($key === '' || ! str_contains($result, $key)) {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}_])'.preg_quote($key, '/').'(?![\p{L}\p{N}_])/u';

            if (@preg_match($pattern, '') === false) {
                continue;
            }

            if (preg_match_all($pattern, $result, $hits, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            // A key only replaces a span that stands on its own. A match that is
            // followed by more words of the same sentence (", plus", "cannot be",
            // "of the …") is a prefix, and translating only the prefix is what
            // produced half-Chinese output. A key at the end of the segment, or
            // one followed by punctuation, is a complete phrase.
            $result = self::replaceCompleteMatches($result, $pattern, $key, $phrases[$key]);
        }

        return $result;
    }

    /**
     * Replace the matches of one key that cover a whole clause, not a prefix of a
     * longer sentence.
     *
     * The rule: a match must not be followed by a lowercase word, because that
     * means the sentence continues and the key was only its first words. This is
     * what turned "This resource cannot be restored …" into "此资源 cannot be
     * restored …". Punctuation, the end of the segment, or a capitalised next
     * word (a label, a list, a title) count as a complete phrase.
     */
    private static function replaceCompleteMatches(string $text, string $pattern, string $key, string $translation): string
    {
        if (preg_match_all($pattern, $text, $hits, PREG_OFFSET_CAPTURE) === 0) {
            return $text;
        }

        $length = strlen($text);
        $result = '';
        $cursor = 0;

        foreach ($hits[0] as [$match, $offset]) {
            $after = $offset + strlen($match);

            if (! self::isClauseEnd($text, $after, $length)) {
                continue;
            }

            $result .= substr($text, $cursor, $offset - $cursor).$translation;
            $cursor = $after;
        }

        if ($cursor === 0) {
            return $text;
        }

        return $result.substr($text, $cursor);
    }

    /**
     * True when the text after $offset does not continue the sentence with more
     * words of the same clause.
     *
     * A match is complete when the segment ends right after it, or when the next
     * non-space character is punctuation: that covers "Servers, and more",
     * "Dashboard | Coolify", "Running (healthy)". Only a space followed by a
     * plain lowercase word continues the sentence — which is what made
     * "This resource cannot be restored …" render as "此资源 cannot be restored …".
     */
    private static function isClauseEnd(string $text, int $offset, int $length): bool
    {
        if ($offset >= $length) {
            return true;
        }

        $rest = substr($text, $offset, 16);
        $trimmed = ltrim($rest);

        if ($trimmed === '') {
            return true;
        }

        $first = $trimmed[0];

        // Punctuation separates this phrase from what follows.
        if (preg_match('/^[!-,\.\/:-@\[-`{-~]/', $first) === 1) {
            return true;
        }

        // A lowercase ASCII letter right after a space continues the sentence.
        return preg_match('/^[a-z]/', $first) !== 1;
    }

    /**
     * Walk the tag/text segments of an HTML chunk, continuing the skip-region
     * stack that spans chunks (comments are interleaved and preserved verbatim).
     *
     * @param  array<string, string>  $exact
     * @param  array<string, string>  $phrases
     * @param  list<string>  $skipStack
     */
    private static function translateSegments(string $html, array $exact, array $phrases, array $index, array &$skipStack): string
    {
        $segments = preg_split(self::TAG_SPLIT, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($segments === false) {
            return $html;
        }

        $result = '';

        foreach ($segments as $position => $segment) {
            if ($segment === '') {
                continue;
            }

            // Even indexes are text, odd indexes are whole tags (PREG_SPLIT_DELIM_CAPTURE).
            if ($position % 2 === 1) {
                $result .= self::translateTag($segment, $exact, $phrases, $index, $skipStack);
            } elseif ($skipStack === []) {
                $result .= self::translateText($segment, $exact, $phrases, $index);
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
     * @param  array<string, string>  $exact
     * @param  array<string, string>  $phrases
     * @param  list<string>  $skipStack
     */
    private static function translateTag(string $tag, array $exact, array $phrases, array $index, array &$skipStack): string
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

        return self::translateAttributes($tag, $exact, $phrases, $index);
    }

    /**
     * Translate the values of whitelisted attributes inside one tag segment.
     *
     * @param  array<string, string>  $exact
     * @param  array<string, string>  $phrases
     */
    private static function translateAttributes(string $tag, array $exact, array $phrases, array $index): string
    {
        return preg_replace_callback(
            self::TRANSLATABLE_ATTRIBUTE,
            fn (array $matches): string => $matches[1].$matches[2].self::translateText($matches[3], $exact, $phrases, $index).$matches[2],
            $tag,
        ) ?? $tag;
    }
}
