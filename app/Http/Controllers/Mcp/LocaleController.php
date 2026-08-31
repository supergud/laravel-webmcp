<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets an agent switch the shop's language.
 *
 * The interesting part is the returned URL. Storefront routes carry the locale
 * in the path, so switching language means rewriting the path - and the
 * rewrite is done here rather than in the browser because the list of served
 * locales lives here.
 *
 * Only the first path segment is ever touched, and only when it is already a
 * locale we serve. The caller's path is otherwise treated as opaque, so this
 * cannot be used to build a URL pointing anywhere else.
 */
class LocaleController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', Locales::codes())],
            'path' => ['nullable', 'string', 'max:2048'],
        ]);

        $locale = Locales::sanitize($validated['locale']);

        $request->session()->put(config('localization.session_key'), $locale);
        app()->setLocale($locale);

        return response()->json([
            'ok' => true,
            'locale' => $locale,
            'locale_name' => Locales::nativeName($locale),
            'available' => Locales::codes(),
            'url' => $this->localizedPath($validated['path'] ?? null, $locale),
        ]);
    }

    /**
     * Swap the locale segment of a path this application served.
     */
    private function localizedPath(?string $path, string $locale): string
    {
        // Anything that is not a plain, relative path is discarded outright
        // rather than repaired, so a scheme, a host or a protocol-relative
        // prefix can never survive into the URL handed back.
        if ($path === null || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return route('home', ['locale' => $locale]);
        }

        $segments = explode('/', ltrim(parse_url($path, PHP_URL_PATH) ?: '/', '/'));

        // For "/" the first segment is an empty string, which is not a served
        // locale, so a bare root falls through to the home page like anything
        // else that does not already carry a locale.
        if (! Locales::isSupported($segments[0])) {
            return route('home', ['locale' => $locale]);
        }

        $segments[0] = $locale;

        return url('/'.implode('/', $segments));
    }
}
