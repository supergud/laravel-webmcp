<?php

declare(strict_types=1);

use App\Models\Product;
use App\Services\CartService;
use Database\Seeders\CatalogSeeder;

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->cart = app(CartService::class);
});

it('reads an empty cart', function (): void {
    $this->getJson('/api/mcp/cart')
        ->assertOk()
        ->assertJsonPath('cart.total', 0)
        ->assertJsonPath('cart.items', []);
});

it('publishes the limits it enforces so an agent can plan', function (): void {
    $this->getJson('/api/mcp/cart')
        ->assertOk()
        ->assertJsonPath('limits.max_quantity_per_item', (int) config('shop.cart.max_quantity_per_item'))
        ->assertJsonPath('limits.max_items', (int) config('shop.cart.max_items'))
        ->assertJsonPath('limits.max_total', (int) config('shop.cart.max_total'));
});

it('adds a product by sku', function (): void {
    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006', 'quantity' => 2])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('cart.unit_count', 2)
        ->assertJsonPath('cart.items.0.sku', 'ACC-5006');
});

it('defaults to a quantity of one', function (): void {
    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006'])
        ->assertOk()
        ->assertJsonPath('cart.unit_count', 1);
});

it('accumulates repeated adds', function (): void {
    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006', 'quantity' => 2])->assertOk();

    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006', 'quantity' => 3])
        ->assertOk()
        ->assertJsonPath('cart.unit_count', 5);
});

it('sets an exact quantity and removes on zero', function (): void {
    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006', 'quantity' => 4])->assertOk();

    $this->patchJson('/api/mcp/cart/items/ACC-5006', ['quantity' => 1])
        ->assertOk()
        ->assertJsonPath('cart.unit_count', 1);

    $this->patchJson('/api/mcp/cart/items/ACC-5006', ['quantity' => 0])
        ->assertOk()
        ->assertJsonPath('cart.line_count', 0);
});

it('removes a line and clears the cart', function (): void {
    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006'])->assertOk();
    $this->deleteJson('/api/mcp/cart/items/ACC-5006')->assertOk()->assertJsonPath('cart.line_count', 0);

    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006'])->assertOk();
    $this->deleteJson('/api/mcp/cart')->assertOk()->assertJsonPath('cart.line_count', 0);
});

it('rejects a quantity beyond the published cap', function (): void {
    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006', 'quantity' => 99])
        ->assertStatus(422)
        ->assertJsonValidationErrors('quantity');

    expect($this->cart->summary()->isEmpty())->toBeTrue();
});

it('rejects an unknown sku without saying anything about the catalogue', function (): void {
    $response = $this->postJson('/api/mcp/cart/items', ['sku' => 'DOES-NOT-EXIST'])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error.code', 'rejected');

    expect($response->json('error.message'))->toBe(__('shop.errors.product_unavailable'));
});

it('refuses a product that is not for sale', function (): void {
    Product::where('sku', 'LAP-1001')->update(['is_active' => false]);

    $this->postJson('/api/mcp/cart/items', ['sku' => 'LAP-1001'])->assertStatus(422);
});

it('refuses to exceed the cart value ceiling', function (): void {
    // AeroBook Studio 16 is 89,900 - two exceed the 100,000 cap.
    $this->postJson('/api/mcp/cart/items', ['sku' => 'LAP-1006'])->assertOk();

    $this->patchJson('/api/mcp/cart/items/LAP-1006', ['quantity' => 2])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'rejected');

    expect($this->cart->summary()->unitCount())->toBe(1);
});

it('returns the unchanged cart alongside a rejection', function (): void {
    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006', 'quantity' => 2])->assertOk();

    $this->postJson('/api/mcp/cart/items', ['sku' => 'NOPE'])
        ->assertStatus(422)
        ->assertJsonPath('cart.unit_count', 2);
});

it('localizes cart contents', function (): void {
    $this->withSession(['locale' => 'zh-TW'])
        ->postJson('/api/mcp/cart/items', ['sku' => 'LAP-1001'])
        ->assertOk()
        ->assertJsonPath('cart.items.0.name', 'AeroBook Pro 14 吋');
});

it('keeps one session cart out of another', function (): void {
    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006', 'quantity' => 2])->assertOk();

    // A fresh session is a fresh cart; there is no identifier to swap.
    $this->flushSession();

    $this->getJson('/api/mcp/cart')->assertOk()->assertJsonPath('cart.line_count', 0);
});

it('requires a csrf token for writes', function (): void {
    // The JSON test helpers bypass CSRF, so this asserts the middleware is
    // actually on the route rather than trying to defeat the test harness.
    $middleware = collect(app('router')->getRoutes()->getByName('mcp.cart.store')->gatherMiddleware());

    expect($middleware->contains('web'))->toBeTrue();
});

it('throttles writes harder than reads', function (): void {
    config()->set('shop.rate_limits.write', 2);

    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006'])->assertOk();
    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006'])->assertOk();

    $this->postJson('/api/mcp/cart/items', ['sku' => 'ACC-5006'])->assertStatus(429);

    // Reads have their own, larger budget.
    $this->getJson('/api/mcp/cart')->assertOk();
});
