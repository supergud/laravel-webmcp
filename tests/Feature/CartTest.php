<?php

declare(strict_types=1);

use App\Exceptions\ShopException;
use App\Models\Product;
use App\Services\CartService;
use Database\Seeders\CatalogSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(CatalogSeeder::class);
    $this->cart = app(CartService::class);
    $this->product = Product::where('sku', 'ACC-5006')->firstOrFail(); // 390 TWD
});

it('starts empty', function (): void {
    expect($this->cart->summary()->isEmpty())->toBeTrue()
        ->and($this->cart->summary()->total)->toBe(0);
});

it('adds a product and totals the line', function (): void {
    $summary = $this->cart->add($this->product->id, 2);

    expect($summary->unitCount())->toBe(2)
        ->and($summary->lineCount())->toBe(1)
        ->and($summary->total)->toBe($this->product->price * 2);
});

it('accumulates repeated adds of the same product', function (): void {
    $this->cart->add($this->product->id, 2);
    $summary = $this->cart->add($this->product->id, 3);

    expect($summary->unitCount())->toBe(5)
        ->and($summary->lineCount())->toBe(1);
});

it('sets an exact quantity on update', function (): void {
    $this->cart->add($this->product->id, 5);

    expect($this->cart->update($this->product->id, 2)->unitCount())->toBe(2);
});

it('removes the line when a quantity of zero is set', function (): void {
    $this->cart->add($this->product->id, 3);

    expect($this->cart->update($this->product->id, 0)->isEmpty())->toBeTrue();
});

it('removes and clears', function (): void {
    $this->cart->add($this->product->id, 1);
    expect($this->cart->remove($this->product->id)->isEmpty())->toBeTrue();

    $this->cart->add($this->product->id, 1);
    expect($this->cart->clear()->isEmpty())->toBeTrue();
});

it('refuses more than the per-product quantity cap', function (): void {
    $max = (int) config('shop.cart.max_quantity_per_item');

    $this->cart->add($this->product->id, $max);

    expect(fn () => $this->cart->add($this->product->id, 1))
        ->toThrow(ShopException::class);

    // The rejected change must not have been applied.
    expect($this->cart->summary()->unitCount())->toBe($max);
});

it('refuses more distinct products than the line cap', function (): void {
    $max = (int) config('shop.cart.max_items');

    $products = Product::query()->available()->orderBy('price')->limit($max + 1)->get();

    foreach ($products->take($max) as $product) {
        $this->cart->add($product->id, 1);
    }

    expect(fn () => $this->cart->add($products->last()->id, 1))
        ->toThrow(ShopException::class)
        ->and($this->cart->summary()->lineCount())->toBe($max);
});

it('refuses a cart worth more than the value ceiling', function (): void {
    // AeroBook Studio 16 is 89,900, so two of them exceed the 100,000 cap.
    $expensive = Product::where('sku', 'LAP-1006')->firstOrFail();

    $this->cart->add($expensive->id, 1);

    expect(fn () => $this->cart->update($expensive->id, 2))
        ->toThrow(ShopException::class)
        ->and($this->cart->summary()->total)->toBe($expensive->price);
});

it('refuses a product that is not for sale', function (): void {
    $product = Product::where('sku', 'LAP-1001')->firstOrFail();
    $product->update(['is_active' => false]);

    expect(fn () => $this->cart->add($product->id, 1))->toThrow(ShopException::class);
});

it('refuses a product that does not exist', function (): void {
    expect(fn () => $this->cart->add(999_999, 1))->toThrow(ShopException::class);
});

it('refuses a product that is out of stock', function (): void {
    $product = Product::factory()->outOfStock()->create();

    expect(fn () => $this->cart->add($product->id, 1))->toThrow(ShopException::class);
});

it('refuses to order more than the remaining stock', function (): void {
    $product = Product::factory()->create(['stock' => 2]);

    expect(fn () => $this->cart->add($product->id, 3))->toThrow(ShopException::class);
});

it('drops a product that is withdrawn after it was added', function (): void {
    $this->cart->add($this->product->id, 1);

    $this->product->update(['is_active' => false]);

    expect($this->cart->summary()->isEmpty())->toBeTrue();
});

it('ignores a malformed cart payload in the session', function (): void {
    session()->put(config('shop.cart.session_key'), [
        'not-an-id' => 'not-a-quantity',
        '-1' => 5,
        $this->product->id => 0,
    ]);

    expect($this->cart->summary()->isEmpty())->toBeTrue();
});

it('is empty again once the session is flushed', function (): void {
    $this->cart->add($this->product->id, 1);
    expect($this->cart->summary()->isEmpty())->toBeFalse();

    session()->flush();

    expect($this->cart->summary()->isEmpty())->toBeTrue();
});

it('exposes only customer-facing fields to tools', function (): void {
    $payload = $this->cart->add($this->product->id, 2)->toArray();

    expect($payload)->toHaveKeys(['currency', 'items', 'line_count', 'unit_count', 'total'])
        ->and($payload['items'][0])->toHaveKeys(['sku', 'slug', 'name', 'unit_price', 'quantity', 'line_total'])
        ->and($payload['items'][0])->not->toHaveKey('id')
        ->and($payload['items'][0])->not->toHaveKey('stock');
});

it('adds to the cart from the product page component', function (): void {
    Livewire::test('add-to-cart', [
        'productId' => $this->product->id,
        'productName' => (string) $this->product->name,
        'available' => true,
    ])
        ->call('add')
        ->assertDispatched('cart-updated');

    expect($this->cart->summary()->unitCount())->toBe(1);
});

it('does not dispatch an update when adding fails', function (): void {
    $product = Product::factory()->outOfStock()->create();

    Livewire::test('add-to-cart', [
        'productId' => $product->id,
        'productName' => 'Unavailable',
        'available' => false,
    ])
        ->call('add')
        ->assertNotDispatched('cart-updated');
});

it('shows the cart contents on the cart page', function (): void {
    $this->cart->add($this->product->id, 2);

    $this->get('/en/cart')
        ->assertOk()
        ->assertSee($this->product->sku);
});

it('updates and clears from the cart page', function (): void {
    $this->cart->add($this->product->id, 4);

    Livewire::test('pages::shop.cart')
        ->call('updateQuantity', $this->product->id, 1)
        ->assertDispatched('cart-updated');

    expect($this->cart->summary()->unitCount())->toBe(1);

    Livewire::test('pages::shop.cart')->call('clear');

    expect($this->cart->summary()->isEmpty())->toBeTrue();
});

it('counts units in the header badge', function (): void {
    $this->cart->add($this->product->id, 3);

    Livewire::test('cart-badge')->assertSee('3');
});
