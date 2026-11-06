<?php

namespace App\Http\Controllers;

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

        $data = [
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_url' => config('app.url'),
            'app_timezone' => config('app.timezone'),
            'app_debug' => (bool) config('app.debug'),
            'app_version' => $version,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];

        $showDbUrl = (bool) config('about.show_database_url', false);

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
        } else {
            $data['database_url'] = null;
            $data['payroll_database'] = null;
        }

        return response()->json($data);
    }
}
