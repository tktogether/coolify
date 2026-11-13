<?php

namespace App\Http\Middleware;

use App\Services\Localize\HtmlLocalizer;
use App\Services\Localize\LivewirePayloadLocalizer;
use App\Services\LocalizeDictionary;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Translates outgoing web responses from English to Chinese at the very end
 * of the pipeline, using the runtime dictionaries in resources/localization.
 *
 * Content-Type decides what happens: text/html bodies go through HtmlLocalizer,
 * application/json bodies (Livewire update responses) through
 * LivewirePayloadLocalizer, and everything else — downloads, streams, binary —
 * passes through byte-identical. When `localize.enabled` is false nothing is
 * touched at all. Any exception inside the translation step returns the
 * original response, so a broken dictionary can never break a page.
 */
class LocalizeResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('localize.enabled')) {
            return $response;
        }

        return $this->translate($response);
    }

    private function translate(Response $response): Response
    {
        try {
            $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

            if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/json')) {
                return $response;
            }

            $content = $response->getContent();

            if (! is_string($content) || $content === '') {
                return $response;
            }

            $map = LocalizeDictionary::map();

            if ($map === []) {
                return $response;
            }

            $translated = str_contains($contentType, 'text/html')
                ? HtmlLocalizer::translate($content, $map)
                : LivewirePayloadLocalizer::translate($content, $map);

            if ($translated === $content) {
                return $response;
            }

            $response->setContent($translated);
            $response->headers->remove('Content-Length');

            return $response;
        } catch (\Throwable $e) {
            Log::warning('Localization failed, serving the original response.', ['exception' => $e]);

            return $response;
        }
    }
}
