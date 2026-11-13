<?php

namespace App\Services\Localize;

class LivewirePayloadLocalizer
{
    /**
     * Translate the user-visible strings inside a Livewire update-response JSON
     * body.
     *
     * Only two things may change: each component's `effects.html` fragment goes
     * through HtmlLocalizer, and each entry of `effects.dispatches` has its
     * `params` string elements replaced by exact full-string dictionary lookup
     * (isset, never substring scanning). The snapshot, memo, and every other
     * field keep their values untouched. When nothing translates, the original
     * body is returned byte-identical; invalid JSON or any exception also
     * returns the original body.
     *
     * @param  string  $body  Raw response body, expected to be Livewire JSON.
     * @param  array<string, string>  $map  Full English phrase => translation.
     */
    public static function translate(string $body, array $map): string
    {
        if ($body === '' || $map === []) {
            return $body;
        }

        try {
            $data = json_decode($body, true);

            if (! is_array($data) || ! is_array($data['components'] ?? null)) {
                return $body;
            }

            $changed = false;

            foreach ($data['components'] as $key => $component) {
                if (! is_array($component)) {
                    continue;
                }

                $effects = $component['effects'] ?? null;

                if (! is_array($effects)) {
                    continue;
                }

                $translated = self::translateEffects($effects, $map);

                if ($translated === $effects) {
                    continue;
                }

                $data['components'][$key]['effects'] = $translated;
                $changed = true;
            }

            if (! $changed) {
                return $body;
            }

            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? $body : $encoded;
        } catch (\Throwable) {
            return $body;
        }
    }

    /**
     * Translate the html effect and the dispatch params of one component's
     * effects, keeping every other effects entry untouched.
     *
     * @param  array<string, mixed>  $effects
     * @param  array<string, string>  $map
     * @return array<string, mixed> The translated effects; identical to the input when nothing translated.
     */
    private static function translateEffects(array $effects, array $map): array
    {
        $translated = $effects;

        $html = $effects['html'] ?? null;

        if (is_string($html)) {
            $translated['html'] = HtmlLocalizer::translate($html, $map);
        }

        $dispatches = $effects['dispatches'] ?? null;

        if (is_array($dispatches)) {
            $translated['dispatches'] = self::translateDispatchParams($dispatches, $map);
        }

        return $translated;
    }

    /**
     * Replace each pure-string element of a dispatch's params via exact
     * full-string dictionary lookup. Non-string elements (numbers, null,
     * arrays) and every other dispatch field pass through untouched.
     *
     * @param  list<mixed>  $dispatches
     * @param  array<string, string>  $map
     * @return list<mixed>
     */
    private static function translateDispatchParams(array $dispatches, array $map): array
    {
        foreach ($dispatches as $index => $dispatch) {
            if (! is_array($dispatch)) {
                continue;
            }

            $params = $dispatch['params'] ?? null;

            if (! is_array($params)) {
                continue;
            }

            foreach ($params as $paramIndex => $param) {
                if (! is_string($param)) {
                    continue;
                }

                $params[$paramIndex] = isset($map[$param]) ? $map[$param] : $param;
            }

            $dispatch['params'] = $params;
            $dispatches[$index] = $dispatch;
        }

        return $dispatches;
    }
}
