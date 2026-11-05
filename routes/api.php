<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserController;
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
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::put('/tickets/{id}', [TicketController::class, 'update']);
    Route::post('/tickets/{id}/close', [TicketController::class, 'close']);

    Route::prefix('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{id}/access', [UserController::class, 'updateAccess']);
        Route::patch('/users/{id}/supervisor', [UserController::class, 'updateSupervisor']);
        Route::patch('/users/{id}/fpc', [UserController::class, 'toggleFpc']);
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
        Route::get('/users/{id}/supervisor-tree', [UserController::class, 'supervisorTree']);
        Route::delete('/users/{id}', [UserController::class, 'softDelete']);

        Route::get('/categories', [CategoryController::class, 'index']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        Route::get('/permissions', [PermissionController::class, 'index']);
    });
});
