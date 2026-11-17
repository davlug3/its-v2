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
        // Use a wildcard port so any Vite dev server port works, even when
        // the configured port is already taken and Vite picks the next one.
        if (app()->isLocal() || config('app.debug')) {
            $vite = 'http://localhost:* http://127.0.0.1:* ws://localhost:* ws://127.0.0.1:*';
            $viteHttp = 'http://localhost:* http://127.0.0.1:*';
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
