<?php

use App\Http\Controllers\Mcp\CartController;
use App\Http\Controllers\Mcp\CatalogController;
use App\Http\Controllers\Mcp\LocaleController;
use App\Http\Controllers\Mcp\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WebMCP tool endpoints
|--------------------------------------------------------------------------
|
| The JSON surface behind the tools registered with the browser through
| document.modelContext. These deliberately run in the "web" middleware group
| rather than a stateless API group: an AI agent driving these tools is acting
| inside the visitor's own browser session, and it must inherit exactly that
| session's identity, locale and permissions - no tokens, no separate
| credentials, no wider access than the person sitting in front of the page.
|
| Writes therefore also carry CSRF protection like any other form post.
|
*/

Route::middleware('throttle:mcp-read')->group(function () {
    Route::get('products', [CatalogController::class, 'products'])->name('products');
    Route::get('products/{identifier}', [CatalogController::class, 'product'])->name('products.show');
    Route::get('categories', [CatalogController::class, 'categories'])->name('categories');
    Route::get('cart', [CartController::class, 'show'])->name('cart');
});

Route::middleware('throttle:mcp-write')->group(function () {
    Route::post('cart/items', [CartController::class, 'store'])->name('cart.store');
    Route::patch('cart/items/{sku}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('cart/items/{sku}', [CartController::class, 'destroyItem'])->name('cart.items.destroy');
    Route::delete('cart', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::post('locale', [LocaleController::class, 'update'])->name('locale');
});

/*
| Orders belong to an account, so these require one. An unauthenticated call
| gets a JSON 401 rather than a redirect, which the tools turn into a message
| telling the agent to ask the customer to sign in - there is deliberately no
| tool that can sign anybody in.
|
| Note what is absent: nothing here confirms an order. prepare_checkout writes
| a draft and stops. Confirming is done by a person in the checkout page.
*/
Route::middleware('auth')->group(function (): void {
    Route::middleware('throttle:mcp-read')->group(function (): void {
        Route::get('orders', [OrderController::class, 'index'])->name('orders');
        Route::get('orders/{number}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('checkout/status', [OrderController::class, 'status'])->name('checkout.status');
    });

    Route::middleware('throttle:mcp-write')->group(function (): void {
        Route::post('checkout/prepare', [OrderController::class, 'prepare'])->name('checkout.prepare');
    });
});
