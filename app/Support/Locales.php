<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\App;

/**
 * The single source of truth for which locales exist and whether a given
 * string is one of them.
 *
 * Locale strings reach the application from URL segments and from the WebMCP
 * `set_locale` tool. Both are attacker-controlled, so every entry point funnels
 * through isSupported() before the value goes anywhere near App::setLocale(),
 * a file path, or a database column name.
 */
final class Locales
{
    /**
     * @return array<string, array{name: string, native: string, regional: string, flag: string}>
     */
    public static function all(): array
    {
        /** @var array<string, array{name: string, native: string, regional: string, flag: string}> $locales */
        $locales = config('localization.supported', []);

        return $locales;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::all());
    }

    public static function fallback(): string
    {
        /** @var string $fallback */
        $fallback = config('app.fallback_locale', 'en');

        return self::isSupported($fallback) ? $fallback : (self::codes()[0] ?? 'en');
    }

    /**
     * Normalise any untrusted value to a locale we actually serve.
     */
    public static function sanitize(?string $locale): string
    {
        return self::isSupported($locale) ? $locale : self::fallback();
    }

    public static function current(): string
    {
        return self::sanitize(App::getLocale());
    }

    /**
     * The human-readable name of a locale, in that locale.
     */
    public static function nativeName(string $locale): string
    {
        return self::all()[$locale]['native'] ?? $locale;
    }
}
