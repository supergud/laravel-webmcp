<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Support\Locales;
use App\Support\ProductQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read access to the catalogue.
 *
 * The Livewire listing page and the read-side WebMCP tools both go through
 * this class, so a person and an agent see exactly the same rows under exactly
 * the same visibility rules.
 */
class CatalogService
{
    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(ProductQuery $query, ?string $locale = null): LengthAwarePaginator
    {
        $locale = Locales::sanitize($locale ?? Locales::current());

        $builder = Product::query()
            ->available()
            ->with('category');

        if ($query->hasTerm()) {
            $builder->search((string) $query->term, $locale);
        }

        if ($query->categorySlug !== null) {
            $builder->whereHas('category', fn ($category) => $category->where('slug', $query->categorySlug));
        }

        if ($query->minPrice !== null) {
            $builder->where('price', '>=', $query->minPrice);
        }

        if ($query->maxPrice !== null) {
            $builder->where('price', '<=', $query->maxPrice);
        }

        [$column, $direction] = $query->sort->toOrderBy($locale);

        return $builder
            ->orderBy($column, $direction)
            ->paginate(perPage: $query->perPage, page: $query->page);
    }

    /**
     * Look a single product up by its slug, respecting visibility rules.
     */
    public function findBySlug(string $slug): ?Product
    {
        return Product::query()
            ->available()
            ->with('category')
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Look a single product up by its SKU, respecting visibility rules.
     */
    public function findBySku(string $sku): ?Product
    {
        return Product::query()
            ->available()
            ->with('category')
            ->where('sku', $sku)
            ->first();
    }

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()
            ->withCount(['products' => fn ($products) => $products->available()])
            ->orderBy('position')
            ->get();
    }
}
