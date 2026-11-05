<?php

namespace App\Http\Middleware;

use App\Models\UserProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAcl
{
    /**
     * Require the authenticated user to have at least the given ACL level.
     */
    public function handle(Request $request, Closure $next, int $minimum): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $profile = UserProfile::where('user_id', $user->id)->first();
        $acl = $profile !== null ? (int) $profile->acl_id : 1;

        if ($acl < $minimum) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
