<?php

use App\Http\Middleware\EnsureAcl;
use App\Http\Middleware\EnsurePayrollAccountActive;
use App\Http\Middleware\Idempotency;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecureHeaders::class);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return '/';
        });

        $middleware->alias([
            'acl' => EnsureAcl::class,
            'csrf' => VerifyCsrfToken::class,
            'idempotency' => Idempotency::class,
            'payroll.active' => EnsurePayrollAccountActive::class,
            'payroll.active' => EnsurePayrollAccountActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Never leak stack traces or SQL from unexpected API failures.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException
                || $e instanceof HttpExceptionInterface
                || $e instanceof AuthenticationException) {
                return null;
            }

            report($e);

            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        });
    })->create();
