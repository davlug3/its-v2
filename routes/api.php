<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware(['auth:api', 'csrf']);
Route::get('/auth/me', [LoginController::class, 'me'])->middleware('auth:api');
Route::post('/auth/change-password', [LoginController::class, 'changePassword'])
    ->middleware(['auth:api', 'fpc', 'csrf']);
