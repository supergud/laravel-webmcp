<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * A resolved snapshot of the cart.
 *
 * The session only ever stores product ids and quantities. Prices, names and
 * stock are read from the database every time a summary is built, so a stale
 * session can never sell something at yesterday's price or under a name the
 * catalogue no longer uses.
 */
final readonly class CartSummary
{
    /**
     * @param  Collection<int, array{product: Product, quantity: int, line_total: int}>  $items
     */
    public function __construct(
        public Collection $items,
        public int $total,
    ) {}

    public static function empty(): self
    {
        /** @var Collection<int, array{product: Product, quantity: int, line_total: int}> $items */
        $items = collect();

        return new self($items, 0);
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Distinct products in the cart.
     */
    public function lineCount(): int
    {
        return $this->items->count();
    }

    /**
     * Total number of units across all lines.
     */
    public function unitCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /**
     * The shape handed to WebMCP tools and to the JSON API.
     *
     * Only fields a customer is entitled to see: no internal ids beyond the
     * product's own, no stock levels for products that are not in the cart,
     * nothing about any other session.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => config('shop.currency'),
            'items' => $this->items->map(fn (array $item): array => [
                'sku' => $item['product']->sku,
                'slug' => $item['product']->slug,
                'name' => (string) $item['product']->name,
                'unit_price' => $item['product']->price,
                'quantity' => $item['quantity'],
                'line_total' => $item['line_total'],
            ])->values()->all(),
            'line_count' => $this->lineCount(),
            'unit_count' => $this->unitCount(),
            'total' => $this->total,
        ];
    }
}
