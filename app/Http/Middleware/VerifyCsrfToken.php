<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyCsrfToken
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $token = $request->header('X-CSRF-TOKEN');

        if ($token === null || $token === '') {
            return response()->json(['message' => 'CSRF token missing.'], 419);
        }

        $stored = Cache::get('csrf:'.(int) $user->id);

        if ($stored === null || ! hash_equals($stored, $token)) {
            return response()->json(['message' => 'CSRF token mismatch.'], 419);
        }

        return $next($request);
    }
}
