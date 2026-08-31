<?php

use App\Models\Order;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.shop')] class extends Component {
    public Order $order;

    /**
     * The lookup is scoped to the signed-in user, so another customer's order
     * number 404s exactly like one that was never issued. Nothing in the
     * response distinguishes the two cases.
     */
    public function mount(string $number): void
    {
        $this->order = app(CheckoutService::class)->orderFor(Auth::user(), $number) ?? abort(404);
    }

    public function title(): string
    {
        return $this->order->number;
    }
}; ?>

<div class="flex flex-col gap-6 py-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('orders.index')" wire:navigate>
            {{ __('shop.orders.title') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $order->number }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-wrap items-center gap-3">
        <flux:heading size="xl" level="1">{{ $order->number }}</flux:heading>
        <flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge>
    </div>

    <div class="grid gap-1">
        <flux:text>{{ __('shop.orders.placed_at') }}: {{ $order->confirmed_at?->isoFormat('LLL') }}</flux:text>
        <flux:text>{{ __('shop.checkout.shipping_name') }}: {{ $order->shipping_name }}</flux:text>
        <flux:text>{{ __('shop.checkout.shipping_address') }}: {{ $order->shipping_address }}</flux:text>
    </div>

    <flux:table data-test="order-items">
        <flux:table.columns>
            <flux:table.column>{{ __('shop.orders.items') }}</flux:table.column>
            <flux:table.column>{{ __('shop.cart.quantity') }}</flux:table.column>
            <flux:table.column align="end">{{ __('shop.cart.subtotal') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($order->items as $item)
                <flux:table.row wire:key="item-{{ $item->id }}" data-sku="{{ $item->sku }}">
                    <flux:table.cell>
                        {{ $item->name }}
                        <flux:text size="sm">{{ __('shop.products.sku') }}: {{ $item->sku }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell>{{ $item->quantity }}</flux:table.cell>
                    <flux:table.cell align="end">NT${{ number_format($item->line_total) }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:heading size="lg" data-test="order-total">
        {{ __('shop.orders.total') }}: NT${{ number_format($order->total) }}
    </flux:heading>
</div>
