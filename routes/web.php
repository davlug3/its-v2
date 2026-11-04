<?php

use Illuminate\Support\Facades\Route;

/*
 * The Vue SPA owns every non-API path.
 * API routes live in routes/api.php under the /api prefix.
 */
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api(?:/|$)).*$')
    ->name('spa');
