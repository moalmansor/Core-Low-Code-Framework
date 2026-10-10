<?php

declare(strict_types=1);

use App\Modules\Notifications\HtmlPreviews;
use App\Modules\Notifications\Http\Controllers\EmailTemplateController;
use App\Modules\Notifications\Http\Controllers\InboxController;
use App\Modules\Notifications\Http\Controllers\NotificationRuleController;
use Illuminate\Http\Request;
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

// Rendered email HTML for a sandboxed frame, behind a short-lived signed link.
Route::get('/html-previews/{preview}', function (string $preview, Request $request, HtmlPreviews $previews) {
    abort_unless($request->hasValidSignature(), 403);
    $html = $previews->get($preview);
    abort_if($html === null, 404);

    return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', HtmlPreviews::HEADER => '1', 'Cache-Control' => 'no-store, private']);
})->whereUuid('preview')->name('html-previews.show')->middleware('throttle:120,1');
