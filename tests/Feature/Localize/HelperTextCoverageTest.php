<?php

use App\Services\Localize\HtmlLocalizer;

/**
 * Guard: every static helper="…" text in the Blade views must render at least
 * partly in Chinese. A helper that no dictionary entry reaches stays fully
 * English on screen, which is exactly the regression this batch fixed.
 *
 * Dynamic helpers (Blade interpolation, PHP expressions) are skipped: their
 * rendered text is not known at build time.
 */
test('every static helper text is translated', function () {
    $map = [];
    foreach (glob(resource_path('localization/zh-cn').'/*.php') as $file) {
        $entries = require $file;
        if (is_array($entries)) {
            $map = array_merge($map, $entries);
        }
    }

    $untouched = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (! preg_match_all('/(?<![:\\w-])helper\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\')/s', $source, $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as $match) {
            $raw = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

            if (trim($raw) === '' || str_contains($raw, '{{') || str_contains($raw, '->') || str_contains($raw, '::')) {
                continue;
            }

            // A helper that is only a __('…') call is translated through lang files.
            if (preg_match('/^\\s*\\{!!\\s*__\\(/', $raw) === 1) {
                continue;
            }

            $html = '<div>'.$raw.'</div>';

            if (HtmlLocalizer::translate($html, $map) === $html) {
                $untouched[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname()).': '.mb_substr($raw, 0, 90);
            }
        }
    }

    expect($untouched)->toBe([]);
});
