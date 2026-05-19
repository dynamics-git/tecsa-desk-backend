<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthPasswordController;
use App\Http\Controllers\Api\Setup\CategoryController;
use App\Http\Controllers\Api\Setup\CustomerController;
use App\Http\Controllers\Api\Setup\MacroController;
use App\Http\Controllers\Api\Setup\MailConfigController;
use App\Http\Controllers\Api\Setup\QueueController;
use App\Http\Controllers\Api\Setup\SlaPolicyController;
use App\Http\Controllers\Api\Setup\SupportPermissionRoleController;
use App\Http\Controllers\Api\Setup\SupportUserScopeController;
use App\Http\Controllers\Api\Setup\TeamController;
use App\Http\Controllers\Api\Setup\UserController;
use App\Http\Controllers\Api\Setup\CustomerUserAccessController;
use App\Http\Controllers\Api\SupportAttachmentController;
use App\Http\Controllers\Api\SupportReferenceDataController;
use App\Http\Controllers\Api\Support\ActivityReadController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\UserSecuritySettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/password-policy', [AuthPasswordController::class, 'passwordPolicy']);
    Route::post('/password/forgot', [AuthPasswordController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('/password/reset', [AuthPasswordController::class, 'resetPassword'])->middleware('throttle:10,1');
});

Route::middleware('api.token')->prefix('auth')->group(function (): void {
    Route::patch('/password-policy', [AuthPasswordController::class, 'updatePasswordPolicy']);
    Route::post('/password/generate', [AuthPasswordController::class, 'generatePassword']);
    Route::post('/password/set-by-admin', [AuthPasswordController::class, 'setByAdmin']);
    Route::post('/password/change', [AuthPasswordController::class, 'changePassword']);
});

Route::middleware('api.token')->prefix('setup')->group(function (): void {
    Route::get('mail-config', [MailConfigController::class, 'show']);
    Route::put('mail-config', [MailConfigController::class, 'update']);
    Route::post('mail-config/test-connection', [MailConfigController::class, 'testConnection']);

    Route::apiResource('users', UserController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('teams', TeamController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('queues', QueueController::class);
    Route::apiResource('sla-policies', SlaPolicyController::class);
    Route::apiResource('macros', MacroController::class);
    Route::apiResource('permission-roles', SupportPermissionRoleController::class);
    Route::apiResource('customer-user-access', CustomerUserAccessController::class);
    Route::apiResource('support-user-scope', SupportUserScopeController::class);
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
    Route::get('/{id}/activities', [SupportTicketController::class, 'activities']);
    Route::post('/{id}/activities/mark-read', [ActivityReadController::class, 'markRead'])->middleware('api.token');
    Route::post('/{id}/activities/mark-read-all', [ActivityReadController::class, 'markReadAll'])->middleware('api.token');
    Route::get('/{id}/linked-tasks', [SupportTicketController::class, 'linkedTasks']);
    Route::get('/{id}/attachments', [SupportTicketController::class, 'attachments']);
    Route::post('/{id}/attachments/download-all', [SupportTicketController::class, 'downloadAllAttachments']);
    Route::post('/{id}/email-send', [SupportTicketController::class, 'emailSend']);
    Route::post('/{id}/notifications/dispatch', [SupportTicketController::class, 'dispatchNotifications']);
    Route::post('/bulk/assign', [SupportTicketController::class, 'bulkAssign']);
    Route::post('/bulk/status', [SupportTicketController::class, 'bulkStatus']);
    Route::post('/bulk/priority', [SupportTicketController::class, 'bulkPriority']);
    Route::post('/{id}/reply', [SupportTicketController::class, 'reply']);
    Route::post('/{id}/forward', [SupportTicketController::class, 'forward']);
});

Route::middleware('signed')->group(function (): void {
    Route::get('support/attachments/{id}/preview', [SupportAttachmentController::class, 'preview'])->name('support.attachments.preview');
    Route::get('support/attachments/{id}/download', [SupportAttachmentController::class, 'download'])->name('support.attachments.download');
    Route::get('support/attachments/bundles/{bundleId}/download', [SupportAttachmentController::class, 'bundleDownload'])->name('support.attachments.bundle-download');
});

Route::middleware('api.token')->group(function (): void {
    Route::get('users/{id}/security-settings', [UserSecuritySettingsController::class, 'show']);
    Route::patch('users/{id}/security-settings', [UserSecuritySettingsController::class, 'update']);
});
