<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [LoginController::class, 'login']);
Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('auth:api');
Route::get('/auth/me', [LoginController::class, 'me'])->middleware('auth:api');
Route::middleware(['auth:api', 'csrf', 'idempotency'])->group(function () {
    Route::get('/about', [AboutController::class, 'show']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::post('/tickets/end-user', [TicketController::class, 'endUserStore']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/by-subordinates', [TicketController::class, 'bySubordinates']);

    Route::get('/categories', [CategoryController::class, 'list']);
    Route::get('/tickets/pending-review-count', [TicketController::class, 'pendingReviewCount']);

    Route::get('/monitoring/tickets', [TicketController::class, 'monitoring']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::middleware('acl:3')->group(function () {
        Route::get('/reports/tickets', [ReportController::class, 'tickets']);
        Route::get('/reports/monthly', [ReportController::class, 'monthly']);
    });
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::put('/tickets/{id}', [TicketController::class, 'update']);
    Route::patch('/tickets/{id}/assign', [TicketController::class, 'assign']);
    Route::post('/tickets/{id}/close', [TicketController::class, 'close']);
    Route::get('/tickets/{id}/log', [TicketController::class, 'logs']);
    Route::post('/tickets/{id}/comment', [TicketController::class, 'comment']);

    Route::get('/tickets/{id}/attachments', [TicketController::class, 'attachments']);
    Route::post('/tickets/{id}/attachments', [TicketController::class, 'uploadAttachment']);
    Route::get('/tickets/{id}/attachments/{attachmentId}/download', [TicketController::class, 'downloadAttachment']);
    Route::delete('/tickets/{id}/attachments/{attachmentId}', [TicketController::class, 'deleteAttachment']);

    Route::post('/tickets/{id}/approve', [TicketController::class, 'approve']);
    Route::post('/tickets/{id}/disapprove', [TicketController::class, 'disapprove']);
    Route::post('/tickets/{id}/undo-approve', [TicketController::class, 'undoApprove']);
    Route::post('/tickets/batch-approve', [TicketController::class, 'batchApprove']);
    Route::post('/tickets/batch-disapprove', [TicketController::class, 'batchDisapprove']);

    Route::prefix('admin')->group(function () {
        Route::middleware('acl:3')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/users/options', [UserController::class, 'options']);
            Route::patch('/users/{id}/access', [UserController::class, 'updateAccess']);
            Route::patch('/users/{id}/supervisor', [UserController::class, 'updateSupervisor']);
            Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
            Route::get('/users/{id}/supervisor-tree', [UserController::class, 'supervisorTree']);
            Route::delete('/users/{id}', [UserController::class, 'softDelete']);

            Route::get('/categories', [CategoryController::class, 'index']);
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
        });

        Route::middleware('acl:9')->group(function () {
            Route::get('/permissions', [PermissionController::class, 'index']);
        });
    });
});
