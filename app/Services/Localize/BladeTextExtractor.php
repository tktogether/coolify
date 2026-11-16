<?php

namespace App\Services\Localize;

/**
 * Collects the English strings a Blade template shows to a user, in the shape
 * the runtime localizer sees them: one entry per text node, plus the values of
 * the attributes HtmlLocalizer translates.
 *
 * Blade has to be evaluated before this shape exists, so the template source is
 * reduced first: comments, PHP blocks, script and style bodies, echoes, and
 * directive argument lists are removed. Removing them also protects the tag
 * split below, because Blade expressions such as `['class' => 'x']` contain a
 * `>` that is not the end of a tag.
 *
 * A returned string may still be a fragment of a sentence: when a template puts
 * an inline tag or a Blade echo inside a sentence, each piece is its own text
 * node. The coverage test treats those as separate entries on purpose, because
 * that is what the runtime sees.
 */
class BladeTextExtractor
{
    /**
     * Mirrors HtmlLocalizer::TAG_SPLIT: a quote-aware whole tag, so a `>` inside
     * a quoted attribute value cannot split a tag in the middle.
     */
    private const TAG_SPLIT = '/(<\/?[a-zA-Z][^\s>]*+(?:"[^"]*+"|\'[^\']*\'|"[^>"]*+|\'[^>\']*+|[^>"\'])*+>)/';

    /**
     * Attribute values the runtime renders as text.
     *
     * title and placeholder are also browser attributes, and the localizer
     * translates them in place. The rest are component props: the project's
     * components print them into their own markup — `{{ $label }}`,
     * `{{ $description }}`, `{{ $buttonTitle }}` — so the rendered page contains
     * them as ordinary text nodes even though the template value never appears
     * literally in the HTML.
     *
     * The button and confirmation captions are the largest group: 384 call sites
     * in the views, and an English caption on a button is one of the first things
     * a user notices.
     */
    private const TRANSLATABLE_ATTRIBUTE = '~\s(title|placeholder|aria-label|data-tooltip|label|description|buttonTitle|step1ButtonText|step2ButtonText|step3ButtonText|confirmationLabel|shortConfirmationLabel|emptyText|searchEmptyText)\s*=\s*(["\'])(.*?)\2~is';

    /**
     * Stands where a conditional directive sat, so the branches on either side
     * become separate candidates instead of one joined sentence.
     */
    private const BRANCH_MARKER = "\x1A";

    /**
     * The static text at the start of an interpolated prop value, for example
     * "Delete" from title="Delete {{ $resourceLabel }}".
     *
     * Such a prop renders as "<static><value>" in one text node, so the value
     * reaches the browser only combined with this prefix. The prefix alone cannot
     * match the whole node, but it is the part a dictionary entry has to start
     * with, and it is what a reviewer needs to see. A value that starts with the
     * echo has no prefix and is skipped.
     *
     * @return list<string>
     */
    private static function interpolatedPropPrefixes(string $source): array
    {
        if (preg_match_all('/(?<![:\\w-])(?:title|placeholder|aria-label|data-tooltip|label|description)\s*=\s*"([^"]*\{\{.*?\}\}[^"]*)"/s', $source, $matches) === 0) {
            return [];
        }

        $prefixes = [];
        foreach ($matches[1] as $value) {
            $position = strpos($value, '{{');
            if ($position === false) {
                continue;
            }

            $prefix = trim((string) preg_replace('/\s+/u', ' ', substr($value, 0, $position)));

            // A single word is kept: "Delete {{ $label }}" renders as
            // "Delete resource", and the prefix is what identifies the entry.
            if (preg_match('/[A-Za-z]{3,}/', $prefix) === 1) {
                $prefixes[] = $prefix;
            }
        }

        return $prefixes;
    }

