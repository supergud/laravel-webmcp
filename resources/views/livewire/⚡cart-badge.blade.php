<?php

use App\Services\CartService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    /**
     * Re-render whenever the cart changes, whether the change came from a
     * person clicking or from a WebMCP tool dispatching the same event.
     */
    #[On('cart-updated')]
    public function refresh(): void
    {
        // The re-render is the point; the computed property below is rebuilt
        // because each Livewire request gets a fresh component instance.
    }

    #[Computed]
    public function count(): int
    {
        return app(CartService::class)->summary()->unitCount();
    }
}; ?>

<flux:navbar.item
    :href="route('cart')"
    icon="shopping-cart"
    :current="request()->routeIs('cart')"
    :badge="$this->count > 0 ? (string) $this->count : null"
    badge-color="green"
    wire:navigate
    data-test="cart-badge"
    data-cart-count="{{ $this->count }}"
>
    {{ __('shop.nav.cart') }}
</flux:navbar.item>
