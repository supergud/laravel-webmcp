<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Registered inside the "web" group on purpose: the WebMCP tools
            // run in the visitor's own browser session and must inherit its
            // cookies, locale and permissions rather than hold credentials of
            // their own. See routes/mcp.php.
            Route::middleware('web')
                ->prefix('api/mcp')
                ->name('mcp.')
                ->group(base_path('routes/mcp.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
