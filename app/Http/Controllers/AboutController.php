<?php

namespace App\Http\Controllers;

use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $version = 'unknown';
        $versionFile = storage_path('app/version.txt');
        if (is_readable($versionFile)) {
            $version = trim((string) file_get_contents($versionFile));
        } elseif (config('about.app_version')) {
            $version = (string) config('about.app_version');
        }

        $profile = UserProfile::where('user_id', $request->user()->id)->first();
        $acl = $profile !== null ? (int) $profile->acl_id : 1;
        $isAdmin = $acl >= 9;

        $data = [
            'app_name' => config('app.name'),
            'app_description' => config('app.description'),
            'app_url' => config('app.url'),
            'app_timezone' => config('app.timezone'),
            'app_version' => $version,
            'app_env' => $isAdmin ? config('app.env') : '',
            'app_debug' => $isAdmin && (bool) config('app.debug'),
            'php_version' => $isAdmin ? PHP_VERSION : '',
            'laravel_version' => $isAdmin ? app()->version() : '',
            'database_url' => null,
            'payroll_database' => null,
        ];

        $showDbUrl = $isAdmin && (bool) config('about.show_database_url', false);

        if ($showDbUrl) {
            $data['database_url'] = sprintf(
                '%s://%s@%s:%s/%s',
                config('database.connections.mysql.driver', 'mysql'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.host'),
                config('database.connections.mysql.port'),
                config('database.connections.mysql.database'),
            );
            $data['payroll_database'] = sprintf(
                '%s://%s@%s:%s/%s',
                config('database.connections.payroll.driver', 'mysql'),
                config('database.connections.payroll.username'),
                config('database.connections.payroll.host'),
                config('database.connections.payroll.port'),
                config('database.connections.payroll.database'),
            );
        }

        return response()->json($data);
    }
}
