<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class Idempotency
{
    /**
     * Replay cached responses for repeated requests that carry the same
     * Idempotency-Key, so double submissions do not create duplicates.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');

        if ($key === '' || ! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $user = $request->user();
        $scope = $user !== null ? 'u'.$user->id : 'ip'.$request->ip();

        $cacheKey = 'idempotency:'.$scope.':'.$key;
        $lockKey = $cacheKey.':lock';

        $bodyHash = hash('sha256', $request->getContent());

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            if (($cached['body_hash'] ?? null) !== $bodyHash) {
                return response()->json([
                    'message' => 'Idempotency key reused with a different request body.',
                ], 409);
            }

            $response = response($cached['body'] ?? '', (int) ($cached['status'] ?? 200));

            foreach (($cached['headers'] ?? []) as $name => $value) {
                $response->headers->set($name, $value);
            }

            $response->headers->set('Idempotency-Replayed', 'true');

            return $response;
        }

        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            usleep(200000);

            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                if (($cached['body_hash'] ?? null) !== $bodyHash) {
                    return response()->json([
                        'message' => 'Idempotency key reused with a different request body.',
                    ], 409);
                }

                $response = response($cached['body'] ?? '', (int) ($cached['status'] ?? 200));

                foreach (($cached['headers'] ?? []) as $name => $value) {
                    $response->headers->set($name, $value);
                }

                $response->headers->set('Idempotency-Replayed', 'true');

                return $response;
            }

            return response()->json(['message' => 'Request in progress.'], 409);
        }

        try {
            $response = $next($request);

            if ($response->getStatusCode() < 500) {
                Cache::put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(),
                    'headers' => [
                        'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
                    ],
                    'body_hash' => $bodyHash,
                ], now()->addHours(24));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
