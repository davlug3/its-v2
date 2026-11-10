<?php

namespace App\Http\Middleware;

use App\Services\PayrollAccountStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePayrollAccountActive
{
    public function __construct(private PayrollAccountStatus $accounts) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $this->accounts->isActive((int) $user->id)) {
            if ($user !== null) {
                Cache::forget('csrf:'.(int) $user->id);

                // Revoke this JWT as well, so re-enabling the account does not
                // silently restore a token issued before it was disabled.
                try {
                    JWTAuth::parseToken();
                    JWTAuth::invalidate();
                } catch (\Throwable $e) {
                    // The request is denied even if token blacklisting fails.
                }
            }

            return response()->json(['message' => 'This account is inactive.'], 403);
        }

        return $next($request);
    }
}
