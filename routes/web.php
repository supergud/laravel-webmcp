<?php

use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
|
| Public pages carry the locale in the URL so a link is shareable and an AI
| agent can navigate straight to a specific language. Account pages (dashboard,
| settings) and Fortify's auth routes stay unprefixed and take their language
| from the session instead - see App\Http\Middleware\SetLocale.
|
*/

Route::get('/', function (Request $request) {
    $locale = $request->session()->get(config('localization.session_key'))
        ?? $request->getPreferredLanguage(Locales::codes());

    return redirect()->route('home', ['locale' => Locales::sanitize(is_string($locale) ? $locale : null)]);
});

Route::prefix('{locale}')
    ->whereIn('locale', Locales::codes())
    ->group(function () {
        Route::livewire('/', 'pages::shop.products')->name('home');
        // The parameter is {slug}, not {product}: a route parameter whose name
        // matches a Livewire component property typed as a model makes Livewire
        // try to resolve it as one, which 404s before mount() ever runs.
        Route::livewire('products/{slug}', 'pages::shop.product')->name('products.show');
    });

/*
|--------------------------------------------------------------------------
| Language switching
|--------------------------------------------------------------------------
|
| Used only by pages that have no {locale} segment of their own (auth, account).
| Storefront links switch language by rewriting the URL prefix instead.
|
*/

Route::get('locale/{locale}', function (Request $request, string $locale) {
    $request->session()->put(config('localization.session_key'), Locales::sanitize($locale));

    $previous = url()->previous();

    // Never bounce the visitor to a host we do not control: url()->previous()
    // is derived from the Referer header, which the client sets.
    $isLocal = parse_url($previous, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST);

    return redirect()->to($isLocal ? $previous : route('home', ['locale' => Locales::sanitize($locale)]));
})->whereIn('locale', Locales::codes())->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Account
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
