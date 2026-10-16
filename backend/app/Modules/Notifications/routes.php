<?php

declare(strict_types=1);

use App\Modules\Notifications\Http\Controllers\EmailTemplateController;
use App\Modules\Notifications\Http\Controllers\InboxController;
use App\Modules\Notifications\Http\Controllers\NotificationRuleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    // The notifications center (§4.16).
    Route::get('/me/notifications', [InboxController::class, 'index']);
    Route::get('/me/notifications/count', [InboxController::class, 'count']);
    Route::post('/me/notifications/read-all', [InboxController::class, 'readAll']);
    Route::post('/me/notifications/{notification}/read', [InboxController::class, 'read'])->whereUuid('notification');

    // Email templates and notification rules.
    Route::get('/notifications/templates', [EmailTemplateController::class, 'index']);
    Route::post('/notifications/templates', [EmailTemplateController::class, 'store']);
    Route::post('/notifications/templates/preview', [EmailTemplateController::class, 'preview'])->middleware('throttle:60,1');
    Route::get('/notifications/placeholders', [EmailTemplateController::class, 'placeholders']);
    Route::get('/notifications/templates/{template}', [EmailTemplateController::class, 'show'])->whereUuid('template');
    Route::put('/notifications/templates/{template}', [EmailTemplateController::class, 'update'])->whereUuid('template');
    Route::delete('/notifications/templates/{template}', [EmailTemplateController::class, 'destroy'])->whereUuid('template');
    Route::post('/notifications/templates/{template}/test', [EmailTemplateController::class, 'test'])->whereUuid('template')->middleware('throttle:10,1');
    Route::get('/forms/{form}/notification-rules', [NotificationRuleController::class, 'show']);
    Route::put('/forms/{form}/notification-rules', [NotificationRuleController::class, 'save']);
});
