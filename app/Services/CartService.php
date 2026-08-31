<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ShopException;
use App\Models\Product;
use App\Support\CartSummary;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * The cart, held in the visitor's session.
 *
 * Only product ids and quantities are stored; everything else is resolved on
 * read. Keeping it in the session (rather than a table keyed by session id)
 * means the cart survives the session regeneration that happens at login,
 * and it isolates carts from each other for free: a session cannot address
 * another session's data.
 *
 * The WebMCP tools call the same JSON endpoints from the page with the same
 * cookies, so an agent operates on this cart and no other.
 */
class CartService
{
    public function __construct(private readonly Session $session) {}

    public function summary(): CartSummary
    {
        $quantities = $this->quantities();

        if ($quantities === []) {
            return CartSummary::empty();
        }

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->available()
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->keyBy('id');

        // A product that has been withdrawn since it was added simply falls
        // out of the cart rather than blocking every later operation.
        $this->forgetMissing($quantities, $products);

        $items = collect($quantities)
            ->filter(fn (int $quantity, int $productId): bool => $products->has($productId))
            ->map(function (int $quantity, int $productId) use ($products): array {
                /** @var Product $product */
                $product = $products->get($productId);

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'line_total' => $product->price * $quantity,
                ];
            })
            ->values();

        return new CartSummary($items, (int) $items->sum('line_total'));
    }

    /**
     * Add to the quantity already in the cart.
     */
    public function add(int $productId, int $quantity = 1): CartSummary
    {
        $product = $this->requireAvailableProduct($productId);
        $quantities = $this->quantities();

        $this->put($product, ($quantities[$product->id] ?? 0) + $quantity);

        return $this->summary();
    }

    /**
     * Set an exact quantity. A quantity of zero removes the line.
     */
    public function update(int $productId, int $quantity): CartSummary
    {
        $product = $this->requireAvailableProduct($productId);

        if ($quantity <= 0) {
            return $this->remove($productId);
        }

        $this->put($product, $quantity);

        return $this->summary();
    }

    public function remove(int $productId): CartSummary
    {
        $quantities = $this->quantities();
        unset($quantities[$productId]);
        $this->persist($quantities);

        return $this->summary();
    }

    public function clear(): CartSummary
    {
        $this->persist([]);

        return CartSummary::empty();
    }

    /**
     * Validate a proposed quantity against every limit, then store it.
     */
    private function put(Product $product, int $quantity): void
    {
        $max = (int) config('shop.cart.max_quantity_per_item');

        if ($quantity > $max) {
            throw ShopException::fromKey('shop.errors.quantity_max', ['max' => $max]);
        }

        if ($quantity > $product->stock) {
            throw ShopException::fromKey('shop.errors.insufficient_stock', ['stock' => $product->stock]);
        }

        $quantities = $this->quantities();
        $isNewLine = ! array_key_exists($product->id, $quantities);
        $maxItems = (int) config('shop.cart.max_items');

        if ($isNewLine && count($quantities) >= $maxItems) {
            throw ShopException::fromKey('shop.errors.items_max', ['max' => $maxItems]);
        }

        $quantities[$product->id] = $quantity;

        $this->guardTotal($quantities);

        $this->persist($quantities);
    }

    /**
     * Reject the change if it would push the cart over the value ceiling.
     *
     * @param  array<int, int>  $quantities
     */
    private function guardTotal(array $quantities): void
    {
        $maxTotal = (int) config('shop.cart.max_total');

        $total = Product::query()
            ->available()
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->sum(fn (Product $product): int => $product->price * ($quantities[$product->id] ?? 0));

        if ($total > $maxTotal) {
            throw ShopException::fromKey('shop.errors.total_max', ['max' => number_format($maxTotal)]);
        }
    }

    private function requireAvailableProduct(int $productId): Product
    {
        $product = Product::query()->available()->find($productId);

        if ($product === null) {
            throw ShopException::fromKey('shop.errors.product_unavailable');
        }

        if (! $product->isInStock()) {
            throw ShopException::fromKey('shop.errors.out_of_stock');
        }

        return $product;
    }

    /**
     * @return array<int, int>
     */
    private function quantities(): array
    {
        /** @var array<array-key, mixed> $raw */
        $raw = $this->session->get((string) config('shop.cart.session_key'), []);

        $quantities = [];

        // The session is server-side, but it is still normalised here so that
        // a leftover payload from an older release cannot produce odd types.
        foreach ($raw as $productId => $quantity) {
            if (is_numeric($productId) && is_numeric($quantity) && (int) $quantity > 0) {
                $quantities[(int) $productId] = (int) $quantity;
            }
        }

        return $quantities;
    }

    /**
     * @param  array<int, int>  $quantities
     */
    private function persist(array $quantities): void
    {
        $this->session->put((string) config('shop.cart.session_key'), $quantities);
    }

    /**
     * @param  array<int, int>  $quantities
     * @param  Collection<int, Product>  $products
     */
    private function forgetMissing(array $quantities, Collection $products): void
    {
        $missing = array_diff(array_keys($quantities), $products->keys()->all());

        if ($missing !== []) {
            $this->persist(array_diff_key($quantities, array_flip($missing)));
        }
    }
}