    /**
     * Text nodes and attribute values in one template, in source order.
     *
     * @return list<string>
     */
    public static function fromTemplate(string $source): array
    {
        $found = [];

        // Interpolated props need the original source: the echo is removed below,
        // which would leave only the static prefix ("Delete" out of
        // title="Delete {{ $resourceLabel }}"). The prefix reaches the browser as
        // the first words of the rendered text node.
        foreach (self::interpolatedPropPrefixes($source) as $prefix) {
            $found[] = $prefix;
        }

        // PHP array values in the original source: a bound option list such as
        // :options="[['value' => true, 'label' => 'Use Docker build cache']]" is
        // inside a quoted attribute, so the tag pass below treats it as a value
        // rather than as text. The component renders each label as a text node.
        foreach (self::fromPhpArrayValues($source) as $value) {
            $found[] = $value;
        }

        foreach (preg_split(self::TAG_SPLIT, self::stripBlade($source), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $index => $segment) {
            if ($index % 2 === 1) {
                // Group 2 is the quote character, group 3 the attribute value.
                if (preg_match_all(self::TRANSLATABLE_ATTRIBUTE, $segment, $matches) > 0) {
                    foreach ($matches[3] as $value) {
                        $found[] = $value;
                    }
                }

                continue;
            }

            // One conditional branch is one candidate.
            foreach (explode(self::BRANCH_MARKER, $segment) as $branch) {
                $found[] = $branch;
            }
        }

        return array_values(array_unique(array_filter(
            array_map(static fn (string $value): string => trim((string) preg_replace('/\s+/u', ' ', $value)), $found),
            static fn (string $value): bool => $value !== '',
        )));
    }

    /**
     * Every template under the views directory, keyed by its path relative to it.
     *
     * @return array<string, list<string>>
     */
    public static function fromDirectory(string $viewsDirectory): array
    {
        $result = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($viewsDirectory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $path = $file->getPathname();
            $result[str_replace('\\', '/', substr($path, strlen($viewsDirectory) + 1))] = self::fromTemplate((string) file_get_contents($path));
        }

        ksort($result);

        return $result;
    }

    /**
     * UI copy that lives in PHP instead of a template, keyed by its file.
     *
     * Component and Livewire payloads carry display text in array values — a
     * resource-type card's `name` and `description`, a listbox option's `label`,
     * a settings-section `title`. Those reach the browser as ordinary text nodes,
     * so a template-only scan misses them completely.
     *
     * Only the display keys are read. Text that never renders into a page is
     * skipped by path: OpenAPI attribute descriptions, MCP tool descriptions, and
     * console output are documentation, not UI.
     *
     * @return array<string, list<string>>
     */
    public static function fromPhpSources(string $appDirectory): array
    {
        $skippedPaths = [
            'Http/Controllers/Api/',
            'OpenApi/',
            'Mcp/',
            'Console/',
            'Jobs/',
            'Notifications/',
            'Actions/',
        ];

        $result = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDirectory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($appDirectory) + 1));

            foreach ($skippedPaths as $skip) {
                if (str_starts_with($relative, $skip)) {
                    continue 2;
                }
            }

            $source = (string) file_get_contents($file->getPathname());

            // An OpenAPI controller can also hold UI copy; skip only its attribute text.
            $source = preg_replace('/#\[OA\\\\[^\]]*\]/s', '', $source) ?? $source;

            $values = self::fromPhpArrayValues($source);

