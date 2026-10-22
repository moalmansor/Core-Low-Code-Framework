<?php

declare(strict_types=1);

use App\Modules\Reports\Http\Controllers\DashboardController;
use App\Modules\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Reports and dashboards (specification §4.18, ADR-0053).
Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/insights', [ReportController::class, 'mine']);

    Route::get('/reports', [ReportController::class, 'index']);
    Route::post('/reports', [ReportController::class, 'store']);
    Route::get('/reports/fields', [ReportController::class, 'fields']);
    Route::post('/reports/preview', [ReportController::class, 'preview'])->middleware('throttle:60,1');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->whereUuid('report');
    Route::put('/reports/{report}', [ReportController::class, 'update'])->whereUuid('report');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->whereUuid('report');
    Route::get('/reports/{report}/data', [ReportController::class, 'data'])->whereUuid('report')->middleware('throttle:120,1');
    Route::post('/reports/{report}/export', [ReportController::class, 'export'])->whereUuid('report')->middleware('throttle:20,1');

    Route::get('/dashboards', [DashboardController::class, 'index']);
    Route::post('/dashboards', [DashboardController::class, 'store']);
    Route::get('/dashboards/{dashboard}', [DashboardController::class, 'show'])->whereUuid('dashboard');
    Route::put('/dashboards/{dashboard}', [DashboardController::class, 'update'])->whereUuid('dashboard');
    Route::delete('/dashboards/{dashboard}', [DashboardController::class, 'destroy'])->whereUuid('dashboard');
    Route::get('/dashboards/{dashboard}/view', [DashboardController::class, 'view'])->whereUuid('dashboard')->middleware('throttle:60,1');
    Route::post('/dashboards/{dashboard}/export', [DashboardController::class, 'export'])->whereUuid('dashboard')->middleware('throttle:20,1');
});
