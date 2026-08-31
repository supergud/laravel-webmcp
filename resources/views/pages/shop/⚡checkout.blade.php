<?php

use App\Exceptions\ShopException;
use App\Models\Order;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Support\CartSummary;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.shop')] class extends Component {
    public string $shippingName = '';

    public string $shippingEmail = '';

    public string $shippingAddress = '';

    /**
     * The confirmation token for the outstanding draft.
     *
     * It is deliberately held here and nowhere else that automation can reach:
     * no WebMCP tool response contains it, and Order hides it from every
     * serialisation. Confirming therefore requires something that only exists
     * in this rendered page.
     */
    public string $confirmationToken = '';

    public function mount(): void
    {
        $user = Auth::user();

        $this->shippingName = $this->shippingName !== '' ? $this->shippingName : (string) $user?->name;
        $this->shippingEmail = $this->shippingEmail !== '' ? $this->shippingEmail : (string) $user?->email;

        $this->syncDraftToken();
    }

    #[Computed]
    public function cart(): CartSummary
    {
        return app(CartService::class)->summary();
    }

    #[Computed]
    public function draft(): ?Order
    {
        return app(CheckoutService::class)->currentDraft(Auth::user());
    }

    /**
     * Re-read state when a WebMCP tool changes the cart or prepares a draft.
     */
    #[On(['cart-updated', 'checkout-updated'])]
    public function refresh(): void
    {
        unset($this->cart, $this->draft);

        $this->syncDraftToken();
    }

    /**
     * Write a draft order. This is the half that prepare_checkout also reaches:
     * it takes no payment, touches no stock and expires by itself.
     */
    public function prepare(): void
    {
        $validated = $this->validate([
            'shippingName' => ['required', 'string', 'max:255'],
            'shippingEmail' => ['required', 'string', 'email', 'max:255'],
            'shippingAddress' => ['required', 'string', 'max:500'],
        ]);

        try {
            $order = app(CheckoutService::class)->prepareDraft(Auth::user(), [
                'shipping_name' => $validated['shippingName'],
                'shipping_email' => $validated['shippingEmail'],
                'shipping_address' => $validated['shippingAddress'],
            ]);
        } catch (ShopException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->draft);
        $this->confirmationToken = (string) $order->confirmation_token;

        Flux::toast(text: __('shop.checkout.prepared', ['number' => $order->number]));

        $this->dispatch('checkout-updated');
    }

    /**
     * The irreversible step. Reachable only from this component - there is no
     * WebMCP tool that can call it.
     */
    public function confirm(): void
    {
        try {
            $order = app(CheckoutService::class)->confirm(Auth::user(), $this->confirmationToken);
        } catch (ShopException $exception) {
            unset($this->cart, $this->draft);
            $this->confirmationToken = '';

            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->confirmationToken = '';

        Flux::toast(variant: 'success', text: __('shop.checkout.placed', ['number' => $order->number]));

        $this->redirectRoute('orders.show', ['number' => $order->number], navigate: true);
    }

    public function cancel(): void
    {
        app(CheckoutService::class)->cancelDraft(Auth::user());

        unset($this->draft);
        $this->confirmationToken = '';

        Flux::toast(text: __('shop.checkout.cancelled'));

        $this->dispatch('checkout-updated');
    }

    private function syncDraftToken(): void
    {
        $draft = $this->draft;

        $this->confirmationToken = $draft === null ? '' : (string) $draft->confirmation_token;
    }

    public function title(): string
    {
        return __('shop.checkout.title');
    }
}; ?>

<div class="flex flex-col gap-6 py-6">
    <flux:heading size="xl" level="1">{{ __('shop.checkout.title') }}</flux:heading>

    @if ($this->draft)
        {{-- A draft is outstanding: this is the only place it can be confirmed. --}}
        <flux:callout variant="warning" icon="clock" data-test="draft-panel">
            <flux:callout.heading>{{ __('shop.checkout.pending_draft') }}</flux:callout.heading>
            <flux:callout.text>
                {{ $this->draft->number }} &middot;
                {{ __('shop.checkout.expires_in', ['time' => $this->draft->expires_at?->diffForHumans()]) }}
            </flux:callout.text>
        </flux:callout>

        <flux:table data-test="draft-items">
            <flux:table.columns>
                <flux:table.column>{{ __('shop.orders.items') }}</flux:table.column>
                <flux:table.column>{{ __('shop.cart.quantity') }}</flux:table.column>
                <flux:table.column align="end">{{ __('shop.cart.subtotal') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->draft->items as $item)
                    <flux:table.row wire:key="draft-item-{{ $item->id }}" data-sku="{{ $item->sku }}">
                        <flux:table.cell>{{ $item->name }}</flux:table.cell>
                        <flux:table.cell>{{ $item->quantity }}</flux:table.cell>
                        <flux:table.cell align="end">NT${{ number_format($item->line_total) }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="flex flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading size="lg" data-test="draft-total">
                {{ __('shop.cart.total') }}: NT${{ number_format($this->draft->total) }}
            </flux:heading>

            <flux:text>{{ __('shop.checkout.confirm_hint') }}</flux:text>

            <div class="flex flex-wrap gap-2">
                <flux:button wire:click="confirm" variant="primary" data-test="confirm-order">
                    {{ __('shop.checkout.confirm') }}
                </flux:button>

                <flux:button wire:click="cancel" variant="subtle" data-test="cancel-order">
                    {{ __('shop.checkout.cancel') }}
                </flux:button>
            </div>
        </div>
    @elseif ($this->cart->isEmpty())
        <flux:callout icon="shopping-cart" data-test="empty-cart">
            <flux:callout.heading>{{ __('shop.cart.empty') }}</flux:callout.heading>
            <x-slot name="actions">
                <flux:button :href="route('home')" size="sm" variant="primary" wire:navigate>
                    {{ __('shop.cart.continue_shopping') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @else
        <flux:text>{{ __('shop.checkout.no_draft_yet') }}</flux:text>

        <form wire:submit="prepare" class="flex max-w-lg flex-col gap-4" data-test="checkout-form">
            <flux:input wire:model="shippingName" :label="__('shop.checkout.shipping_name')" required />
            <flux:input wire:model="shippingEmail" type="email" :label="__('shop.checkout.shipping_email')" required />
            <flux:textarea wire:model="shippingAddress" :label="__('shop.checkout.shipping_address')" rows="3" required />

            <flux:heading size="lg" data-test="checkout-total">
                {{ __('shop.cart.total') }}: NT${{ number_format($this->cart->total) }}
            </flux:heading>

            <flux:button type="submit" variant="primary" data-test="prepare-order">
                {{ __('shop.checkout.prepare') }}
            </flux:button>
        </form>
    @endif
</div>
