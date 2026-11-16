<?php

namespace App\Console\Commands;

use App\Services\Localize\BladeTextExtractor;
use App\Services\LocalizeDictionary;
use Illuminate\Console\Command;

/**
 * Writes tests/Fixtures/localize/untranslated-baseline.txt, the list of Blade
 * strings that BladeTextCoverageTest accepts as English on purpose.
 *
 * Run it after a reviewed test failure, when the new strings really should stay
 * English (an example value, a sentence a link cuts apart). Never run it to hide
 * a label that only needs a translation: add the dictionary entry instead.
 */
class LocalizeBaseline extends Command
{
    protected $signature = 'localize:baseline {--show : Print the list instead of writing it}';

    protected $description = 'Write the accepted-English baseline for the Blade text coverage test';

    public function handle(): int
    {
        LocalizeDictionary::flush();
        $map = LocalizeDictionary::map();

        $untranslated = [];

        $sources = array_merge(
            BladeTextExtractor::fromDirectory(resource_path('views')),
            BladeTextExtractor::fromPhpSources(app_path()),
        );

        foreach ($sources as $values) {
            foreach ($values as $value) {
                if (! isset($map[$value]) && self::isCandidate($value)) {
                    $untranslated[$value] = true;
                }
            }
        }

        $keys = array_keys($untranslated);
        sort($keys, SORT_FLAG_CASE | SORT_STRING);

        if ($this->option('show')) {
            $this->line(implode(PHP_EOL, $keys));

            return self::SUCCESS;
        }

        $path = self::path();
        $header = "# Strings the Blade text coverage test accepts as English on purpose.\n"
            ."# Regenerate with `php artisan localize:baseline` after a reviewed run.\n";

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $header.implode("\n", $keys)."\n");
        $this->info('Wrote '.count($keys).' entries to '.$path);

        return self::SUCCESS;
    }

    public static function path(): string
    {
        return base_path('tests/Fixtures/localize/untranslated-baseline.txt');
    }

    /**
     * Same rule as the test: a candidate is a string the runtime could match.
     */
    public static function isCandidate(string $value): bool
    {
        if (mb_strlen($value) < 3 || preg_match('/[A-Za-z]{2,}/', $value) !== 1) {
            return false;
        }

        foreach ([
            '/[{};$`\[\]]|=>|::|->|@\w/',
            '/^(<!DOCTYPE|\/|~\/|\.\/|https?:\/\/|#|--|\d)/',
            '/^(FROM|CMD|EXPOSE|RUN|COPY|ADD|SELECT|CREATE|BEGIN|POST|GET|PUT|DELETE|services:|ports:|placement:)/',
            '/^[A-Z][A-Z0-9_]{2,}$/',
            '/\b(artisan|compose build|compose up|journalctl|pg_isready|nvidia-smi|docker login|ssh-ed25519)\b/',
            '/\.(conf|json|yml|yaml|sql|sqlite|gz|php|js|md|local)$/',
            '/^[a-z0-9.\-]+\.(com|io|dev|net|org|local)\b/i',
            '/^\d{1,3}(\.\d{1,3}){3}/',
            '/^[^@\s]+@[^@\s]+$/',
            '/^(ghcr\.io|docker\.io|coollabsio)\//',
        ] as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return false;
            }
        }

        return true;
    }
}
