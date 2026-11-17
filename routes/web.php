<?php

use Illuminate\Support\Facades\Route;

/*
 * Retired legacy ?p= adapter (DECISIONS.md U9): any request carrying the
 * ?p= query parameter receives a 404. All navigation uses Vue Router.
 */
Route::get('/{any?}', function ($any = null) {
    if (request()->has('p')) {
        abort(404);
    }

    return view('app');
})
    ->where('any', '^(?!api(?:/|$)).*$')
    ->name('spa');
