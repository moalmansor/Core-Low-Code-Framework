<?php

declare(strict_types=1);

use App\Modules\Integrations\Http\Controllers\DataSourceController;
use Illuminate\Support\Facades\Route;

// External data sources and sync jobs (§4.33, ADR-0057).
Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/data-sources', [DataSourceController::class, 'index']);
    Route::get('/data-sources/options', [DataSourceController::class, 'options']);
    Route::post('/data-sources', [DataSourceController::class, 'store']);
    Route::get('/data-sources/{source}', [DataSourceController::class, 'show'])->whereUuid('source');
    Route::put('/data-sources/{source}', [DataSourceController::class, 'update'])->whereUuid('source');
    Route::delete('/data-sources/{source}', [DataSourceController::class, 'destroy'])->whereUuid('source');
    Route::post('/data-sources/{source}/test', [DataSourceController::class, 'test'])->whereUuid('source')->middleware('throttle:20,1');
});
