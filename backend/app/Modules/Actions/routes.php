<?php

declare(strict_types=1);

use App\Modules\Actions\Http\Controllers\ActionConfigController;
use App\Modules\Actions\Http\Controllers\ActionRunController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    // Configuration (§4.15).
    Route::get('/forms/{form}/actions', [ActionConfigController::class, 'show']);
    Route::put('/forms/{form}/actions', [ActionConfigController::class, 'update']);
    // Running.
    Route::get('/r/{form}/actions', [ActionRunController::class, 'index']);
    Route::post('/r/{form}/actions/{action}/prepare', [ActionRunController::class, 'prepare'])->whereUuid('action');
    Route::post('/r/{form}/actions/{action}/dry-run', [ActionRunController::class, 'dryRun'])->whereUuid('action')->middleware('throttle:20,1');
    Route::post('/r/{form}/actions/{action}/run', [ActionRunController::class, 'run'])->whereUuid('action')->middleware('throttle:30,1');
    Route::get('/action-runs/{run}', [ActionRunController::class, 'status'])->whereUuid('run');
    Route::post('/action-runs/{run}/cancel', [ActionRunController::class, 'cancel'])->whereUuid('run');
});
