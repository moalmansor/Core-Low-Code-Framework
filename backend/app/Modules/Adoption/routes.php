<?php

declare(strict_types=1);

use App\Modules\Adoption\Http\UsageController;
use Illuminate\Support\Facades\Route;

// Usage insight (specification §4.37, ADR-0066).
Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::post('/usage', [UsageController::class, 'store'])->middleware('throttle:60,1');
    Route::get('/usage/forms', [UsageController::class, 'forms']);
    Route::get('/usage/forms/{form}', [UsageController::class, 'form'])->whereUuid('form');
});
