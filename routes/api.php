<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Setup\CategoryController;
use App\Http\Controllers\Api\Setup\CustomerController;
use App\Http\Controllers\Api\Setup\MacroController;
use App\Http\Controllers\Api\Setup\QueueController;
use App\Http\Controllers\Api\Setup\SlaPolicyController;
use App\Http\Controllers\Api\Setup\TeamController;
use App\Http\Controllers\Api\Setup\UserController;
use App\Http\Controllers\Api\SupportAttachmentController;
use App\Http\Controllers\Api\SupportReferenceDataController;
use App\Http\Controllers\Api\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('api.token')->prefix('setup')->group(function (): void {
    Route::apiResource('users', UserController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('teams', TeamController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('queues', QueueController::class);
    Route::apiResource('sla-policies', SlaPolicyController::class);
    Route::apiResource('macros', MacroController::class);
});

Route::get('support/attachments', [SupportAttachmentController::class, 'index']);
Route::post('support/attachments/upload', [SupportAttachmentController::class, 'upload']);
Route::get('support/reference-data', [SupportReferenceDataController::class, 'index']);
Route::get('support/teams', [SupportReferenceDataController::class, 'teams']);
Route::get('support/categories', [SupportReferenceDataController::class, 'categories']);
Route::get('support/agents', [SupportReferenceDataController::class, 'agents']);

Route::prefix('support/tickets')->group(function (): void {
    Route::get('/', [SupportTicketController::class, 'index']);
    Route::post('/', [SupportTicketController::class, 'store']);
    Route::get('/{id}', [SupportTicketController::class, 'show']);
    Route::get('/{id}/linked-tasks', [SupportTicketController::class, 'linkedTasks']);
    Route::get('/{id}/attachments', [SupportTicketController::class, 'attachments']);
    Route::post('/bulk/assign', [SupportTicketController::class, 'bulkAssign']);
    Route::post('/bulk/status', [SupportTicketController::class, 'bulkStatus']);
    Route::post('/bulk/priority', [SupportTicketController::class, 'bulkPriority']);
    Route::post('/{id}/reply', [SupportTicketController::class, 'reply']);
    Route::post('/{id}/forward', [SupportTicketController::class, 'forward']);
});
