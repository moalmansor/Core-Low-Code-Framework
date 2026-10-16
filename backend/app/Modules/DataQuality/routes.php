<?php

declare(strict_types=1);

use App\Modules\DataQuality\Http\Controllers\DataQualityController;
use App\Modules\DataQuality\Http\Controllers\MergeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    // Duplicate rules and sweeps (§4.35).
    Route::get('/forms/{form}/duplicate-rules', [DataQualityController::class, 'rules']);
    Route::put('/forms/{form}/duplicate-rules', [DataQualityController::class, 'saveRules']);
    Route::post('/duplicate-rules/{rule}/sweep', [DataQualityController::class, 'sweep'])->whereUuid('rule')->middleware('throttle:10,1');
    Route::get('/data-operations/{operation}', [DataQualityController::class, 'operation'])->whereUuid('operation');
    // Merging duplicates.
    Route::post('/r/{form}/merge/preview', [MergeController::class, 'preview']);
    Route::post('/r/{form}/merge', [MergeController::class, 'merge'])->middleware('throttle:30,1');
    // Data repair.
    Route::get('/forms/{form}/repair', [DataQualityController::class, 'repairState']);
    Route::post('/forms/{form}/repair', [DataQualityController::class, 'scan'])->middleware('throttle:10,1');
    Route::post('/forms/{form}/repair/clear-links', [DataQualityController::class, 'clearLinks'])->middleware('throttle:20,1');
});
