<?php

namespace Tests\Unit;

use App\Http\Middleware\SecureHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SecureHeadersTest extends TestCase
{
    public function test_adds_baseline_security_headers(): void
    {
        config(['app.debug' => false]);
        $this->app['env'] = 'production';

        $middleware = new SecureHeaders;
        $request = Request::create('https://example.test/', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringNotContainsString('localhost:5173', $csp);
    }

    public function test_adds_hsts_only_for_secure_requests(): void
    {
        config(['app.debug' => false]);
        $this->app['env'] = 'production';

        $middleware = new SecureHeaders;
        $secure = Request::create('https://example.test/', 'GET');
        $insecure = Request::create('http://example.test/', 'GET');

        $secureResponse = $middleware->handle($secure, fn () => new Response('ok', 200));
        $insecureResponse = $middleware->handle($insecure, fn () => new Response('ok', 200));

        $this->assertNotNull($secureResponse->headers->get('Strict-Transport-Security'));
        $this->assertNull($insecureResponse->headers->get('Strict-Transport-Security'));
    }

    public function test_allows_vite_dev_server_in_local_debug(): void
    {
        config(['app.debug' => true]);
        $this->app['env'] = 'local';

        $middleware = new SecureHeaders;
        $request = Request::create('http://localhost/', 'GET');
        $response = $middleware->handle($request, fn () => new Response('ok', 200));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('localhost:5173', $csp);
        $this->assertStringContainsString('ws://localhost:5173', $csp);
    }
}
