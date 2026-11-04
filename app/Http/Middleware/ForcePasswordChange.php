<?php

namespace App\Http\Middleware;

use App\Models\UserProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $profile = UserProfile::where('user_id', $user->id)->first();

        // fpc = 1 means the user must change their password.
        if ($profile === null || (int) $profile->fpc !== 1) {
            return $next($request);
        }

        $allowed = [
            'api/v1/auth/me',
            'api/v1/auth/logout',
            'api/v1/auth/change-password',
        ];

        $path = trim($request->path(), '/');

        foreach ($allowed as $allowedPath) {
            if ($path === $allowedPath) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Password change required.',
            'redirect' => '/user_options',
        ], 403);
    }
}
