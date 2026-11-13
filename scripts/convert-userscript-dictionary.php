<?php

/**
 * Userscript dictionary converter (development tool, not part of the build or runtime).
 *
 * Converts the Chinese (zh-cn) UI dictionary of the MIT-licensed Tampermonkey
 * userscript "Coolify 中文汉化增强版" (https://greasyfork.org/en/scripts/580682)
 * into resources/localization/zh-cn/seed.php, which App\Services\LocalizeDictionary
 * loads automatically together with the other resources/localization/zh-cn/*.php files.
 *
 * The userscript carries two literal English -> Chinese mapping blocks that are
 * imported (both contain string-literal pairs only):
 *   - const EXACT   = new Map(Object.entries({ 'English': '中文', ... }))
 *   - const PHRASES = [ ['English', '中文'], ... ]
 * The REGEX_RULES block (dynamic patterns with capture groups) and the inline
 * word-level .replace() chain are NOT importable as exact-match dictionary
 * entries and are skipped.
 *
 * Entry policy (plan Global Constraint 禁止单词级键):
 *   - dropped:  key shorter than 2 characters, or empty value
 *   - deferred: single-word keys (keys without any whitespace) are written to
 *               storage/app/localize-review/single-word-keys.txt for manual
 *               review instead of the runtime dictionary. The review list sits
 *               outside resources/localization/ so the dictionary loader never
 *               sees it (and is git-ignored via storage/app/.gitignore).
 *   - imported: everything else; keys are kept verbatim as full phrases.
 *
 * When the same key appears in both blocks, the EXACT value wins, mirroring the
 * userscript's lookup order (EXACT before PHRASES).
 *
 * Usage:
 *   php scripts/convert-userscript-dictionary.php build/userscript/<script>.user.js
 *
 * The downloaded .user.js is untrusted data: it is only read and parsed here,
 * never executed, and must live outside the repository (e.g. build/userscript/).
 */

declare(strict_types=1);

const SEED_PATH = __DIR__.'/../resources/localization/zh-cn/seed.php';
const REVIEW_LIST_PATH = __DIR__.'/../storage/app/localize-review/single-word-keys.txt';

function usage(int $exitCode = 0): never
{
    $lines = [
        'Usage: php scripts/convert-userscript-dictionary.php <path/to/script.user.js>',
        '',
        'Converts the MIT-licensed userscript dictionary into',
        'resources/localization/zh-cn/seed.php and writes the deferred single-word',
        'keys to storage/app/localize-review/single-word-keys.txt.',
    ];
    fwrite($exitCode === 0 ? STDOUT : STDERR, implode("\n", $lines)."\n");
    exit($exitCode);
}

/**
 * Extract the literal body of a "const NAME = ... { ... };" / "const NAME = [ ... ];"
 * block between its declaration and its closer, without evaluating any code.
 */
function extractBlock(string $source, string $declaration, string $closer, string $label): string
{
    $start = strpos($source, $declaration);
    if ($start === false) {
        fwrite(STDERR, "Failed to find the {$label} block ({$declaration}...) in the userscript.\n");
        exit(1);
    }
    $end = strpos($source, $closer, $start + strlen($declaration));
    if ($end === false) {
        fwrite(STDERR, "Failed to find the end of the {$label} block ({$closer}) in the userscript.\n");
        exit(1);
    }

    return substr($source, $start + strlen($declaration), $end - $start - strlen($declaration));
}

/**
 * Decode the JS string escapes that can appear in the dictionary literals.
 */
function decodeJsString(string $raw): string
{
    return (string) preg_replace_callback(
        '/\\\\(u[0-9a-fA-F]{4}|x[0-9a-fA-F]{2}|.)/',
        fn (array $match): string => match ($match[1][0]) {
            'u', 'x' => mb_chr((int) hexdec(substr($match[1], 1)), 'UTF-8'),
            'n' => "\n",
            't' => "\t",
            'r' => "\r",
            default => $match[1],
        },
        $raw
    );
}

/**
 * Parsed line of a mapping block: a comment, a key/value entry, or a blank line.
 *
 * @return array{type: 'comment'|'entry'|'blank', key?: string, value?: string, raw?: string}
 */
function parseDictionaryLine(string $line, string $entryRegex): array
{
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '//')) {
        return ['type' => $trimmed === '' ? 'blank' : 'comment', 'raw' => $trimmed];
    }
    if (! preg_match($entryRegex, $trimmed, $match)) {
        fwrite(STDERR, "Unparseable dictionary line: {$trimmed}\n");
        exit(1);
    }

    return [
        'type' => 'entry',
        'key' => decodeJsString(trim($match[1])),
        'value' => decodeJsString(trim($match[2])),
    ];
}

/**
 * @return list<array{type: 'comment'|'entry'|'blank', key?: string, value?: string, raw?: string}>
 */
function parseBlock(string $block, string $entryRegex): array
{
    $lines = [];
    foreach (preg_split("/\r\n|\n|\r/", $block) as $line) {
        $lines[] = parseDictionaryLine($line, $entryRegex);
    }

    return $lines;
}

