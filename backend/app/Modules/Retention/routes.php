<?php

declare(strict_types=1);

use App\Modules\Retention\Http\Controllers\LegalHoldController;
use App\Modules\Retention\Http\Controllers\PersonalDataController;
use App\Modules\Retention\Http\Controllers\RetentionController;
use Illuminate\Support\Facades\Route;

// Retention, legal holds, personal data and storage quotas (§4.26); each controller checks its own permission.
Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/retention/options', [RetentionController::class, 'options']);
    Route::get('/retention/policies', [RetentionController::class, 'index']);
    Route::post('/retention/policies', [RetentionController::class, 'store']);
    Route::get('/retention/policies/{policy}', [RetentionController::class, 'show'])->whereUuid('policy');
    Route::put('/retention/policies/{policy}', [RetentionController::class, 'update'])->whereUuid('policy');
    Route::delete('/retention/policies/{policy}', [RetentionController::class, 'destroy'])->whereUuid('policy');
    Route::post('/retention/policies/{policy}/run', [RetentionController::class, 'run'])->whereUuid('policy')->middleware('throttle:10,1');
    Route::get('/retention/runs', [RetentionController::class, 'runs']);
    Route::get('/retention/runs/{run}/export', [RetentionController::class, 'runExport'])->whereUuid('run');
    Route::get('/retention/archive', [RetentionController::class, 'archive']);
    Route::post('/retention/archive/{id}/restore', [RetentionController::class, 'restore'])->whereNumber('id')->middleware('throttle:30,1');
    Route::get('/retention/storage', [RetentionController::class, 'storage']);
    Route::get('/retention/quotas', [RetentionController::class, 'quotas']);
    Route::put('/retention/quotas', [RetentionController::class, 'saveQuotas']);
    Route::get('/retention/usage', [RetentionController::class, 'usage']);

    Route::get('/legal-holds', [LegalHoldController::class, 'index']);
    Route::get('/legal-holds/forms', [LegalHoldController::class, 'forms']);
    Route::post('/legal-holds', [LegalHoldController::class, 'store']);
    Route::post('/legal-holds/{hold}/lift', [LegalHoldController::class, 'lift'])->whereUuid('hold');

    Route::get('/personal-data-requests', [PersonalDataController::class, 'index']);
    Route::post('/personal-data-requests', [PersonalDataController::class, 'store'])->middleware('throttle:30,1');
    Route::get('/personal-data-requests/{request}', [PersonalDataController::class, 'show'])->whereUuid('request');
    Route::post('/personal-data-requests/{request}/locate', [PersonalDataController::class, 'locate'])->whereUuid('request');
    Route::post('/personal-data-requests/{request}/execute', [PersonalDataController::class, 'execute'])->whereUuid('request')->middleware('throttle:10,1');
    Route::post('/personal-data-requests/{request}/reject', [PersonalDataController::class, 'reject'])->whereUuid('request');
    Route::get('/personal-data-requests/{request}/file', [PersonalDataController::class, 'file'])->whereUuid('request');
});
