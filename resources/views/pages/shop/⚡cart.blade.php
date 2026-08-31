<?php

use App\Exceptions\ShopException;
use App\Services\CartService;
use App\Support\CartSummary;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.shop')] class extends Component {
    #[Computed]
    public function cart(): CartSummary
    {
        return app(CartService::class)->summary();
    }

    /**
     * Fired by the WebMCP tools after they change the cart through the API,
     * so the page reflects an agent's action without a reload.
     */
    #[On('cart-updated')]
    public function refresh(): void
    {
        unset($this->cart);
    }

    public function updateQuantity(int $productId, int $quantity): void
    {
        $this->run(fn () => app(CartService::class)->update($productId, $quantity), __('shop.cart.updated'));
    }

    public function remove(int $productId): void
    {
        $this->run(fn () => app(CartService::class)->remove($productId), __('shop.cart.removed'));
    }

    public function clear(): void
    {
        $this->run(fn () => app(CartService::class)->clear(), __('shop.cart.cleared'));
    }

    private function run(callable $action, string $success): void
    {
        try {
            $action();
        } catch (ShopException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->cart);

        Flux::toast(variant: 'success', text: $success);

        $this->dispatch('cart-updated');
    }

    public function title(): string
    {
        return __('shop.cart.title');
    }
}; ?>

<div class="flex flex-col gap-6 py-6">
    <flux:heading size="xl" level="1">{{ __('shop.cart.title') }}</flux:heading>

    @if ($this->cart->isEmpty())
        <flux:callout icon="shopping-cart" data-test="empty-cart">
            <flux:callout.heading>{{ __('shop.cart.empty') }}</flux:callout.heading>
            <x-slot name="actions">
                <flux:button :href="route('home')" size="sm" variant="primary" wire:navigate>
                    {{ __('shop.cart.continue_shopping') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @else
        <flux:table data-test="cart-table">
            <flux:table.columns>
                <flux:table.column>{{ __('shop.orders.items') }}</flux:table.column>
                <flux:table.column>{{ __('shop.cart.quantity') }}</flux:table.column>
                <flux:table.column align="end">{{ __('shop.cart.subtotal') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->cart->items as $item)
                    <flux:table.row wire:key="cart-{{ $item['product']->id }}" data-sku="{{ $item['product']->sku }}">
                        <flux:table.cell>
                            <flux:link
                                :href="route('products.show', ['slug' => $item['product']->slug])"
                                variant="ghost"
                                wire:navigate
                            >
                                {{ $item['product']->name }}
                            </flux:link>
                            <flux:text size="sm">{{ __('shop.products.sku') }}: {{ $item['product']->sku }}</flux:text>
                        </flux:table.cell>

                        <flux:table.cell>
                            <flux:input
                                type="number"
                                min="0"
                                :max="config('shop.cart.max_quantity_per_item')"
                                :value="$item['quantity']"
                                class="max-w-24"
                                wire:change="updateQuantity({{ $item['product']->id }}, $event.target.value)"
                                data-test="cart-quantity"
                            />
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            NT${{ number_format($item['line_total']) }}
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <flux:button
                                wire:click="remove({{ $item['product']->id }})"
                                size="sm"
                                variant="subtle"
                                icon="trash"
                                :tooltip="__('shop.cart.remove')"
                                data-test="cart-remove"
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <flux:button wire:click="clear" variant="subtle" size="sm" data-test="cart-clear">
                {{ __('shop.cart.clear') }}
            </flux:button>

            <div class="flex items-center gap-4">
                <flux:heading size="lg" data-test="cart-total">
                    {{ __('shop.cart.total') }}: NT${{ number_format($this->cart->total) }}
                </flux:heading>

                <flux:button :href="route('checkout')" variant="primary" wire:navigate data-test="cart-checkout">
                    {{ __('shop.cart.checkout') }}
                </flux:button>
            </div>
        </div>
    @endif
</div>