function isSingleWordKey(string $key): bool
{
    return preg_match('/\s/u', $key) === 0;
}

/**
 * Escape a string for a PHP single-quoted string literal.
 */
function phpSingleQuote(string $value): string
{
    return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
}

function readMeta(string $source, string $field): string
{
    preg_match('/@'.preg_quote($field, '/').'\s+([^\r\n]+)/', $source, $match);

    return isset($match[1]) ? trim($match[1]) : 'unknown';
}

$scriptPath = $argv[1] ?? null;
if ($scriptPath === null || $scriptPath === '--help' || $scriptPath === '-h') {
    usage($scriptPath === null ? 1 : 0);
}
if (count($argv) > 2) {
    fwrite(STDERR, "Unknown argument: {$argv[2]}\n");
    usage(1);
}
if (! is_file($scriptPath)) {
    fwrite(STDERR, "Userscript not found: {$scriptPath}\n");
    exit(1);
}

$source = file_get_contents($scriptPath);
if ($source === false) {
    fwrite(STDERR, "Failed to read userscript: {$scriptPath}\n");
    exit(1);
}

$exactLines = parseBlock(
    extractBlock($source, 'const EXACT = new Map(Object.entries({', '}));', 'EXACT'),
    "/^'((?:\\\\.|[^'\\\\])*)'[\t ]*:[\t ]*'((?:\\\\.|[^'\\\\])*)'[\t ]*,?$/u"
);
$phraseLines = parseBlock(
    extractBlock($source, 'const PHRASES = [', '];', 'PHRASES'),
    "/^\[[\t ]*'((?:\\\\.|[^'\\\\])*)'[\t ]*,[\t ]*'((?:\\\\.|[^'\\\\])*)'[\t ]*\][\t ]*,?$/u"
);

// Merge with the userscript's precedence: PHRASES first, EXACT overrides.
$entries = []; // key => ['key', 'value', 'section'], unique keys
$stats = [
    'exactParsed' => 0,
    'phraseParsed' => 0,
    'droppedShortKey' => 0,
    'droppedEmptyValue' => 0,
    'singleWordDeferred' => 0,
    'phraseOverridesByExact' => 0,
    'identity' => 0,
    'chineseKeys' => 0,
];

foreach ($phraseLines as $line) {
    if ($line['type'] !== 'entry') {
        continue;
    }
    $stats['phraseParsed']++;
    $entries[$line['key']] = ['key' => $line['key'], 'value' => $line['value'], 'section' => 'PHRASES'];
}

foreach ($exactLines as $line) {
    if ($line['type'] !== 'entry') {
        continue;
    }
    $stats['exactParsed']++;
    if (isset($entries[$line['key']])) {
        $stats['phraseOverridesByExact']++;
        unset($entries[$line['key']]);
    }
    $entries[$line['key']] = ['key' => $line['key'], 'value' => $line['value'], 'section' => 'EXACT'];
}

// Classify every merged entry: seed, single-word review list, or dropped.
$seedMap = []; // key => value, in merged order (EXACT entries first, then remaining PHRASES)
foreach ($entries as $entry) {
    $key = $entry['key'];
    $value = $entry['value'];
    if (mb_strlen($key) < 2) {
        $stats['droppedShortKey']++;

        continue;
    }
    if (trim($value) === '') {
        $stats['droppedEmptyValue']++;

        continue;
    }
    if ($key === $value) {
        $stats['identity']++;
    }
    if (preg_match('/\p{Han}/u', $key)) {
        $stats['chineseKeys']++;
    }
    if (isSingleWordKey($key)) {
        $stats['singleWordDeferred']++;
        $reviewList[] = ['key' => $key, 'value' => $value];

        continue;
    }
    $seedMap[$key] = $value;
}

// Render the seed body: EXACT section (with its source comments, pruned when a
// section has no surviving entries) followed by the surviving PHRASES section.
$rendered = []; // list<string>
$pendingComments = [];
foreach ($exactLines as $line) {
    if ($line['type'] === 'comment') {
        $pendingComments[] = '    '.$line['raw'];

        continue;
    }
    if ($line['type'] !== 'entry' || ! isset($seedMap[$line['key']])) {
        continue; // blank, dropped, or deferred
    }
    $rendered = array_merge($rendered, $pendingComments);
    $pendingComments = [];
    $rendered[] = "    '".phpSingleQuote($line['key'])."' => '".phpSingleQuote($seedMap[$line['key']])."',";
}
$phraseRendered = [];
foreach ($entries as $entry) {
    if ($entry['section'] === 'PHRASES' && isset($seedMap[$entry['key']])) {
        $phraseRendered[] = "    '".phpSingleQuote($entry['key'])."' => '".phpSingleQuote($seedMap[$entry['key']])."',";
    }
}
if ($phraseRendered !== []) {
    $rendered[] = '    // ===== Long phrases (PHRASES block) =====';
    $rendered = array_merge($rendered, $phraseRendered);
}

