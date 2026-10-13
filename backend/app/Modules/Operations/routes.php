<?php

declare(strict_types=1);

use App\Modules\Operations\Http\Controllers\OperationsController;
use Illuminate\Support\Facades\Route;

// The Operations Center (§4.2, ADR-0045); every route needs "Manage operations".
Route::middleware(['auth:sanctum', 'lcf.secure'])->prefix('ops')->group(function (): void {
    Route::get('/health', [OperationsController::class, 'health']);
    Route::get('/emails', [OperationsController::class, 'emails']);
    Route::get('/emails/{email}/html', [OperationsController::class, 'emailHtml'])->whereUuid('email');
    Route::post('/emails/resend', [OperationsController::class, 'resendEmails'])->middleware('throttle:30,1');
    Route::post('/emails/cancel', [OperationsController::class, 'cancelEmails']);
    Route::get('/submissions', [OperationsController::class, 'submissions']);
    Route::get('/submissions/{submission}', [OperationsController::class, 'submission'])->whereUuid('submission');
    Route::patch('/submissions/{submission}/payload', [OperationsController::class, 'editSubmission'])->whereUuid('submission');
    Route::post('/submissions/retry', [OperationsController::class, 'retrySubmissions'])->middleware('throttle:30,1');
    Route::post('/submissions/{submission}/discard', [OperationsController::class, 'discardSubmission'])->whereUuid('submission');
    Route::get('/jobs', [OperationsController::class, 'jobs']);
    Route::get('/jobs/{kind}/{id}', [OperationsController::class, 'job'])->whereNumber('id');
    Route::post('/jobs/{kind}/{id}/retry', [OperationsController::class, 'retryJob'])->whereNumber('id')->middleware('throttle:30,1');
    Route::post('/jobs/{kind}/{id}/discard', [OperationsController::class, 'dismissJob'])->whereNumber('id');
    Route::get('/alert-rules', [OperationsController::class, 'alertRules']);
    Route::put('/alert-rules', [OperationsController::class, 'saveAlertRules']);
});
