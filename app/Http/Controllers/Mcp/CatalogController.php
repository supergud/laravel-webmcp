<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CatalogService;
use App\Support\Locales;
use App\Support\ProductQuery;
use App\Support\ProductSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only catalogue endpoints behind the WebMCP tools.
 *
 * These take no privileged path: they call the same CatalogService the
 * storefront page calls, so an agent sees exactly the rows a visitor sees.
 *
 * The inputSchema published to the browser is a hint for the model, not a
 * guarantee - anything can POST here - so every parameter is revalidated and
 * clamped server-side through ProductQuery.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'term' => ['nullable', 'string', 'max:'.ProductQuery::MAX_TERM_LENGTH],
            'category' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'integer', 'min:0', 'max:'.ProductQuery::MAX_PRICE],
            'max_price' => ['nullable', 'integer', 'min:0', 'max:'.ProductQuery::MAX_PRICE],
            'sort' => ['nullable', 'string', 'in:'.implode(',', ProductSort::values())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ProductQuery::MAX_PER_PAGE],
        ]);

        $results = $this->catalog->paginate(ProductQuery::fromArray($validated), Locales::current());

        return response()->json([
            'locale' => Locales::current(),
            'currency' => config('shop.currency'),
            'total' => $results->total(),
            'page' => $results->currentPage(),
            'per_page' => $results->perPage(),
            'last_page' => $results->lastPage(),
            'products' => array_map(
                fn (Product $product): array => $product->toToolArray(),
                $results->items(),
            ),
        ]);
    }

    /**
     * Look a product up by SKU or by slug, whichever the agent happens to hold.
     */
    public function product(string $identifier): JsonResponse
    {
        $product = $this->catalog->findBySku($identifier) ?? $this->catalog->findBySlug($identifier);

        if ($product === null) {
            return response()->json([
                'error' => ['code' => 'not_found', 'message' => __('shop.errors.product_unavailable')],
            ], 404);
        }

        return response()->json([
            'locale' => Locales::current(),
            'product' => $product->toToolArray(),
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'locale' => Locales::current(),
            'categories' => $this->catalog->categories()->map(fn ($category): array => [
                'slug' => $category->slug,
                'name' => (string) $category->name,
                'description' => (string) $category->description,
                'product_count' => (int) $category->products_count,
            ])->all(),
        ]);
    }
}
