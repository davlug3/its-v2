<?php

use App\Http\Controllers\Compat\LegacyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Legacy compatibility: ?p=page_name
 * Handled at the root path because /index.php is intercepted by
 * the web server (PHP-FPM / built-in server).
 */
Route::get('/', function (Request $request) {
    if ($request->has('p')) {
        return app(LegacyController::class)->handle($request);
    }

    return view('app');
})->name('home');

/*
 * The Vue SPA owns every non-API path.
 */
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api(?:/|$)).*$')
    ->name('spa');
