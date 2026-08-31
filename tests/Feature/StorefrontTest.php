<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogService;
use Database\Seeders\CatalogSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
});

it('renders the catalogue on the localized home page', function (string $locale, string $heading): void {
    $this->get("/{$locale}")
        ->assertOk()
        ->assertSee($heading)
        ->assertSee('NT$');
})->with([
    'english' => ['en', 'Products'],
    'chinese' => ['zh-TW', '商品'],
]);

it('shows product names in the active locale', function (string $locale, string $expected): void {
    app()->setLocale($locale);

    Livewire::test('pages::shop.products')
        ->set('term', 'AeroBook Pro')
        ->assertSee($expected);
})->with([
    'english' => ['en', 'AeroBook Pro 14'],
    'chinese' => ['zh-TW', 'AeroBook Pro 14 吋'],
]);

it('shows category names in the active locale', function (): void {
    $this->get('/zh-TW')->assertOk()->assertSee('筆記型電腦');
    $this->get('/en')->assertOk()->assertSee('Laptops');
});

it('filters products by search term', function (): void {
    Livewire::test('pages::shop.products')
        ->set('term', 'AeroBook')
        ->assertSee('AeroBook Pro 14')
        ->assertDontSee('Nova 12 Pro');
});

it('filters products by category', function (): void {
    Livewire::test('pages::shop.products')
        ->set('categorySlug', 'smartphones')
        ->assertSee('Nova 12 Pro')
        ->assertDontSee('AeroBook Pro 14');
});

it('filters products by price range', function (): void {
    $component = Livewire::test('pages::shop.products')
        ->set('minPrice', '80000')
        ->set('maxPrice', '95000');

    // Only AeroBook Studio 16 (89900) falls in this band.
    expect($component->instance()->products->total())->toBe(1);
});

it('swaps a reversed price range instead of returning nothing', function (): void {
    $component = Livewire::test('pages::shop.products')
        ->set('minPrice', '95000')
        ->set('maxPrice', '80000');

    expect($component->instance()->products->total())->toBe(1);
});

it('sorts by price in both directions', function (): void {
    $ascending = Livewire::test('pages::shop.products')->set('sort', 'price_asc');
    $descending = Livewire::test('pages::shop.products')->set('sort', 'price_desc');

    $cheapest = $ascending->instance()->products->first();
    $priciest = $descending->instance()->products->first();

    expect($cheapest->price)->toBeLessThan($priciest->price);
});

it('falls back to the default sort when given an unknown one', function (): void {
    $component = Livewire::test('pages::shop.products')->set('sort', 'price_asc; drop table products');

    expect($component->instance()->products->total())->toBeGreaterThan(0);
});

it('returns to page one when a filter changes', function (): void {
    Livewire::test('pages::shop.products')
        ->set('paginators.page', 3)
        ->set('term', 'Nova')
        ->assertSet('paginators.page', 1);
});

it('shows a product detail page in both locales', function (): void {
    $this->get('/en/products/aerobook-pro-14')->assertOk()->assertSee('AeroBook Pro 14');
    $this->get('/zh-TW/products/aerobook-pro-14')->assertOk()->assertSee('AeroBook Pro 14 吋');
});

it('does not expose an inactive product', function (): void {
    $product = Product::where('sku', 'LAP-1001')->firstOrFail();
    $product->update(['is_active' => false]);

    $this->get('/en/products/aerobook-pro-14')->assertNotFound();
});

it('hides inactive products from the listing', function (): void {
    Product::where('sku', 'LAP-1001')->update(['is_active' => false]);

    Livewire::test('pages::shop.products')
        ->set('term', 'AeroBook Pro 14')
        ->assertDontSee('AeroBook Pro 14');
});

it('counts only available products per category', function (): void {
    Product::where('sku', 'LAP-1001')->update(['is_active' => false]);

    $laptops = Category::where('slug', 'laptops')->firstOrFail();

    expect(app(CatalogService::class)->categories()
        ->firstWhere('slug', 'laptops')
        ->products_count)
        ->toBe($laptops->products()->count() - 1);
});