            if ($values !== []) {
                $result[$relative] = $values;
            }
        }

        ksort($result);

        return $result;
    }

    /**
     * Display text in array values: name, description, title, label, helper, and
     * placeholder, quoted with either single or double quotes.
     *
     * Double quotes matter for component defaults: `@props(['label' => "You have
     * changes that haven't been saved yet."])` puts an apostrophe inside the value,
     * which is exactly why the original author reached for double quotes.
     *
     * A value inside a schema entry — an array that also declares `type` — is an
     * API field description, not UI copy. Models expose many of those, and
     * translating them would add entries that no page ever shows.
     *
     * @return list<string>
     */
    public static function fromPhpArrayValues(string $source): array
    {
        $pattern = '/[\'"](name|description|title|label|helper|placeholder)[\'"]\s*=>\s*'
            .'(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/s';

        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $found = [];
        foreach ($matches as $match) {
            // Group 2 is the single-quoted value, group 3 the double-quoted one.
            $raw = ($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? '');

            $value = str_replace(["\\'", '\\"', '\\\\'], ["'", '"', '\\'], $raw);
            $value = trim((string) preg_replace('/\s+/u', ' ', $value));

            if ($value === '') {
                continue;
            }

            // Schema entry: the enclosing array declares a `type` next to it, so the
            // value is an API field description rather than UI copy.
            if ($match[1] === 'description') {
                $offset = (int) strpos($source, $match[0]);
                $before = substr($source, max(0, $offset - 400), 400);

                if (preg_match('/[\'"]type[\'"]\s*=>/', $before) === 1) {
                    continue;
                }
            }

            $found[] = $value;
        }

        return array_values(array_unique($found));
    }

    /**
     * Remove everything that is code rather than rendered text.
     */
    private static function stripBlade(string $source): string
    {
        // The attribute pass has to run before the echoes are removed: it looks
        // for a Blade echo inside the value to know that the value is dynamic.
        // Removing the echo first would leave a meaningless tail such as
        // "% of the busiest".
        $source = preg_replace_callback(
            '~\s(title|placeholder|aria-label|data-tooltip)\s*=\s*(["\'])(.*?)\2~is',
            static fn (array $matches): string => str_contains($matches[3], '{{') || str_contains($matches[3], '{!!')
                ? ''
                : $matches[0],
            $source,
        ) ?? $source;

        $source = self::stripBladeBlocks($source);

        return self::removeDirectiveArguments($source);
    }

    /**
     * Remove comments, PHP and verbatim blocks, and script/style bodies.
     */
    private static function stripBladeBlocks(string $source): string
    {
        $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;
        $source = preg_replace('/<!--.*?-->/s', '', $source) ?? $source;
        $source = preg_replace('/@php\b.*?@endphp/s', '', $source) ?? $source;
        $source = preg_replace('/@verbatim\b.*?@endverbatim/s', '', $source) ?? $source;
        $source = preg_replace('/<script\b.*?<\/script>/is', '', $source) ?? $source;
        $source = preg_replace('/<style\b.*?<\/style>/is', '', $source) ?? $source;

        // Echoes outside attributes: the inner expression is code, and it may
        // contain a `>` that would otherwise end a tag early.
        $source = preg_replace('/\{!!.*?!!\}/s', '', $source) ?? $source;

        return preg_replace('/\{\{.*?\}\}/s', '', $source) ?? $source;
    }

    /**
     * Strip the argument list of every `@directive(...)`, keeping the directive
     * name so its output position stays visible, and drop the remaining
     * parenthesis-free directives.
     */
    private static function removeDirectiveArguments(string $source): string
    {
        // Drop `//` and `#` line comments first: an apostrophe inside a comment in
        // an argument list (for example `// callers' canGate`) would otherwise
        // desynchronise the quote tracking and leave part of the list behind.
        $source = preg_replace('~(^|\s)//[^\n]*~', '$1', $source) ?? $source;

        $result = '';
        $length = strlen($source);
        $cursor = 0;

        while ($cursor < $length) {
            if (preg_match('/@[a-zA-Z_]\w*\s*\(/', $source, $match, PREG_OFFSET_CAPTURE, $cursor) !== 1) {
                $result .= substr($source, $cursor);

                break;
            }

            $start = $match[0][1];
            $result .= substr($source, $cursor, $start - $cursor);

            $openParen = $start + strlen($match[0][0]) - 1;
            $closeParen = self::findClosingParen($source, $openParen);

            if ($closeParen === null) {
                $result .= substr($source, $start);

                break;
            }

            $cursor = $closeParen + 1;
        }

        // Parenthesis-free directives: @csrf, @endif, @else, @auth, …
        // Replaced by a marker, not removed: a conditional such as
        // @if (…A…) @else (…B…) @endif emits either A or B, never "A B", so the
        // branches must not be joined into one candidate.
        return preg_replace('/@[a-zA-Z_]\w*/', self::BRANCH_MARKER, $result) ?? $result;
    }

    /**
     * Index of the parenthesis that closes the one at $openIndex, ignoring
     * parentheses inside quotes.
     */
    private static function findClosingParen(string $source, int $openIndex): ?int
    {
        $depth = 0;
        $length = strlen($source);
        $quote = null;

        for ($index = $openIndex; $index < $length; $index++) {
            $char = $source[$index];

            if ($quote !== null) {
                if ($char === '\\') {
                    $index++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }
}
