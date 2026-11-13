<?php

namespace App\Services;

class LocalizeDictionary
{
    private static ?array $map = null;

    /**
     * English phrase to Chinese translation map.
     *
     * Keys are full English phrases (exact case and punctuation). The array
     * shape is directly usable by strtr(), which prefers the longest matching
     * key automatically. The map is loaded once from the dictionary directory
     * and cached until flush() is called.
     *
     * @param  ?string  $dir  Dictionary directory; defaults to resources/localization/zh-cn.
     * @return array<string, string>
     */
    public static function map(?string $dir = null): array
    {
        if (self::$map === null) {
            $files = glob(($dir ?? resource_path('localization/zh-cn')).'/*.php') ?: [];
            sort($files);

            $map = [];
            foreach ($files as $file) {
                $map = array_merge($map, require $file);
            }

            self::$map = $map;
        }

        return self::$map;
    }

    /**
     * Inject entries into the cached map, for tests and runtime patches.
     *
     * @param  array<string, string>  $entries
     */
    public static function merge(array $entries): void
    {
        self::$map ??= [];

        self::$map = array_merge(self::$map, $entries);
    }

    /**
     * Clear the cached map; the next map() call reloads from disk.
     */
    public static function flush(): void
    {
        self::$map = null;
    }
}
