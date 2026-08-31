<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use App\Exceptions\ShopException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CartService;
use App\Support\CartSummary;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cart endpoints behind the write-side WebMCP tools.
 *
 * These are the first tools that change anything, and they are deliberately
 * thin: every rule - the per-product cap, the line cap, the value ceiling,
 * stock and availability - lives in CartService, which the Livewire UI calls
 * too. There is no path through here that a person clicking in the page could
 * not also take.
 *
 * Products are addressed by SKU rather than by primary key, so an agent works
 * with the same identifier the catalogue prints, and internal ids stay
 * internal.
 */
class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(): JsonResponse
    {
        return $this->respond($this->cart->summary());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:64'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.config('shop.cart.max_quantity_per_item')],
        ]);

        return $this->attempt(fn (): CartSummary => $this->cart->add(
            $this->productId($validated['sku']),
            (int) ($validated['quantity'] ?? 1),
        ));
    }

    public function update(Request $request, string $sku): JsonResponse
    {
        $validated = $request->validate([
            // Zero is allowed and means "remove this line", which is a more
            // forgiving thing for an agent to get right than a separate call.
            'quantity' => ['required', 'integer', 'min:0', 'max:'.config('shop.cart.max_quantity_per_item')],
        ]);

        return $this->attempt(fn (): CartSummary => $this->cart->update(
            $this->productId($sku),
            (int) $validated['quantity'],
        ));
    }

    public function destroyItem(string $sku): JsonResponse
    {
        return $this->attempt(fn (): CartSummary => $this->cart->remove($this->productId($sku)));
    }

    public function destroy(): JsonResponse
    {
        return $this->attempt(fn (): CartSummary => $this->cart->clear());
    }

    /**
     * Resolve a SKU to a product id, or fail the way the cart itself would.
     */
    private function productId(string $sku): int
    {
        $product = Product::query()->available()->where('sku', $sku)->first();

        if ($product === null) {
            throw ShopException::fromKey('shop.errors.product_unavailable');
        }

        return $product->id;
    }

    /**
     * @param  callable(): CartSummary  $action
     */
    private function attempt(callable $action): JsonResponse
    {
        try {
            return $this->respond($action());
        } catch (ShopException $exception) {
            // A rule the caller broke, not a crash. The message is already
            // localized and safe to hand to an agent: it says which limit was
            // hit and nothing about anybody else.
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'rejected', 'message' => $exception->getMessage()],
                'cart' => $this->cart->summary()->toArray(),
            ], 422);
        }
    }

    private function respond(CartSummary $summary): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'locale' => Locales::current(),
            'limits' => [
                'max_quantity_per_item' => (int) config('shop.cart.max_quantity_per_item'),
                'max_items' => (int) config('shop.cart.max_items'),
                'max_total' => (int) config('shop.cart.max_total'),
            ],
            'cart' => $summary->toArray(),
        ]);
    }
}
