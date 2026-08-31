<?php

declare(strict_types=1);

it('redirects the bare root to a localized home page', function (): void {
    $this->get('/')->assertRedirect(route('home', ['locale' => 'en']));
});

it('serves every supported locale', function (string $locale): void {
    $this->get("/{$locale}")->assertOk();
})->with(['en', 'zh-TW']);

it('sets the application locale from the url segment', function (): void {
    $this->get('/zh-TW');

    expect(app()->getLocale())->toBe('zh-TW');
});

it('translates strings according to the url locale', function (): void {
    $this->get('/zh-TW');
    expect(__('shop.nav.cart'))->toBe('購物車');

    $this->get('/en');
    expect(__('shop.nav.cart'))->toBe('Cart');
});

it('rejects locales that are not on the whitelist', function (string $locale): void {
    $this->get("/{$locale}")->assertNotFound();
})->with([
    'unsupported language' => 'fr',
    'regional variant we do not serve' => 'zh-CN',
    'path traversal attempt' => '..%2F..%2Fetc',
    'sql-ish payload' => "en'--",
]);

it('remembers the locale for routes that have no locale segment', function (): void {
    $this->get('/zh-TW');

    // The login page is registered by Fortify at the root, without a prefix.
    $this->get('/login')->assertOk();

    expect(app()->getLocale())->toBe('zh-TW');
});

it('advertises the served language in the response headers', function (): void {
    $this->get('/zh-TW')->assertHeader('Content-Language', 'zh-TW');
});

it('switches locale via the session route', function (): void {
    $this->get(route('locale.switch', ['locale' => 'zh-TW']))
        ->assertRedirect();

    expect(session(config('localization.session_key')))->toBe('zh-TW');
});

it('never redirects to an external host when switching locale', function (): void {
    $response = $this->get(
        route('locale.switch', ['locale' => 'zh-TW']),
        ['referer' => 'https://evil.example.com/phish'],
    );

    $location = $response->headers->get('Location');

    expect($location)->not->toContain('evil.example.com');
});
