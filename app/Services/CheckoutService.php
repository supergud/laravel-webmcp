<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ShopException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Checkout, split deliberately into two halves.
 *
 * prepareDraft() is safe to expose to automation: it writes a draft that has
 * no effect on stock, takes no payment, expires on its own and can be replaced
 * or cancelled freely. The prepare_checkout WebMCP tool reaches this half.
 *
 * confirm() is the irreversible half, and no tool reaches it. It is called
 * only from the checkout page's Livewire component, using a token that exists
 * nowhere except in the rendered page and the database row.
 *
 * The honest limit of that split: an agent that shares the session could still
 * forge a Livewire request if it scraped the page. What the split does buy is
 * that no affordance to confirm is advertised to an agent, so text on a page -
 * a product description, a review - cannot talk an agent into placing an order
 * with the tools it has been given.
 */
class CheckoutService
{
    public function __construct(private readonly CartService $cart) {}

    /**
     * The draft this user is currently being asked to confirm, if any.
     */
    public function currentDraft(User $user): ?Order
    {
        $draft = Order::query()
            ->ownedBy($user->id)
            ->where('status', OrderStatus::Draft)
            ->with('items')
            ->latest('id')
            ->first();

        if ($draft === null) {
            return null;
        }

        if ($draft->hasExpired()) {
            $this->discard($draft);

            return null;
        }

        return $draft;
    }

    /**
     * Turn the current cart into a draft order awaiting confirmation.
     *
     * @param  array{shipping_name: string, shipping_email: string, shipping_address: string}  $shipping
     */
    public function prepareDraft(User $user, array $shipping): Order
    {
        $summary = $this->cart->summary();

        if ($summary->isEmpty()) {
            throw ShopException::fromKey('shop.errors.cart_empty');
        }

        return DB::transaction(function () use ($user, $shipping, $summary): Order {
            // Only one draft can be outstanding, so preparing again replaces
            // the previous one rather than leaving confirmable leftovers.
            Order::query()
                ->ownedBy($user->id)
                ->where('status', OrderStatus::Draft)
                ->get()
                ->each(fn (Order $order) => $this->discard($order));

            $order = Order::create([
                'user_id' => $user->id,
                'number' => $this->generateNumber(),
                'status' => OrderStatus::Draft,
                'total' => $summary->total,
                'currency' => (string) config('shop.currency'),
                'shipping_name' => $shipping['shipping_name'],
                'shipping_email' => $shipping['shipping_email'],
                'shipping_address' => $shipping['shipping_address'],
                'confirmation_token' => Str::random(64),
                'expires_at' => now()->addMinutes((int) config('shop.checkout.draft_lifetime_minutes')),
            ]);

            foreach ($summary->items as $item) {
                /** @var Product $product */
                $product = $item['product'];

                $order->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    // Snapshot every translation, not just the active one.
                    'name' => $product->getTranslations('name'),
                    'unit_price' => $product->price,
                    'quantity' => $item['quantity'],
                    'line_total' => $item['line_total'],
                ]);
            }

            return $order->load('items');
        });
    }

    /**
     * Confirm a draft. This is the irreversible step.
     *
     * The token is compared in constant time against the user's own draft
     * rather than looked up directly, so a token cannot be used to discover
     * or address an order belonging to anyone else.
     */
    public function confirm(User $user, string $token): Order
    {
        // Expiry is settled before the transaction opens. Discarding inside it
        // and then throwing would roll the discard back, leaving an expired
        // draft sitting in the database on every failed attempt.
        $expired = Order::query()
            ->ownedBy($user->id)
            ->where('status', OrderStatus::Draft)
            ->latest('id')
            ->first();

        if ($expired !== null && $expired->hasExpired()) {
            $this->discard($expired);

            throw ShopException::fromKey('shop.checkout.expired');
        }

        return DB::transaction(function () use ($user, $token): Order {
            $draft = Order::query()
                ->ownedBy($user->id)
                ->where('status', OrderStatus::Draft)
                ->with('items')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($draft === null) {
                throw ShopException::fromKey('shop.errors.no_draft');
            }

            if ($draft->confirmation_token === null || ! hash_equals($draft->confirmation_token, $token)) {
                throw ShopException::fromKey('shop.errors.invalid_confirmation');
            }

            if ($draft->hasExpired()) {
                $this->discard($draft);

                throw ShopException::fromKey('shop.checkout.expired');
            }

            $this->assertStillFulfillable($draft);

            $this->decrementStock($draft);

            $draft->update([
                'status' => OrderStatus::Paid,
                'confirmed_at' => now(),
                // Burning the token makes the confirmation single-use.
                'confirmation_token' => null,
                'expires_at' => null,
            ]);

            $this->cart->clear();

            return $draft->refresh()->load('items');
        });
    }

    public function cancelDraft(User $user): void
    {
        $draft = $this->currentDraft($user);

        if ($draft !== null) {
            $this->discard($draft);
        }
    }

    /**
     * Placed orders belonging to this user, newest first.
     *
     * @return Collection<int, Order>
     */
    public function ordersFor(User $user): Collection
    {
        return Order::query()
            ->ownedBy($user->id)
            ->placed()
            ->with('items')
            ->latest('id')
            ->get();
    }

    /**
     * A single placed order, or null. Scoped by user, so an order number
     * belonging to somebody else is indistinguishable from one that does not
     * exist.
     */
    public function orderFor(User $user, string $number): ?Order
    {
        return Order::query()
            ->ownedBy($user->id)
            ->placed()
            ->where('number', $number)
            ->with('items')
            ->first();
    }

    /**
     * The catalogue can move between preparing and confirming a draft, so the
     * whole order is re-checked at the moment it becomes real.
     */
    private function assertStillFulfillable(Order $draft): void
    {
        $products = Product::query()
            ->available()
            ->whereIn('id', $draft->items->pluck('product_id')->filter()->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($draft->items as $item) {
            $product = $item->product_id === null ? null : $products->get($item->product_id);

            if ($product === null) {
                throw ShopException::fromKey('shop.errors.draft_product_unavailable', ['sku' => $item->sku]);
            }

            if ($product->price !== $item->unit_price) {
                throw ShopException::fromKey('shop.errors.draft_price_changed', ['sku' => $item->sku]);
            }

            if ($product->stock < $item->quantity) {
                throw ShopException::fromKey('shop.errors.draft_out_of_stock', ['sku' => $item->sku]);
            }
        }
    }

    private function decrementStock(Order $draft): void
    {
        foreach ($draft->items as $item) {
            if ($item->product_id !== null) {
                Product::query()
                    ->whereKey($item->product_id)
                    ->decrement('stock', $item->quantity);
            }
        }
    }

    private function discard(Order $order): void
    {
        $order->update([
            'status' => OrderStatus::Cancelled,
            'confirmation_token' => null,
            'expires_at' => null,
        ]);
    }

    private function generateNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
