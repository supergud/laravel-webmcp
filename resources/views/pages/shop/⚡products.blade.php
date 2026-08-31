<?php

use App\Services\CatalogService;
use App\Support\Locales;
use App\Support\ProductQuery;
use App\Support\ProductSort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.shop')] #[Title('Products')] class extends Component {
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $term = '';

    #[Url(as: 'category', except: '')]
    public string $categorySlug = '';

    #[Url(as: 'min', except: '')]
    public string $minPrice = '';

    #[Url(as: 'max', except: '')]
    public string $maxPrice = '';

    #[Url(as: 'sort', except: 'newest')]
    public string $sort = 'newest';

    /**
     * Any change to the filters puts the visitor back on page one, otherwise
     * narrowing a search can land them on a page that no longer exists.
     */
    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['term', 'categorySlug', 'minPrice', 'maxPrice', 'sort']);
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, \App\Models\Product>
     */
    #[Computed]
    public function products(): LengthAwarePaginator
    {
        return app(CatalogService::class)->paginate(
            ProductQuery::fromArray([
                'term' => $this->term,
                'category' => $this->categorySlug,
                'min_price' => $this->minPrice,
                'max_price' => $this->maxPrice,
                'sort' => $this->sort,
                'page' => $this->getPage(),
            ]),
            Locales::current(),
        );
    }

    /**
     * @return Collection<int, \App\Models\Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return app(CatalogService::class)->categories();
    }

    /**
     * @return list<ProductSort>
     */
    #[Computed]
    public function sortOptions(): array
    {
        return ProductSort::cases();
    }
}; ?>

<div class="flex flex-col gap-6 py-6">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1">{{ __('shop.products.title') }}</flux:heading>
        <flux:text>{{ trans_choice('shop.products.results', $this->products->total(), ['count' => $this->products->total()]) }}</flux:text>
    </div>

    {{-- Filters --}}
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <flux:input
                wire:model.live.debounce.400ms="term"
                :placeholder="__('shop.products.search_placeholder')"
                icon="magnifying-glass"
                kbd="/"
                clearable
                data-test="product-search"
            />
        </div>

        <flux:select wire:model.live="categorySlug" data-test="category-filter">
            <flux:select.option value="">{{ __('shop.products.all_categories') }}</flux:select.option>
            @foreach ($this->categories as $category)
                <flux:select.option :value="$category->slug">
                    {{ $category->name }} ({{ $category->products_count }})
                </flux:select.option>
            @endforeach
        </flux:select>

        <div class="flex gap-2">
            <flux:input
                wire:model.live.debounce.500ms="minPrice"
                type="number"
                min="0"
                :placeholder="__('shop.products.price_from')"
                data-test="min-price"
            />
            <flux:input
                wire:model.live.debounce.500ms="maxPrice"
                type="number"
                min="0"
                :placeholder="__('shop.products.price_to')"
                data-test="max-price"
            />
        </div>

        <flux:select wire:model.live="sort" data-test="sort">
            @foreach ($this->sortOptions as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- Results --}}
    @if ($this->products->isEmpty())
        <flux:callout icon="magnifying-glass" data-test="no-results">
            <flux:callout.heading>{{ __('shop.products.empty') }}</flux:callout.heading>
            <x-slot name="actions">
                <flux:button wire:click="clearFilters" size="sm" variant="ghost">
                    {{ __('shop.products.all_categories') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-test="product-grid">
            @foreach ($this->products as $product)
                <flux:card
                    class="flex flex-col gap-2"
                    wire:key="product-{{ $product->id }}"
                    data-test="product-card"
                    data-sku="{{ $product->sku }}"
                >
                    <flux:badge size="sm" variant="subtle">{{ $product->category->name }}</flux:badge>

                    <flux:heading size="lg">
                        <flux:link
                            :href="route('products.show', ['slug' => $product->slug])"
                            variant="ghost"
                            wire:navigate
                        >
                            {{ $product->name }}
                        </flux:link>
                    </flux:heading>

                    <flux:text class="line-clamp-2">{{ $product->description }}</flux:text>

                    <flux:spacer />

                    <div class="flex items-center justify-between pt-2">
                        <flux:heading size="lg">NT${{ number_format($product->price) }}</flux:heading>
                        @if ($product->isInStock())
                            <flux:badge size="sm" color="green">{{ __('shop.products.in_stock') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="zinc">{{ __('shop.products.out_of_stock') }}</flux:badge>
                        @endif
                    </div>

                    <livewire:add-to-cart
                        :product-id="$product->id"
                        :product-name="(string) $product->name"
                        :available="$product->isInStock()"
                        :key="'add-'.$product->id"
                    />
                </flux:card>
            @endforeach
        </div>

        {{ $this->products->links() }}
    @endif
</div>
