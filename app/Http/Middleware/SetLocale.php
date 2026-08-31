<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active locale for every web request.
 *
 * Storefront routes carry the locale in the URL (/zh-TW/products). Fortify's
 * auth routes do not, so the chosen locale is also mirrored into the session
 * and used as the fallback. This keeps the language stable when a visitor
 * moves between /zh-TW/cart and /login.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);

        $request->session()->put(config('localization.session_key'), $locale);

        // Lets route('products.index') work without threading {locale} through
        // every single route() call in the views.
        URL::defaults(['locale' => $locale]);

        $response = $next($request);

        // Helps caches and crawlers, and lets the WebMCP client see which
        // language the tool responses came back in.
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    private function resolve(Request $request): string
    {
        $fromRoute = $request->route('locale');

        if (is_string($fromRoute) && Locales::isSupported($fromRoute)) {
            return $fromRoute;
        }

        $fromSession = $request->session()->get(config('localization.session_key'));

        if (is_string($fromSession) && Locales::isSupported($fromSession)) {
            return $fromSession;
        }

        return Locales::sanitize($request->getPreferredLanguage(Locales::codes()));
    }
}
