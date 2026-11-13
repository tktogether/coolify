<?php

/**
 * Blade localization candidate extractor (development tool, not part of the build or runtime).
 *
 * Scans every Blade template under resources/views (recursively) for candidate
 * English UI phrases, so the Chinese translation dictionary can be triaged:
 *
 *  - static text nodes between tags
 *  - static quoted values of the whitelisted attributes: title, placeholder, aria-label
 *
 * Blade comments, echo expressions, directive argument lists, @php and @verbatim
 * blocks, HTML comments, and inline script/style blocks are stripped before scanning.
 * A segment is excluded when it contains a Blade directive (@), an echo ({{ or }}),
 * a tag (< or >), or is pure symbols, pure numbers, or a single character.
 *
 * Usage:
 *   php scripts/extract-localization-candidates.php                 # print candidates to stdout
 *   php scripts/extract-localization-candidates.php --output=FILE   # write candidates to FILE
 *
 * Output: one unique candidate per line, sorted case-insensitively.
 */

declare(strict_types=1);

const WHITELISTED_ATTRIBUTES = ['title', 'placeholder', 'aria-label'];

function usage(int $exitCode = 0): never
{
    $lines = [
        'Usage: php scripts/extract-localization-candidates.php [--output=FILE]',
        '',
        'Extracts candidate English UI phrases from the Blade templates under',
        'resources/views (static text nodes + static values of title, placeholder',
        'and aria-label). Prints one unique, sorted candidate per line, or writes',
        'them to FILE.',
    ];
    fwrite($exitCode === 0 ? STDOUT : STDERR, implode("\n", $lines)."\n");
    exit($exitCode);
}

function parseOutputPath(array $argv): ?string
{
    $outputPath = null;
    $argumentCount = count($argv);
    for ($index = 1; $index < $argumentCount; $index++) {
        $argument = $argv[$index];
        if ($argument === '--help' || $argument === '-h') {
            usage(0);
        } elseif (str_starts_with($argument, '--output=')) {
            $outputPath = substr($argument, strlen('--output='));
            if ($outputPath === '') {
                fwrite(STDERR, "The --output flag needs a file path.\n");
                usage(1);
            }
        } elseif ($argument === '--output') {
            $outputPath = $argv[$index + 1] ?? '';
            if ($outputPath === '') {
                fwrite(STDERR, "The --output flag needs a file path.\n");
                usage(1);
            }
            $index++;
        } else {
            fwrite(STDERR, "Unknown argument: {$argument}\n");
            usage(1);
        }
    }

    return $outputPath;
}

