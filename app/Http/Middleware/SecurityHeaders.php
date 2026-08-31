<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // PHP adds this itself, so it has to be removed at the SAPI level
        // rather than from the response object.
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Nothing here needs any of these, and an AI agent driving the page
        // should not be able to reach them by any route either.
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()',
        );

        // A deliberately partial Content-Security-Policy.
        //
        // script-src is NOT set. Livewire and Alpine evaluate expressions at
        // runtime, so a script-src strict enough to be worth having would break
        // the application, and one loose enough to work (unsafe-eval,
        // unsafe-inline) would be security theatre. The directives below are
        // the ones that hold without lying: the page cannot be framed, cannot
        // have its base URL rewritten, cannot post a form anywhere else, and
        // cannot load plugins.
        $response->headers->set(
            'Content-Security-Policy',
            "base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'",
        );

        if ($request->is('api/mcp/*')) {
            // Tool responses carry cart and order contents. Nothing should
            // hold on to them.
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Only in production: pinning HSTS against a local hostname would
        // outlive this demo in the developer's browser.
        if (app()->isProduction() && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
