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
 *
 * HTML pages also get the client companion script (public/js/localize-client.js)
 * appended before </body>: it exact-matches a small whitelist of enum words that
 * Alpine renders client-side from Livewire data (x-text), which the server-side
 * pass can never reach. The whitelist only contains fixed UI words and matches
 * whole trimmed text nodes, so user data is never touched.
 */
class LocalizeResponse
{
    private const CLIENT_SCRIPT = '<script src="/js/localize-client.js" defer></script>';

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

            if (str_contains($contentType, 'text/html') && str_contains($translated, '</body>')) {
                $translated = $this->injectClientScript($translated);
            }

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

    /**
     * Append the client companion script before the last </body> tag. The tag
     * carries a version fingerprint so browsers refetch it when the file
     * changes with a new image.
     */
    private function injectClientScript(string $html): string
    {
        $version = substr(sha1((string) filemtime(public_path('js/localize-client.js'))), 0, 10);
        $tag = '<script src="/js/localize-client.js?v='.$version.'" defer></script>';
        $position = strripos($html, '</body>');

        if ($position === false) {
            return $html;
        }

        return substr_replace($html, $tag, $position, 0);
    }
}
