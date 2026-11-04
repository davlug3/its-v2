<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('auth:api');
Route::get('/auth/me', [LoginController::class, 'me'])->middleware('auth:api');
