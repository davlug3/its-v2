<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PisUser;
use App\Models\UserProfile;
use App\Services\PisSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class LoginController extends Controller
{
    public function __construct(private PisSyncService $sync) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower($credentials['username']).':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many login attempts. Try again later.',
                'retry_after' => $seconds,
            ], 429);
        }

        $user = PisUser::where('username', $credentials['username'])->first();

        if ($user === null) {
            RateLimiter::hit($key, 900);

            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        $valid = $this->verifyPassword($user, $credentials['password']);

        if (! $valid) {
            RateLimiter::hit($key, 900);

            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        RateLimiter::clear($key);

        // Ensure the helpdesk profile exists and is up to date.
        $this->sync->sync((int) $user->id);

        $profile = UserProfile::firstOrNew(['user_id' => $user->id]);
        if (! $profile->exists) {
            $profile->acl_id = 1;
            $profile->group_id = 1;
            $profile->fpc = 1;
            $profile->save();
        }

        // Rehash legacy MD5 to Argon2id on first successful login.
        if (empty($profile->password_v2)) {
            $profile->password_v2 = Hash::make($credentials['password']);
            $profile->save();
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            JWTAuth::parseToken();
            JWTAuth::invalidate();
        } catch (\Throwable $e) {
            // Token missing or invalid. Nothing to invalidate.
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(): JsonResponse
    {
        $user = auth('api')->user();

        $payroll = DB::connection('payroll')
            ->table('payroll')
            ->where('id', $user->id)
            ->first();

        $fullname = '';
        if ($payroll !== null) {
            $fullname = trim($payroll->name.' '.$payroll->mname.' '.$payroll->lname);
        }

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'fullname' => $fullname,
        ]);
    }

    private function verifyPassword(PisUser $user, string $password): bool
    {
        $profile = UserProfile::where('user_id', $user->id)->first();

        if ($profile !== null && ! empty($profile->password_v2)) {
            return Hash::check($password, $profile->password_v2);
        }

        // Legacy MD5.
        return hash_equals((string) $user->password, md5($password));
    }
}
