<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Add browser and API security headers without changing
     * authentication, response bodies, or application design.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /** @var Response $response */
        $response = $next($request);

        /*
        |--------------------------------------------------------------------------
        | Prevent Content-Type Sniffing
        |--------------------------------------------------------------------------
        */

        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff'
        );

        /*
        |--------------------------------------------------------------------------
        | Referrer Privacy
        |--------------------------------------------------------------------------
        */

        $response->headers->set(
            'Referrer-Policy',
            'strict-origin-when-cross-origin'
        );

        /*
        |--------------------------------------------------------------------------
        | Prevent Admin Iframe Embedding
        |--------------------------------------------------------------------------
        */

        if ($request->is('admin', 'admin/*')) {
            $response->headers->set(
                'X-Frame-Options',
                'DENY'
            );

            $response->headers->set(
                'Content-Security-Policy',
                "frame-ancestors 'none'"
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Sensitive API Response Caching
        |--------------------------------------------------------------------------
        |
        | Authentication responses and bearer-token API responses
        | must not be stored by browsers or shared proxy caches.
        |
        */

        $isAuthenticationEndpoint = $request->is(
            'api/v1/auth/*',
            'api/v1/admin/auth/*'
        );

        $hasBearerToken =
            $request->bearerToken() !== null;

        if (
            $isAuthenticationEndpoint
            || (
                $request->is('api/*')
                && $hasBearerToken
            )
        ) {
            $response->headers->set(
                'Cache-Control',
                'private, no-store, max-age=0'
            );

            $response->headers->set(
                'Pragma',
                'no-cache'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | HTTPS Strict Transport Security
        |--------------------------------------------------------------------------
        |
        | Enable HSTS only in production over HTTPS.
        |
        | Do not force HTTPS for the current local HTTP
        | development server or local Wi-Fi mobile connection.
        |
        */

        if (
            app()->environment('production')
            && $request->isSecure()
        ) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000'
            );
        }

        return $response;
    }
}
