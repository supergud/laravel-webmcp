<?php

use App\Exceptions\ShopException;
use App\Services\CartService;
use Flux\Flux;
use Livewire\Component;

new class extends Component {
    public int $productId;

    public string $productName = '';

    public bool $available = true;

    public int $quantity = 1;

    /**
     * Adding to the cart goes through CartService, which is the same code the
     * add_to_cart WebMCP tool reaches through the JSON API. Limits, stock
     * checks and error messages are therefore identical for a person and for
     * an agent.
     */
    public function add(): void
    {
        try {
            app(CartService::class)->add($this->productId, $this->quantity);
        } catch (ShopException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('shop.cart.added', ['name' => $this->productName]));

        // Tells the header badge and the cart page to re-read the cart. The
        // WebMCP tools dispatch this same event from JavaScript after a write,
        // which is what makes the page move when an agent acts.
        $this->dispatch('cart-updated');
    }
}; ?>

<div>
    <flux:button
        wire:click="add"
        variant="primary"
        size="sm"
        icon="shopping-cart"
        :disabled="! $available"
        data-test="add-to-cart"
        data-product-id="{{ $productId }}"
    >
        {{ __('shop.cart.add') }}
    </flux:button>
</div>
