<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\App;

it('seeds a bilingual catalogue', function (): void {
    $this->seed(CatalogSeeder::class);

    expect(Category::count())->toBe(6)
        ->and(Product::count())->toBe(43);
});

it('is idempotent so the demo can be reseeded', function (): void {
    $this->seed(CatalogSeeder::class);
    $this->seed(CatalogSeeder::class);

    expect(Product::count())->toBe(43);
});

it('resolves product names in the active locale', function (): void {
    $this->seed(CatalogSeeder::class);

    $product = Product::where('sku', 'LAP-1001')->firstOrFail();

    App::setLocale('en');
    expect($product->name)->toBe('AeroBook Pro 14');

    App::setLocale('zh-TW');
    expect($product->name)->toBe('AeroBook Pro 14 吋');
});

it('stores both translations in a single json column', function (): void {
    $this->seed(CatalogSeeder::class);

    $raw = Product::where('sku', 'LAP-1001')->firstOrFail()->getAttributes()['name'];

    expect(json_decode((string) $raw, true))
        ->toHaveKeys(['en', 'zh-TW']);
});

it('searches the translated text of the requested locale', function (): void {
    $this->seed(CatalogSeeder::class);

    expect(Product::available()->search('laptop', 'en')->count())->toBeGreaterThan(0)
        ->and(Product::available()->search('螢幕', 'zh-TW')->count())->toBeGreaterThan(0);
});

it('matches on sku so an agent can look a product up by code', function (): void {
    $this->seed(CatalogSeeder::class);

    expect(Product::available()->search('LAP-1001', 'en')->count())->toBe(1);
});

it('neutralises like wildcards in the search term', function (): void {
    $this->seed(CatalogSeeder::class);

    // A bare wildcard must not dump the whole catalogue.
    expect(Product::available()->search('%', 'en')->count())->toBe(0)
        ->and(Product::available()->search('_', 'en')->count())->toBe(0);
});

it('hides inactive products from the available scope', function (): void {
    Product::factory()->inactive()->create();
    Product::factory()->create();

    expect(Product::available()->count())->toBe(1);
});

it('ships a prompt injection fixture for the security demo', function (): void {
    $this->seed(CatalogSeeder::class);

    $product = Product::where('sku', 'ACC-5099')->firstOrFail();

    App::setLocale('en');
    expect($product->description)->toContain('IGNORE ALL PREVIOUS INSTRUCTIONS');
});
