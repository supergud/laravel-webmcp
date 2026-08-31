<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\OrderStatus;
use Database\Seeders\CatalogSeeder;

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);

    $this->user = User::factory()->create(['name' => 'Demo Shopper', 'email' => 'demo@example.com']);
    $this->cart = app(CartService::class);
    $this->checkout = app(CheckoutService::class);

    $this->shipping = ['shipping_address' => 'No. 1, Somewhere Road, Taipei'];
});

function placeOrder(User $user): Order
{
    $checkout = app(CheckoutService::class);

    app(CartService::class)->add(
        Product::where('sku', 'ACC-5006')->firstOrFail()->id,
        1,
    );

    $draft = $checkout->prepareDraft($user, [
        'shipping_name' => 'Demo Shopper',
        'shipping_email' => 'demo@example.com',
        'shipping_address' => 'No. 1, Somewhere Road, Taipei',
    ]);

    return $checkout->confirm($user, (string) $draft->confirmation_token);
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

it('answers unauthenticated order calls with json, not a redirect', function (string $method, string $path): void {
    $this->json($method, $path)->assertStatus(401);
})->with([
    ['GET', '/api/mcp/orders'],
    ['GET', '/api/mcp/orders/ORD-20260901-ABCDEF'],
    ['GET', '/api/mcp/checkout/status'],
    ['POST', '/api/mcp/checkout/prepare'],
]);

/*
|--------------------------------------------------------------------------
| Reading orders
|--------------------------------------------------------------------------
*/

it('lists only the signed-in account orders', function (): void {
    $mine = placeOrder($this->user);
    $theirs = placeOrder(User::factory()->create());

    $response = $this->actingAs($this->user)->getJson('/api/mcp/orders')->assertOk();

    expect(collect($response->json('orders'))->pluck('number')->all())->toBe([$mine->number])
        ->and($response->json())->not->toContain($theirs->number);
});

it('reports another account order as not found', function (): void {
    $order = placeOrder($this->user);

    $this->actingAs(User::factory()->create())
        ->getJson("/api/mcp/orders/{$order->number}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

it('never leaks the confirmation token through an order response', function (): void {
    $this->cart->add(Product::where('sku', 'ACC-5006')->firstOrFail()->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, [
        'shipping_name' => 'Demo Shopper',
        'shipping_email' => 'demo@example.com',
        'shipping_address' => 'Somewhere',
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/mcp/checkout/status')->assertOk();

    expect($response->getContent())->not->toContain((string) $draft->confirmation_token);
});

it('does not list an unconfirmed draft as an order', function (): void {
    $this->cart->add(Product::where('sku', 'ACC-5006')->firstOrFail()->id, 1);
    $this->checkout->prepareDraft($this->user, [
        'shipping_name' => 'A', 'shipping_email' => 'a@example.com', 'shipping_address' => 'B',
    ]);

    $this->actingAs($this->user)->getJson('/api/mcp/orders')
        ->assertOk()
        ->assertJsonPath('orders', []);
});

/*
|--------------------------------------------------------------------------
| Preparing
|--------------------------------------------------------------------------
*/

it('prepares a draft without placing it', function (): void {
    $this->cart->add(Product::where('sku', 'ACC-5006')->firstOrFail()->id, 2);

    $response = $this->actingAs($this->user)
        ->postJson('/api/mcp/checkout/prepare', $this->shipping)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('draft.status', 'draft');

    expect(Order::query()->placed()->count())->toBe(0)
        ->and($response->json('confirmation_url'))->toBe(route('checkout'))
        ->and($response->json('next_step'))->toContain('NOT placed');
});

it('does not touch stock or empty the cart when preparing', function (): void {
    $product = Product::where('sku', 'ACC-5006')->firstOrFail();
    $stock = $product->stock;

    $this->cart->add($product->id, 2);
    $this->actingAs($this->user)->postJson('/api/mcp/checkout/prepare', $this->shipping)->assertOk();

    expect($product->fresh()->stock)->toBe($stock)
        ->and($this->cart->summary()->isEmpty())->toBeFalse();
});

it('defaults the recipient to the account holder', function (): void {
    $this->cart->add(Product::where('sku', 'ACC-5006')->firstOrFail()->id, 1);

    $this->actingAs($this->user)->postJson('/api/mcp/checkout/prepare', $this->shipping)->assertOk();

    expect(Order::query()->latest('id')->firstOrFail()->shipping_name)->toBe('Demo Shopper');
});

it('requires a shipping address', function (): void {
    $this->cart->add(Product::where('sku', 'ACC-5006')->firstOrFail()->id, 1);

    $this->actingAs($this->user)
        ->postJson('/api/mcp/checkout/prepare', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('shipping_address');
});

it('refuses to prepare from an empty cart', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/mcp/checkout/prepare', $this->shipping)
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'rejected');
});

/*
|--------------------------------------------------------------------------
| The confirmation boundary
|--------------------------------------------------------------------------
*/

it('exposes no endpoint that can confirm an order', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->uri(), 'api/mcp'))
        ->map(fn ($route): string => $route->getName().' '.$route->uri());

    expect($routes)->not->toContain(fn (string $route): bool => str_contains($route, 'confirm'));

    // With a draft outstanding, the obvious guesses must not place it. A 404
    // or a 405 are both fine; what matters is that nothing succeeds and no
    // order comes into existence.
    $this->cart->add(Product::where('sku', 'ACC-5006')->firstOrFail()->id, 1);
    $this->checkout->prepareDraft($this->user, [
        'shipping_name' => 'A', 'shipping_email' => 'a@example.com', 'shipping_address' => 'B',
    ]);

    foreach (['/api/mcp/checkout/confirm', '/api/mcp/orders/confirm', '/api/mcp/checkout'] as $path) {
        $status = $this->actingAs($this->user)->postJson($path)->getStatusCode();

        expect($status)->toBeGreaterThanOrEqual(400);
    }

    expect(Order::query()->placed()->count())->toBe(0);
});

it('reports the draft as awaiting confirmation and says no tool can confirm it', function (): void {
    $this->cart->add(Product::where('sku', 'ACC-5006')->firstOrFail()->id, 1);
    $this->actingAs($this->user)->postJson('/api/mcp/checkout/prepare', $this->shipping)->assertOk();

    $response = $this->actingAs($this->user)->getJson('/api/mcp/checkout/status')->assertOk();

    expect($response->json('awaiting_confirmation'))->toBeTrue()
        ->and($response->json('note'))->toContain('No tool can confirm');
});

it('reports the order once the customer has confirmed it', function (): void {
    $order = placeOrder($this->user);

    $this->actingAs($this->user)->getJson('/api/mcp/checkout/status')
        ->assertOk()
        ->assertJsonPath('awaiting_confirmation', false)
        ->assertJsonPath('latest_order.number', $order->number)
        ->assertJsonPath('latest_order.status', OrderStatus::Paid->value);
});

it('stops reporting a draft once it has expired', function (): void {
    $this->cart->add(Product::where('sku', 'ACC-5006')->firstOrFail()->id, 1);
    $this->actingAs($this->user)->postJson('/api/mcp/checkout/prepare', $this->shipping)->assertOk();

    $this->travel((int) config('shop.checkout.draft_lifetime_minutes') + 1)->minutes();

    $this->actingAs($this->user)->getJson('/api/mcp/checkout/status')
        ->assertOk()
        ->assertJsonPath('awaiting_confirmation', false);
});

it('localizes order responses', function (): void {
    placeOrder($this->user);

    $this->actingAs($this->user)
        ->withSession(['locale' => 'zh-TW'])
        ->getJson('/api/mcp/orders')
        ->assertOk()
        ->assertJsonPath('orders.0.status_label', '已付款');
});
