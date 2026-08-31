<?php

namespace App\Providers;

use App\Support\Locales;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureLocale();
        $this->configureRateLimiting();
    }

    /**
     * Throttles for the WebMCP tool endpoints.
     *
     * Keyed by account when signed in and by IP otherwise. Deliberately not by
     * session id: a client that simply declines to send the session cookie is
     * handed a fresh session on every request, so a session-derived key would
     * be different every time and throttle nothing at all.
     *
     * An agent driving the tools runs inside the visitor's own browser, so it
     * shares that visitor's key and therefore their budget. Exposing a tool
     * surface must not hand automation a larger allowance than a person has.
     *
     * Writes are throttled harder than reads because they change state.
     */
    protected function configureRateLimiting(): void
    {
        $key = fn (Request $request): string => (string) (Auth::id() ?? $request->ip());

        RateLimiter::for(
            'mcp-read',
            fn (Request $request) => Limit::perMinute((int) config('shop.rate_limits.read'))->by($key($request)),
        );

        RateLimiter::for(
            'mcp-write',
            fn (Request $request) => Limit::perMinute((int) config('shop.rate_limits.write'))->by($key($request)),
        );
    }

    /**
     * Seed a default {locale} route parameter.
     *
     * Storefront routes are locale-prefixed, so route('home') needs a locale
     * even outside an HTTP request (tests, artisan, queued jobs). SetLocale
     * overrides this per-request with the visitor's actual language.
     */
    protected function configureLocale(): void
    {
        URL::defaults(['locale' => Locales::sanitize(config('app.locale'))]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
