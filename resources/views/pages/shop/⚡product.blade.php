<?php

use App\Models\Product;
use App\Services\CatalogService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.shop')] class extends Component {
    public Product $product;

    /**
     * Resolved from the slug rather than by route-model binding so that the
     * availability rules in CatalogService apply: an inactive product 404s
     * instead of rendering.
     */
    public function mount(string $slug): void
    {
        $this->product = app(CatalogService::class)->findBySlug($slug) ?? abort(404);
    }

    public function title(): string
    {
        return (string) $this->product->name;
    }
}; ?>

<div class="flex flex-col gap-6 py-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('home')" wire:navigate>
            {{ __('shop.nav.products') }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item
            :href="route('home', ['category' => $product->category->slug])"
            wire:navigate
        >
            {{ $product->category->name }}
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $product->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-4" data-test="product-detail" data-sku="{{ $product->sku }}">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl" level="1">{{ $product->name }}</flux:heading>
            <flux:text>{{ __('shop.products.sku') }}: {{ $product->sku }}</flux:text>
        </div>

        <flux:separator />

        <flux:text class="max-w-2xl whitespace-pre-line">{{ $product->description }}</flux:text>

        <div class="flex items-center gap-3">
            <flux:heading size="xl">NT${{ number_format($product->price) }}</flux:heading>

            @if ($product->isInStock())
                <flux:badge color="green">{{ __('shop.products.in_stock') }}</flux:badge>
            @else
                <flux:badge color="zinc">{{ __('shop.products.out_of_stock') }}</flux:badge>
            @endif
        </div>
    </div>
</div>
