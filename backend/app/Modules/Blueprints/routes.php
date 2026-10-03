<?php

declare(strict_types=1);

use App\Modules\Blueprints\Http\Controllers\BlueprintController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/blueprints', [BlueprintController::class, 'index']);
    Route::post('/blueprints', [BlueprintController::class, 'store']);
    Route::post('/blueprints/import', [BlueprintController::class, 'import'])->middleware('throttle:10,1');
    Route::get('/blueprints/{blueprint}', [BlueprintController::class, 'show']);
    Route::patch('/blueprints/{blueprint}', [BlueprintController::class, 'update']);
    Route::delete('/blueprints/{blueprint}', [BlueprintController::class, 'destroy']);
    Route::post('/blueprints/{blueprint}/versions', [BlueprintController::class, 'addVersion']);
    Route::post('/blueprints/{blueprint}/instantiate', [BlueprintController::class, 'instantiate']);
    Route::get('/blueprints/{blueprint}/propagation-preview', [BlueprintController::class, 'preview']);
    Route::post('/blueprints/{blueprint}/propagate', [BlueprintController::class, 'propagate']);
    Route::get('/blueprints/{blueprint}/export', [BlueprintController::class, 'export']);
    Route::post('/blueprint-instances/{instance}/detach', [BlueprintController::class, 'detach']);
});
