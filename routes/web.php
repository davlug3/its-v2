<?php

use Illuminate\Support\Facades\Route;

/*
 * The Vue SPA owns every non-API path.
 */
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api(?:/|$)).*$')
    ->name('spa');