$version = readMeta($source, 'version');
$scriptAuthor = readMeta($source, 'author');
$rawScriptUrl = readMeta($source, 'downloadURL');
$generatedAt = date('Y-m-d');
$sourceUrl = 'https://greasyfork.org/en/scripts/580682';

$header = <<<PHP
<?php

/*
 * Chinese (zh-cn) UI dictionary seed — converted from a MIT-licensed browser
 * userscript. This file is a generated artifact: do not edit it by hand,
 * regenerate it with scripts/convert-userscript-dictionary.php instead.
 *
 * Source:      {$sourceUrl} ("Coolify 中文汉化增强版", userscript v{$version})
 * Raw script:  {$rawScriptUrl}
 * Author:      xd lee (Greasy Fork account; script header author: "{$scriptAuthor}")
 * License:     MIT (https://spdx.org/licenses/MIT.html)
 *
 * Loaded automatically by App\Services\LocalizeDictionary together with the
 * other resources/localization/zh-cn/*.php files. Keys are full English
 * phrases kept verbatim from the source; single-word keys are excluded from
 * this runtime dictionary (Global Constraint 禁止单词级键) and deferred to
 * storage/app/localize-review/single-word-keys.txt for manual review.
 *
 * Generated {$generatedAt} from {$stats['exactParsed']} EXACT + {$stats['phraseParsed']} PHRASES source pairs:
 * {$stats['phraseOverridesByExact']} duplicates resolved in favor of EXACT, {$stats['droppedShortKey']} dropped for keys
 * shorter than 2 characters, {$stats['droppedEmptyValue']} dropped for empty values, {$stats['singleWordDeferred']}
 * single-word keys deferred to the review list.
 */

return [
PHP;

$seedPhp = $header."\n".implode("\n", $rendered)."\n];\n";

$seedDirectory = dirname(SEED_PATH);
if (! is_dir($seedDirectory)) {
    mkdir($seedDirectory, 0755, true);
}
if (@file_put_contents(SEED_PATH, $seedPhp) === false) {
    fwrite(STDERR, 'Failed to write seed file: '.SEED_PATH."\n");
    exit(1);
}

// Write the single-word review list (outside the dictionary directory, so the
// loader never sees it; git-ignored via storage/app/.gitignore).
$reviewDirectory = dirname(REVIEW_LIST_PATH);
if (! is_dir($reviewDirectory)) {
    mkdir($reviewDirectory, 0755, true);
}
usort($reviewList, fn (array $a, array $b): int => strcasecmp($a['key'], $b['key']));
$reviewLines = [
    '# Single-word keys deferred from the zh-cn runtime dictionary.',
    '# Source: '.$sourceUrl.' (userscript v'.$version.', MIT) — converted '.date('Y-m-d'),
    '# Global Constraint 禁止单词级键: single-word English keys must not enter',
    '# resources/localization/zh-cn/*.php. Review each pair; merge the useful',
    '# ones into resources/localization/zh-cn/common.php (or a new file) by hand.',
    '# Format: English => 中文',
    '',
];
foreach ($reviewList as $entry) {
    $reviewLines[] = $entry['key'].' => '.$entry['value'];
}
if (@file_put_contents(REVIEW_LIST_PATH, implode("\n", $reviewLines)."\n") === false) {
    fwrite(STDERR, 'Failed to write review list: '.REVIEW_LIST_PATH."\n");
    exit(1);
}

// Self-verify the generated artifact.
$loaded = require SEED_PATH;
if (! is_array($loaded)) {
    fwrite(STDERR, "Self-check failed: seed.php did not return an array.\n");
    exit(1);
}
if (count($loaded) !== count($seedMap)) {
    fwrite(STDERR, sprintf("Self-check failed: expected %d entries, seed.php returned %d.\n", count($seedMap), count($loaded)));
    exit(1);
}
foreach ($loaded as $key => $value) {
    if (! is_string($key) || ! is_string($value) || trim($value) === '' || mb_strlen($key) < 2 || isSingleWordKey($key)) {
        fwrite(STDERR, "Self-check failed: invalid entry in seed.php: {$key} => {$value}\n");
        exit(1);
    }
}

fwrite(STDOUT, sprintf(
    "Parsed %d EXACT + %d PHRASES pairs (userscript v%s).\n".
    "Duplicates resolved in favor of EXACT: %d\n".
    "Dropped (key < 2 chars): %d\n".
    "Dropped (empty value): %d\n".
    "Single-word keys deferred to %s: %d\n".
    "Imported into %s: %d entries\n".
    "(informational: %d identity mappings, %d keys containing Chinese kept as-is)\n".
    "Self-check passed: seed.php returns %d valid entries.\n",
    $stats['exactParsed'],
    $stats['phraseParsed'],
    $version,
    $stats['phraseOverridesByExact'],
    $stats['droppedShortKey'],
    $stats['droppedEmptyValue'],
    REVIEW_LIST_PATH,
    $stats['singleWordDeferred'],
    SEED_PATH,
    count($seedMap),
    $stats['identity'],
    $stats['chineseKeys'],
    count($loaded)
));
