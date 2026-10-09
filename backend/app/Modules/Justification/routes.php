<?php

declare(strict_types=1);

use App\Modules\Justification\Http\Controllers\JustificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/forms/{form}/justification-rules', [JustificationController::class, 'rules']);
    Route::put('/forms/{form}/justification-rules', [JustificationController::class, 'saveRules']);
    Route::get('/justification-reason-codes', [JustificationController::class, 'codes']);
    Route::post('/justification-reason-codes', [JustificationController::class, 'storeCode']);
    Route::patch('/justification-reason-codes/{code}', [JustificationController::class, 'updateCode'])->whereUuid('code');
    Route::get('/r/{form}/{record}/justifications', [JustificationController::class, 'forRecord'])->whereUuid('record');
});
