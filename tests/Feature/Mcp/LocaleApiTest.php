<?php

declare(strict_types=1);
use Database\Seeders\CatalogSeeder;

it('switches the session locale', function (): void {
    $this->postJson('/api/mcp/locale', ['locale' => 'zh-TW'])
        ->assertOk()
        ->assertJsonPath('locale', 'zh-TW')
        ->assertJsonPath('locale_name', '繁體中文');

    expect(session(config('localization.session_key')))->toBe('zh-TW');
});

it('rewrites the locale segment of the current path', function (): void {
    $this->postJson('/api/mcp/locale', ['locale' => 'zh-TW', 'path' => '/en/products/aerobook-pro-14'])
        ->assertOk()
        ->assertJsonPath('url', url('/zh-TW/products/aerobook-pro-14'));
});

it('falls back to the home page when the path has no locale segment', function (): void {
    $this->postJson('/api/mcp/locale', ['locale' => 'zh-TW', 'path' => '/login'])
        ->assertOk()
        ->assertJsonPath('url', route('home', ['locale' => 'zh-TW']));
});

it('refuses a locale that is not served', function (string $locale): void {
    $this->postJson('/api/mcp/locale', ['locale' => $locale])
        ->assertStatus(422)
        ->assertJsonValidationErrors('locale');
})->with([
    'unsupported' => 'fr',
    'wrong chinese' => 'zh-CN',
    'traversal' => '../../etc/passwd',
    'empty' => '',
]);

it('never hands back a url pointing at another site', function (string $path): void {
    $response = $this->postJson('/api/mcp/locale', ['locale' => 'zh-TW', 'path' => $path])->assertOk();

    expect($response->json('url'))->toStartWith(url('/'));
})->with([
    'absolute url' => 'https://evil.example.com/en/products',
    'protocol relative' => '//evil.example.com/en/products',
    'scheme only' => 'javascript:alert(1)',
    'no leading slash' => 'en/products',
    'backslashes' => '\\\\evil.example.com\\en',
]);

it('reports which locales are available', function (): void {
    $this->postJson('/api/mcp/locale', ['locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('available', ['en', 'zh-TW']);
});

it('makes later tool responses come back in the new language', function (): void {
    $this->seed(CatalogSeeder::class);

    $this->postJson('/api/mcp/locale', ['locale' => 'zh-TW'])->assertOk();

    $this->getJson('/api/mcp/products/LAP-1001')
        ->assertOk()
        ->assertJsonPath('product.name', 'AeroBook Pro 14 吋');
});
