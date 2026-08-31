<?php

declare(strict_types=1);

use App\Exceptions\ShopException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\OrderStatus;
use Database\Seeders\CatalogSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);

    $this->user = User::factory()->create();
    $this->cart = app(CartService::class);
    $this->checkout = app(CheckoutService::class);
    $this->product = Product::where('sku', 'ACC-5006')->firstOrFail(); // 390 TWD

    $this->shipping = [
        'shipping_name' => 'Demo Shopper',
        'shipping_email' => 'demo@example.com',
        'shipping_address' => 'No. 1, Somewhere Road, Taipei',
    ];
});

/*
|--------------------------------------------------------------------------
| Preparing a draft
|--------------------------------------------------------------------------
*/

it('writes a draft that has not been placed', function (): void {
    $this->cart->add($this->product->id, 2);

    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);

    expect($draft->status)->toBe(OrderStatus::Draft)
        ->and($draft->confirmed_at)->toBeNull()
        ->and($draft->total)->toBe($this->product->price * 2)
        ->and($draft->items)->toHaveCount(1);
});

it('does not touch stock or the cart when preparing a draft', function (): void {
    $stockBefore = $this->product->stock;
    $this->cart->add($this->product->id, 2);

    $this->checkout->prepareDraft($this->user, $this->shipping);

    expect($this->product->fresh()->stock)->toBe($stockBefore)
        ->and($this->cart->summary()->isEmpty())->toBeFalse();
});

it('refuses to prepare a draft from an empty cart', function (): void {
    expect(fn () => $this->checkout->prepareDraft($this->user, $this->shipping))
        ->toThrow(ShopException::class);
});

it('replaces the previous draft rather than leaving two confirmable', function (): void {
    $this->cart->add($this->product->id, 1);

    $first = $this->checkout->prepareDraft($this->user, $this->shipping);
    $second = $this->checkout->prepareDraft($this->user, $this->shipping);

    expect($first->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($first->fresh()->confirmation_token)->toBeNull()
        ->and($this->checkout->currentDraft($this->user)->id)->toBe($second->id);
});

it('snapshots product names in every locale', function (): void {
    $this->cart->add($this->product->id, 1);

    $item = $this->checkout->prepareDraft($this->user, $this->shipping)->items->first();

    expect($item->getTranslations('name'))->toHaveKeys(['en', 'zh-TW']);
});

/*
|--------------------------------------------------------------------------
| Confirming
|--------------------------------------------------------------------------
*/

it('places the order when confirmed with the right token', function (): void {
    $this->cart->add($this->product->id, 2);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);

    $order = $this->checkout->confirm($this->user, (string) $draft->confirmation_token);

    expect($order->status)->toBe(OrderStatus::Paid)
        ->and($order->confirmed_at)->not->toBeNull();
});

it('decrements stock and empties the cart on confirmation', function (): void {
    $stockBefore = $this->product->stock;
    $this->cart->add($this->product->id, 3);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);

    $this->checkout->confirm($this->user, (string) $draft->confirmation_token);

    expect($this->product->fresh()->stock)->toBe($stockBefore - 3)
        ->and($this->cart->summary()->isEmpty())->toBeTrue();
});

it('burns the token so a confirmation cannot be replayed', function (): void {
    $this->cart->add($this->product->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);
    $token = (string) $draft->confirmation_token;

    $this->checkout->confirm($this->user, $token);

    expect(fn () => $this->checkout->confirm($this->user, $token))
        ->toThrow(ShopException::class);
});

it('rejects a wrong confirmation token', function (): void {
    $this->cart->add($this->product->id, 1);
    $this->checkout->prepareDraft($this->user, $this->shipping);

    expect(fn () => $this->checkout->confirm($this->user, str_repeat('a', 64)))
        ->toThrow(ShopException::class);

    expect(Order::query()->placed()->count())->toBe(0);
});

it('refuses to confirm another account draft even with its real token', function (): void {
    $this->cart->add($this->product->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);

    $attacker = User::factory()->create();

    expect(fn () => $this->checkout->confirm($attacker, (string) $draft->confirmation_token))
        ->toThrow(ShopException::class);

    expect($draft->fresh()->status)->toBe(OrderStatus::Draft);
});

