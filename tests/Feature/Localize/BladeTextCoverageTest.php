<?php

use App\Console\Commands\LocalizeBaseline;
use App\Services\Localize\BladeTextExtractor;
use App\Services\Localize\HtmlLocalizer;
use App\Services\LocalizeDictionary;

/**
 * Coverage gate for the strings the runtime localizer can reach in a Blade
 * template: text nodes and the values of title, placeholder, aria-label, and
 * data-tooltip.
 *
 * A string passes when the dictionary translates it. Everything else must be
 * listed in tests/Fixtures/localize/untranslated-baseline.txt, which records the
 * strings that are accepted as English on purpose:
 *
 *  - fragments: a sentence that an inline tag, a link, or a Blade echo cuts
 *    apart. The runtime sees the pieces as separate text nodes, so no single key
 *    can cover them, and translating a piece alone reads as half-Chinese.
 *  - examples: placeholders such as `docker compose build`, hostnames, IPs, paths.
 *  - products: brand and format names that stay untranslated.
 *
 * When a template gains a new label, this test fails. Add the translation to the
 * dictionary, or add the string to the baseline with a category in
 * tests/Fixtures/localize/untranslated-baseline.txt.
 *
 * Regenerate the baseline after a reviewed run:
 *   UPDATE_LOCALIZE_BASELINE=1 php artisan test --compact tests/Feature/Localize/BladeTextCoverageTest.php
 */
const BASELINE_PATH = __DIR__.'/../../Fixtures/localize/untranslated-baseline.txt';

function coverageDictionary(): array
{
    LocalizeDictionary::flush();

    return LocalizeDictionary::map();
}

/**
 * A string the runtime can actually match. The rule lives in the command that
 * writes the baseline, so the test and the generator cannot drift apart.
 */
function isTranslatableCandidate(string $value): bool
{
    return LocalizeBaseline::isCandidate($value);
}

function untranslatedStrings(): array
{
    $map = coverageDictionary();
    $untranslated = [];

    foreach (BladeTextExtractor::fromDirectory(resource_path('views')) as $values) {
        foreach ($values as $value) {
            if (isset($map[$value]) || ! isTranslatableCandidate($value)) {
                continue;
            }

            $untranslated[$value] = true;
        }
    }

    $keys = array_keys($untranslated);
    sort($keys, SORT_FLAG_CASE | SORT_STRING);

    return $keys;
}

function readBaseline(): array
{
    if (! file_exists(BASELINE_PATH)) {
        return [];
    }

    $lines = file(BASELINE_PATH, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    return array_values(array_filter(
        $lines,
        static fn (string $line): bool => ! str_starts_with($line, '#') && ! str_starts_with($line, '//'),
    ));
}

test('every reachable blade string is translated or in the baseline', function () {
    $untranslated = untranslatedStrings();
    $baseline = readBaseline();
    $new = array_values(array_diff($untranslated, $baseline));

    expect($new)->toBe([], 'These strings are neither translated nor in the baseline: '.PHP_EOL.implode(PHP_EOL, $new));
});

test('the baseline has no stale entries', function () {
    $baseline = readBaseline();
    $untranslated = untranslatedStrings();

    // A baseline entry that is now translated (or gone) should be removed, so the
    // file keeps describing what is really left.
    $stale = array_values(array_diff($baseline, $untranslated));

    expect($stale)->toBe([], 'These baseline entries are translated or gone, remove them: '.PHP_EOL.implode(PHP_EOL, $stale));
});

test('a translated string is not in the baseline', function () {
    $map = coverageDictionary();

    // Guards against adding an entry to both places, which would hide the gate.
    $both = array_values(array_filter(readBaseline(), static fn (string $value): bool => isset($map[$value])));

    expect($both)->toBe([], 'These are in the baseline but already translated: '.PHP_EOL.implode(PHP_EOL, $both));
});

test('the extractor reduces blade code before it reads text', function () {
    $template = <<<'BLADE'
<div>
    @if ($active)
        <span class="x" title="Delete server">Delete server</span>
    @endif
    {{ $name }} > Backups | Coolify
    <x-forms.input :helper="filled($x) ? 'a' : 'b'" placeholder="Name" />
    <script>var label = 'Not text';</script>
</div>
BLADE;

    $values = BladeTextExtractor::fromTemplate($template);

    expect($values)->toContain('Delete server')
        ->and($values)->toContain('Name')
        ->and($values)->not->toContain('Not text')
        ->and(implode(' ', $values))->not->toContain('$active')
        ->and(implode(' ', $values))->not->toContain('filled');
});

test('the extractor keeps a title that follows an echo', function () {
    // Rendered as "<name> > Backups | Coolify" in one text node, so the tail is a
    // phrase key the localizer can match inside the node.
    $values = BladeTextExtractor::fromTemplate('{{ $name }} > Backups | Coolify');

    expect($values)->toContain('> Backups | Coolify');

    $map = ['> Backups | Coolify' => ' > 备份 | Coolify'];
    expect(HtmlLocalizer::translate('<title>my-app > Backups | Coolify</title>', $map))
        ->toBe('<title>my-app  > 备份 | Coolify</title>');
});

test('the extractor reads the static prefix of an interpolated prop', function () {
    // title="Delete {{ $resourceLabel }}" renders as "Delete resource" in one
    // text node, so the prefix is the part an entry has to start with.
    $values = BladeTextExtractor::fromTemplate('<x-danger-zone title="Delete {{ $resourceLabel }}">x</x-danger-zone>');

    expect($values)->toContain('Delete');

    $values = BladeTextExtractor::fromTemplate('<x-a label="Delete the login for {{ $name }}?">x</x-a>');

    expect($values)->toContain('Delete the login for');
});

test('the extractor skips a prop that starts with the echo', function () {
    // No static text to translate, and the value is user data.
    $values = BladeTextExtractor::fromTemplate('<x-a title="{{ $name }}">x</x-a>');

    expect(implode(' ', $values))->not->toContain('$name');
});

test('an interpolated prop label translates once the value is known', function () {
    $map = coverageDictionary();

    // The rendered node the danger zone shows for a standalone database.
    expect(HtmlLocalizer::translate('<h4>Delete resource</h4>', $map))->toBe('<h4>删除资源</h4>')
        ->and(HtmlLocalizer::translate('<h4>Delete application</h4>', $map))->toBe('<h4>删除应用</h4>');
});
