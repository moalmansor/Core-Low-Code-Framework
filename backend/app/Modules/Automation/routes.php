<?php

declare(strict_types=1);

use App\Modules\Automation\Http\Controllers\AutomationController;
use App\Modules\Automation\Http\Controllers\AutomationRunController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    // Building (§4.31).
    Route::get('/automations', [AutomationController::class, 'all']);
    Route::get('/forms/{form}/automations', [AutomationController::class, 'index']);
    Route::post('/forms/{form}/automations', [AutomationController::class, 'store']);
    Route::get('/automations/{automation}', [AutomationController::class, 'show'])->whereUuid('automation');
    Route::put('/automations/{automation}', [AutomationController::class, 'update'])->whereUuid('automation');
    Route::delete('/automations/{automation}', [AutomationController::class, 'destroy'])->whereUuid('automation');
    Route::post('/automations/{automation}/enable', [AutomationController::class, 'enable'])->whereUuid('automation');
    Route::post('/automations/{automation}/test', [AutomationController::class, 'test'])->whereUuid('automation')->middleware('throttle:30,1');
    // Running and history.
    Route::post('/automations/{automation}/run', [AutomationController::class, 'run'])->whereUuid('automation')->middleware('throttle:10,1');
    Route::get('/automations/{automation}/runs', [AutomationRunController::class, 'index'])->whereUuid('automation');
    Route::get('/automation-runs/{run}', [AutomationRunController::class, 'show'])->whereUuid('run');
    Route::post('/automation-runs/{run}/confirm', [AutomationRunController::class, 'confirm'])->whereUuid('run');
    Route::post('/automation-runs/{run}/cancel', [AutomationRunController::class, 'cancel'])->whereUuid('run');
    Route::post('/automation-runs/{run}/retry', [AutomationRunController::class, 'retry'])->whereUuid('run');
});
