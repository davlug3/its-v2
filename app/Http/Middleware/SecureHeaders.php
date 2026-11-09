<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Baseline CSP compatible with a same-origin SPA build.
        $scriptSrc = "'self'";
        $styleSrc = "'self' 'unsafe-inline'";
        $imgSrc = "'self' data: blob:";
        $fontSrc = "'self' data:";
        $connectSrc = "'self'";

        // Allow Vite dev server/HMR in local development.
        if (app()->isLocal() || config('app.debug')) {
            $vite = 'http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173';
            $viteHttp = 'http://localhost:5173 http://127.0.0.1:5173';
            $scriptSrc .= ' '.$viteHttp;
            $styleSrc .= ' '.$viteHttp;
            $imgSrc .= ' '.$viteHttp;
            $fontSrc .= ' '.$viteHttp.' data:';
            $connectSrc .= ' '.$vite;
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "img-src {$imgSrc}",
            "font-src {$fontSrc}",
            "style-src {$styleSrc}",
            "script-src {$scriptSrc}",
            "connect-src {$connectSrc}",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
