<?php

declare(strict_types=1);

use App\Models\Product;
use App\Support\ProductQuery;
use Database\Seeders\CatalogSeeder;

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
});

it('serves the catalogue without requiring an account', function (): void {
    $this->getJson('/api/mcp/products')
        ->assertOk()
        ->assertJsonStructure([
            'locale', 'currency', 'total', 'page', 'per_page', 'last_page',
            'products' => [['sku', 'slug', 'name', 'description', 'price', 'currency', 'in_stock', 'category', 'url']],
        ]);
});

it('answers in the language the session is browsing in', function (): void {
    $this->withSession(['locale' => 'zh-TW'])
        ->getJson('/api/mcp/products?term=AeroBook Pro')
        ->assertOk()
        ->assertJsonPath('locale', 'zh-TW')
        ->assertJsonFragment(['name' => 'AeroBook Pro 14 吋']);

    $this->withSession(['locale' => 'en'])
        ->getJson('/api/mcp/products?term=AeroBook Pro')
        ->assertOk()
        ->assertJsonFragment(['name' => 'AeroBook Pro 14']);
});

it('filters the same way the page does', function (): void {
    $this->getJson('/api/mcp/products?category=smartphones')
        ->assertOk()
        ->assertJsonPath('total', 7);

    $this->getJson('/api/mcp/products?min_price=80000&max_price=95000')
        ->assertOk()
        ->assertJsonPath('total', 1);
});

it('rejects a sort value that is not on the list', function (): void {
    $this->getJson('/api/mcp/products?sort=price_asc;drop+table+products')
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort');
});

it('rejects a page size beyond the published maximum', function (): void {
    $this->getJson('/api/mcp/products?per_page='.(ProductQuery::MAX_PER_PAGE + 1))
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page');
});

it('rejects non numeric prices', function (): void {
    $this->getJson('/api/mcp/products?min_price=cheap')
        ->assertStatus(422)
        ->assertJsonValidationErrors('min_price');
});

it('rejects an overlong search term', function (): void {
    $this->getJson('/api/mcp/products?term='.str_repeat('a', ProductQuery::MAX_TERM_LENGTH + 1))
        ->assertStatus(422)
        ->assertJsonValidationErrors('term');
});

it('never exposes an inactive product', function (): void {
    Product::where('sku', 'LAP-1001')->update(['is_active' => false]);

    $this->getJson('/api/mcp/products?term=AeroBook Pro 14')
        ->assertOk()
        ->assertJsonMissing(['sku' => 'LAP-1001']);

    $this->getJson('/api/mcp/products/LAP-1001')->assertNotFound();
});

it('does not reveal the exact stock level', function (): void {
    $response = $this->getJson('/api/mcp/products/LAP-1001')->assertOk();

    expect($response->json('product'))->toHaveKey('in_stock')
        ->and($response->json('product'))->not->toHaveKey('stock')
        ->and($response->json('product'))->not->toHaveKey('id');
});

it('looks a product up by sku or by slug', function (string $identifier): void {
    $this->getJson("/api/mcp/products/{$identifier}")
        ->assertOk()
        ->assertJsonPath('product.sku', 'LAP-1001');
})->with(['LAP-1001', 'aerobook-pro-14']);

it('returns a structured error for a product that does not exist', function (): void {
    $this->getJson('/api/mcp/products/NOPE')
        ->assertNotFound()
        ->assertJsonStructure(['error' => ['code', 'message']])
        ->assertJsonPath('error.code', 'not_found');
});

it('lists categories with localized names and counts', function (): void {
    $this->withSession(['locale' => 'zh-TW'])
        ->getJson('/api/mcp/categories')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'laptops', 'name' => '筆記型電腦'])
        ->assertJsonPath('categories.0.product_count', 7);
});

it('throttles reads so an agent cannot hammer the catalogue', function (): void {
    config()->set('shop.rate_limits.read', 3);

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->getJson('/api/mcp/categories')->assertOk();
    }

    $this->getJson('/api/mcp/categories')->assertStatus(429);
});

it('links to the product page in the browsing language', function (): void {
    $this->withSession(['locale' => 'zh-TW'])
        ->getJson('/api/mcp/products/LAP-1001')
        ->assertOk()
        ->assertJsonPath('product.url', route('products.show', ['locale' => 'zh-TW', 'slug' => 'aerobook-pro-14']));
});
