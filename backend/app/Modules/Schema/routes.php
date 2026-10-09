<?php

declare(strict_types=1);

use App\Modules\Schema\Http\Controllers\SchemaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/schema/tables', [SchemaController::class, 'tables']);
    Route::get('/schema/tables/{name}', [SchemaController::class, 'table'])->where('name', '[A-Za-z0-9_]{1,60}');
    Route::get('/schema/bindable-tables', [SchemaController::class, 'bindable']);
    Route::get('/schema/erd', [SchemaController::class, 'erd']);
    Route::post('/schema/reconcile', [SchemaController::class, 'reconcile']);
    Route::get('/schema/reconciliation-reports', [SchemaController::class, 'reports']);
    Route::get('/migration-plans', [SchemaController::class, 'plans']);
    Route::get('/migration-plans/{plan}', [SchemaController::class, 'plan']);
    Route::post('/migration-plans/{plan}/{action}', [SchemaController::class, 'repair'])->whereIn('action', ['retry', 'reverse', 'reconcile-step', 'restore-snapshot']);
});
