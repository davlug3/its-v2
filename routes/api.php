<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('auth:api');
Route::get('/auth/me', [LoginController::class, 'me'])->middleware('auth:api');
Route::post('/auth/change-password', [LoginController::class, 'changePassword'])
    ->middleware(['auth:api', 'fpc', 'csrf']);

Route::middleware(['auth:api', 'fpc', 'csrf'])->group(function () {
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::post('/tickets/end-user', [TicketController::class, 'endUserStore']);
});