function collectBladeFiles(string $viewsDirectory): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($viewsDirectory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

function findMatchingParen(string $content, int $openParenIndex): ?int
{
    $length = strlen($content);
    $depth = 0;
    $inSingleQuote = false;
    $inDoubleQuote = false;
    for ($index = $openParenIndex; $index < $length; $index++) {
        $char = $content[$index];
        if ($inSingleQuote) {
            if ($char === '\\') {
                $index++;
            } elseif ($char === "'") {
                $inSingleQuote = false;
            }

            continue;
        }
        if ($inDoubleQuote) {
            if ($char === '\\') {
                $index++;
            } elseif ($char === '"') {
                $inDoubleQuote = false;
            }

            continue;
        }
        if ($char === "'") {
            $inSingleQuote = true;
        } elseif ($char === '"') {
            $inDoubleQuote = true;
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

function removeDirectiveExpressions(string $content): string
{
    if (! preg_match_all('/@[a-zA-Z_]\w*\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE)) {
        return $content;
    }

    $result = '';
    $cursor = 0;
    foreach ($matches[0] as [$token, $start]) {
        if ($start < $cursor) {
            continue; // inside a directive expression removed earlier
        }
        $openParenIndex = $start + strlen($token) - 1;
        $closeParenIndex = findMatchingParen($content, $openParenIndex);
        if ($closeParenIndex === null) {
            continue; // unbalanced parentheses; leave the content untouched
        }
        $result .= substr($content, $cursor, $start - $cursor);
        $cursor = $closeParenIndex + 1;
    }

    return $result.substr($content, $cursor);
}

function cleanTemplate(string $content): string
{
    $content = preg_replace('/<\?(?:php\b|=).*?\?>/s', '', $content); // raw PHP blocks
    $content = preg_replace('/{{--.*?--}}/s', '', $content); // Blade comments
    $content = preg_replace('/@php\b.*?@endphp/s', '', $content); // @php blocks
    $content = preg_replace('/@verbatim\b.*?@endverbatim/s', '', $content); // @verbatim blocks
    $content = removeDirectiveExpressions($content); // @include(...), @if (...), @section(...), ...
    $content = preg_replace('/<!--.*?-->/s', '', $content); // HTML comments
    $content = preg_replace('/<script\b[^>]*\/\s*>/is', '', $content); // self-closing script tags
    $content = preg_replace('/<script\b[^>]*>.*?<\/script\s*>/is', '', $content); // inline JS
    $content = preg_replace('/<style\b[^>]*\/\s*>/is', '', $content); // self-closing style tags
    $content = preg_replace('/<style\b[^>]*>.*?<\/style\s*>/is', '', $content); // inline CSS
    $content = preg_replace('/{{.*?}}/s', '', $content); // Blade echo expressions
    $content = preg_replace('/{!!.*?!!}/s', '', $content); // Blade raw echo expressions

    return $content;
}

function findTagEnd(string $content, int $tagStart): ?int
{
    $length = strlen($content);
    $inSingleQuote = false;
    $inDoubleQuote = false;
    for ($index = $tagStart + 1; $index < $length; $index++) {
        $char = $content[$index];
        if ($inSingleQuote) {
            if ($char === "'") {
                $inSingleQuote = false;
            }

            continue;
        }
        if ($inDoubleQuote) {
            if ($char === '"') {
                $inDoubleQuote = false;
            }

            continue;
        }
        if ($char === "'") {
            $inSingleQuote = true;
        } elseif ($char === '"') {
            $inDoubleQuote = true;
        } elseif ($char === '>') {
            return $index + 1;
        }
    }

    return null;
}

/**
 * Walks the template and returns the text between tags plus the tag markup itself.
 * Quoted attribute values may contain < and > without ending the tag; the content of
 * script and style blocks is skipped, not returned as text.
 *
 * @return array{0: list<string>, 1: list<string>} [text segments, tags]
 */
function parseTemplateMarkup(string $content): array
{
    $textSegments = [];
    $tags = [];
    $length = strlen($content);
    $position = 0;
    $textStart = 0;
    while ($position < $length) {
        if ($content[$position] !== '<') {
            $position++;

            continue;
        }
        $nextChar = $content[$position + 1] ?? '';
        if ($nextChar === '!' || $nextChar === '?') {
            // Declaration or processing instruction (DOCTYPE, CDATA, ...): skip it whole.
            $closeIndex = strpos($content, '>', $position);
            if ($closeIndex === false) {
                break;
            }
            $position = $closeIndex + 1;
            $textStart = $position;

            continue;
        }
        if ($nextChar === '' || ! ctype_alpha($nextChar) && $nextChar !== '/') {
            $position++;

            continue;
        }
        $tagEnd = findTagEnd($content, $position);
        if ($tagEnd === null) {
            $position++; // stray < that never closes; treat it as text

            continue;
        }
        if ($position > $textStart) {
            $textSegments[] = substr($content, $textStart, $position - $textStart);
        }
        $tag = substr($content, $position, $tagEnd - $position);
        if (preg_match('/^<\/?(script|style)\b/i', $tag, $blockMatch) && ! str_ends_with($tag, '/>')) {
            // Skip the block content of <script>/<style> up to and including its closing tag.
            $blockName = $blockMatch[1];
            if (preg_match('/<\/'.$blockName.'\s*>/i', $content, $closeMatch, PREG_OFFSET_CAPTURE, $tagEnd)) {
                $position = $closeMatch[0][1] + strlen($closeMatch[0][0]);
            } else {
                $position = $length; // unterminated block: skip the rest of the file
            }
            $textStart = $position;

            continue;
        }
        $tags[] = $tag;
        $position = $tagEnd;
        $textStart = $position;
    }
    if ($length > $textStart) {
        $textSegments[] = substr($content, $textStart, $length - $textStart);
    }

    return [$textSegments, $tags];
}

function normalizeSegment(string $segment): string
{
    $segment = html_entity_decode($segment, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim((string) preg_replace('/\s+/u', ' ', $segment));
}

function isCandidate(string $text): bool
{
    if ($text === '' || mb_strlen($text) < 2) {
        return false; // empty or single character
    }
    if (! preg_match('/[A-Za-z]/', $text)) {
        return false; // pure symbols or numbers
    }
    if (str_contains($text, '@') || str_contains($text, '{{') || str_contains($text, '}}')) {
        return false; // Blade constructs
    }
    if (str_contains($text, '<') || str_contains($text, '>')) {
        return false; // tags, including ones created by entity decoding
    }

    return true;
}

function collectCandidatesFromFile(string $content, array $uniqueCandidates): array
{
    [$textSegments, $tags] = parseTemplateMarkup(cleanTemplate($content));
    foreach ($textSegments as $segment) {
        $candidate = normalizeSegment($segment);
        if (isCandidate($candidate)) {
            $uniqueCandidates[$candidate] = true;
        }
    }

    $attributeNames = implode('|', array_map('preg_quote', WHITELISTED_ATTRIBUTES));
    $attributeRegex = '/(?<![-:\w])('.$attributeNames.')(?![\w-])\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i';
    foreach ($tags as $tag) {
        preg_match_all($attributeRegex, $tag, $attributeMatches, PREG_SET_ORDER);
        foreach ($attributeMatches as $match) {
            $value = (($match[2] ?? '') !== '') ? $match[2] : ($match[3] ?? '');
            $candidate = normalizeSegment($value);
            if (isCandidate($candidate)) {
                $uniqueCandidates[$candidate] = true;
            }
        }
    }

    return $uniqueCandidates;
}

$outputPath = parseOutputPath($argv);

$viewsDirectory = dirname(__DIR__).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views';
if (! is_dir($viewsDirectory)) {
    fwrite(STDERR, "Views directory not found: {$viewsDirectory}\n");
    exit(1);
}

$uniqueCandidates = [];
foreach (collectBladeFiles($viewsDirectory) as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        fwrite(STDERR, "Skipped unreadable file: {$file}\n");

        continue;
    }
    $uniqueCandidates = collectCandidatesFromFile($content, $uniqueCandidates);
}

$candidates = array_keys($uniqueCandidates);
sort($candidates, SORT_STRING | SORT_FLAG_CASE);

if ($outputPath !== null) {
    if (@file_put_contents($outputPath, implode("\n", $candidates)."\n") === false) {
        fwrite(STDERR, "Failed to write output file: {$outputPath}\n");
        exit(1);
    }
    fwrite(STDOUT, sprintf("Wrote %d candidates to %s\n", count($candidates), $outputPath));
} else {
    fwrite(STDOUT, implode("\n", $candidates)."\n");
}