it('refuses to confirm an expired draft', function (): void {
    $this->cart->add($this->product->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);

    $this->travel((int) config('shop.checkout.draft_lifetime_minutes') + 1)->minutes();

    expect(fn () => $this->checkout->confirm($this->user, (string) $draft->confirmation_token))
        ->toThrow(ShopException::class);

    expect($draft->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('treats an expired draft as if there were none', function (): void {
    $this->cart->add($this->product->id, 1);
    $this->checkout->prepareDraft($this->user, $this->shipping);

    $this->travel((int) config('shop.checkout.draft_lifetime_minutes') + 1)->minutes();

    expect($this->checkout->currentDraft($this->user))->toBeNull();
});

it('refuses to confirm when a price changed after the draft was written', function (): void {
    $this->cart->add($this->product->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);

    $this->product->update(['price' => $this->product->price + 100]);

    expect(fn () => $this->checkout->confirm($this->user, (string) $draft->confirmation_token))
        ->toThrow(ShopException::class);
});

it('refuses to confirm when stock ran out after the draft was written', function (): void {
    $this->cart->add($this->product->id, 5);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);

    $this->product->update(['stock' => 1]);

    expect(fn () => $this->checkout->confirm($this->user, (string) $draft->confirmation_token))
        ->toThrow(ShopException::class)
        ->and($this->product->fresh()->stock)->toBe(1);
});

it('refuses to confirm when a product was withdrawn after the draft was written', function (): void {
    $this->cart->add($this->product->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);

    $this->product->update(['is_active' => false]);

    expect(fn () => $this->checkout->confirm($this->user, (string) $draft->confirmation_token))
        ->toThrow(ShopException::class);
});

/*
|--------------------------------------------------------------------------
| Order history isolation
|--------------------------------------------------------------------------
*/

it('lists only the signed-in user orders', function (): void {
    $this->cart->add($this->product->id, 1);
    $mine = $this->checkout->prepareDraft($this->user, $this->shipping);
    $this->checkout->confirm($this->user, (string) $mine->confirmation_token);

    $other = User::factory()->create();
    $this->cart->add($this->product->id, 1);
    $theirs = $this->checkout->prepareDraft($other, $this->shipping);
    $this->checkout->confirm($other, (string) $theirs->confirmation_token);

    expect($this->checkout->ordersFor($this->user)->pluck('number')->all())->toBe([$mine->number]);
});

it('cannot fetch another account order by its number', function (): void {
    $this->cart->add($this->product->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);
    $this->checkout->confirm($this->user, (string) $draft->confirmation_token);

    $attacker = User::factory()->create();

    expect($this->checkout->orderFor($attacker, $draft->number))->toBeNull();
});

it('does not list unconfirmed drafts as orders', function (): void {
    $this->cart->add($this->product->id, 1);
    $this->checkout->prepareDraft($this->user, $this->shipping);

    expect($this->checkout->ordersFor($this->user))->toBeEmpty();
});

it('never serialises the confirmation token', function (): void {
    $this->cart->add($this->product->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);

    expect($draft->toArray())->not->toHaveKey('confirmation_token')
        ->and(json_encode($draft->toToolArray()))->not->toContain((string) $draft->confirmation_token);
});

/*
|--------------------------------------------------------------------------
| Pages
|--------------------------------------------------------------------------
*/

it('requires an account to reach checkout and orders', function (string $path): void {
    $this->get($path)->assertRedirect('/login');
})->with([
    '/en/checkout',
    '/en/orders',
    '/en/orders/ORD-20260901-ABCDEF',
]);

it('prepares and confirms through the checkout page', function (): void {
    $this->actingAs($this->user);
    $this->cart->add($this->product->id, 2);

    $component = Livewire::test('pages::shop.checkout')
        ->set('shippingName', 'Demo Shopper')
        ->set('shippingEmail', 'demo@example.com')
        ->set('shippingAddress', 'No. 1, Somewhere Road, Taipei')
        ->call('prepare')
        ->assertDispatched('checkout-updated');

    expect(Order::query()->where('status', OrderStatus::Draft)->count())->toBe(1);

    $component->call('confirm');

    expect(Order::query()->placed()->count())->toBe(1)
        ->and($this->cart->summary()->isEmpty())->toBeTrue();
});

it('validates the delivery details before writing a draft', function (): void {
    $this->actingAs($this->user);
    $this->cart->add($this->product->id, 1);

    Livewire::test('pages::shop.checkout')
        ->set('shippingName', '')
        ->set('shippingEmail', 'not-an-email')
        ->set('shippingAddress', '')
        ->call('prepare')
        ->assertHasErrors(['shippingName', 'shippingEmail', 'shippingAddress']);

    expect(Order::query()->count())->toBe(0);
});

it('cancels a draft from the checkout page', function (): void {
    $this->actingAs($this->user);
    $this->cart->add($this->product->id, 1);
    $this->checkout->prepareDraft($this->user, $this->shipping);

    Livewire::test('pages::shop.checkout')->call('cancel');

    expect($this->checkout->currentDraft($this->user))->toBeNull()
        ->and(Order::query()->placed()->count())->toBe(0);
});

it('shows an order to its owner and hides it from everyone else', function (): void {
    $this->cart->add($this->product->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);
    $order = $this->checkout->confirm($this->user, (string) $draft->confirmation_token);

    $this->actingAs($this->user)
        ->get("/en/orders/{$order->number}")
        ->assertOk()
        ->assertSee($order->number);

    $this->actingAs(User::factory()->create())
        ->get("/en/orders/{$order->number}")
        ->assertNotFound();
});

it('shows order history in the active locale', function (): void {
    $this->cart->add($this->product->id, 1);
    $draft = $this->checkout->prepareDraft($this->user, $this->shipping);
    $order = $this->checkout->confirm($this->user, (string) $draft->confirmation_token);

    $this->actingAs($this->user)
        ->get("/zh-TW/orders/{$order->number}")
        ->assertOk()
        ->assertSee('已付款');
});
