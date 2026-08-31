<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mcp;

use App\Exceptions\ShopException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutService;
use App\Support\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Order and checkout endpoints behind the WebMCP tools.
 *
 * Note what is missing: there is no confirm action here, and there never will
 * be. An agent can assemble an order and hand it to the customer; turning it
 * into a real order is done by a person in the checkout page, using a token
 * that only that page holds.
 *
 * That is the whole point of the split. Product descriptions, reviews and
 * search results are untrusted text that flows straight into an agent's
 * context, so a tool that placed orders would be a tool that page text could
 * talk an agent into using.
 */
class OrderController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'locale' => Locales::current(),
            'orders' => $this->checkout->ordersFor($this->user())
                ->map(fn (Order $order): array => $order->toToolArray())
                ->all(),
        ]);
    }

    public function show(string $number): JsonResponse
    {
        $order = $this->checkout->orderFor($this->user(), $number);

        if ($order === null) {
            // Scoped to the caller, so somebody else's order number is
            // indistinguishable from one that was never issued.
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'not_found', 'message' => __('shop.errors.order_not_found')],
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'locale' => Locales::current(),
            'order' => $order->toToolArray(),
        ]);
    }

    /**
     * Write a draft order and send the customer to the page where they can
     * confirm it. Takes no payment and touches no stock.
     */
    public function prepare(Request $request): JsonResponse
    {
        $user = $this->user();

        $validated = $request->validate([
            'shipping_address' => ['required', 'string', 'max:500'],
            'shipping_name' => ['nullable', 'string', 'max:255'],
            'shipping_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        try {
            $draft = $this->checkout->prepareDraft($user, [
                'shipping_name' => $validated['shipping_name'] ?? $user->name,
                'shipping_email' => $validated['shipping_email'] ?? $user->email,
                'shipping_address' => $validated['shipping_address'],
            ]);
        } catch (ShopException $exception) {
            return response()->json([
                'ok' => false,
                'error' => ['code' => 'rejected', 'message' => $exception->getMessage()],
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'locale' => Locales::current(),
            'draft' => $draft->toToolArray(),
            'confirmation_url' => route('checkout'),
            'next_step' => __('shop.checkout.agent_next_step'),
        ]);
    }

    /**
     * Lets an agent find out whether the customer confirmed, without giving it
     * any way to confirm on their behalf.
     */
    public function status(): JsonResponse
    {
        $user = $this->user();
        $draft = $this->checkout->currentDraft($user);
        $latest = $this->checkout->ordersFor($user)->first();

        return response()->json([
            'ok' => true,
            'locale' => Locales::current(),
            'awaiting_confirmation' => $draft !== null,
            'draft' => $draft?->toToolArray(),
            'confirmation_url' => route('checkout'),
            'latest_order' => $latest?->toToolArray(),
            'note' => __('shop.checkout.agent_cannot_confirm'),
        ]);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
