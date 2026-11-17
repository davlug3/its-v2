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
        // The port is read from VITE_PORT so it tracks the actual dev server.
        if (app()->isLocal() || config('app.debug')) {
            $port = (int) config('app.vite_port', 5173);
            $vite = "http://localhost:{$port} http://127.0.0.1:{$port} ws://localhost:{$port} ws://127.0.0.1:{$port}";
            $viteHttp = "http://localhost:{$port} http://127.0.0.1:{$port}";
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
