<?php

use App\Services\CheckoutService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.shop')] class extends Component {
    /**
     * @return Collection<int, \App\Models\Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        // Scoped to the signed-in user inside the service, so there is no way
        // to widen this by tampering with a parameter: there is no parameter.
        return app(CheckoutService::class)->ordersFor(Auth::user());
    }

    public function title(): string
    {
        return __('shop.orders.title');
    }
}; ?>

<div class="flex flex-col gap-6 py-6">
    <flux:heading size="xl" level="1">{{ __('shop.orders.title') }}</flux:heading>

    @if ($this->orders->isEmpty())
        <flux:callout icon="receipt-percent" data-test="no-orders">
            <flux:callout.heading>{{ __('shop.orders.empty') }}</flux:callout.heading>
            <x-slot name="actions">
                <flux:button :href="route('home')" size="sm" variant="primary" wire:navigate>
                    {{ __('shop.cart.continue_shopping') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @else
        <flux:table data-test="orders-table">
            <flux:table.columns>
                <flux:table.column>{{ __('shop.orders.number') }}</flux:table.column>
                <flux:table.column>{{ __('shop.orders.placed_at') }}</flux:table.column>
                <flux:table.column>{{ __('shop.orders.status') }}</flux:table.column>
                <flux:table.column align="end">{{ __('shop.orders.total') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->orders as $order)
                    <flux:table.row wire:key="order-{{ $order->id }}" data-order="{{ $order->number }}">
                        <flux:table.cell>{{ $order->number }}</flux:table.cell>
                        <flux:table.cell>{{ $order->confirmed_at?->isoFormat('LLL') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$order->status->color()">
                                {{ $order->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">NT${{ number_format($order->total) }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:link
                                :href="route('orders.show', ['number' => $order->number])"
                                wire:navigate
                            >
                                {{ __('shop.orders.view') }}
                            </flux:link>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
