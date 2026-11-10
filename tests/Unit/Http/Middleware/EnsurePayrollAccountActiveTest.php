<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsurePayrollAccountActive;
use App\Services\PayrollAccountStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class EnsurePayrollAccountActiveTest extends TestCase
{
    public function test_inactive_account_is_rejected_and_csrf_token_is_cleared(): void
    {
        $accounts = Mockery::mock(PayrollAccountStatus::class);
        $accounts->shouldReceive('isActive')->once()->with(123)->andReturn(false);
        Cache::shouldReceive('forget')->once()->with('csrf:123');

        $request = Request::create('/api/v1/auth/me', 'GET');
        $request->setUserResolver(fn () => (object) ['id' => 123]);

        $response = (new EnsurePayrollAccountActive($accounts))->handle(
            $request,
            fn () => new Response('allowed', 200),
        );

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('This account is inactive.', $response->getData(true)['message']);
    }

    public function test_active_account_continues_to_the_next_middleware(): void
    {
        $accounts = Mockery::mock(PayrollAccountStatus::class);
        $accounts->shouldReceive('isActive')->once()->with(123)->andReturn(true);

        $request = Request::create('/api/v1/auth/me', 'GET');
        $request->setUserResolver(fn () => (object) ['id' => 123]);

        $response = (new EnsurePayrollAccountActive($accounts))->handle(
            $request,
            fn () => new Response('allowed', 200),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('allowed', $response->getContent());
    }

    public function test_request_without_an_authenticated_user_is_rejected(): void
    {
        $accounts = Mockery::mock(PayrollAccountStatus::class);
        $accounts->shouldNotReceive('isActive');

        $request = Request::create('/api/v1/auth/me', 'GET');

        $response = (new EnsurePayrollAccountActive($accounts))->handle(
            $request,
            fn () => new Response('allowed', 200),
        );

        $this->assertSame(403, $response->getStatusCode());
    }
}
