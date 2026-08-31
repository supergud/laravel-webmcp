<?php

use App\Http\Controllers\Mcp\CartController;
use App\Http\Controllers\Mcp\CatalogController;
use App\Http\Controllers\Mcp\LocaleController;
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
