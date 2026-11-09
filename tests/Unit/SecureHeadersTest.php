<?php

namespace Tests\Unit;

use App\Http\Middleware\SecureHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;

class SecureHeadersTest extends TestCase
{
    public function test_adds_baseline_security_headers(): void
    {
        $middleware = new SecureHeaders;
        $request = Request::create('https://example.test/', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok', 200));

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_adds_hsts_only_for_secure_requests(): void
    {
        $middleware = new SecureHeaders;
        $secure = Request::create('https://example.test/', 'GET');
        $insecure = Request::create('http://example.test/', 'GET');

        $secureResponse = $middleware->handle($secure, fn () => new Response('ok', 200));
        $insecureResponse = $middleware->handle($insecure, fn () => new Response('ok', 200));

        $this->assertNotNull($secureResponse->headers->get('Strict-Transport-Security'));
        $this->assertNull($insecureResponse->headers->get('Strict-Transport-Security'));
    }
}
