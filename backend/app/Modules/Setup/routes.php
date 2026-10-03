<?php

declare(strict_types=1);

use App\Modules\Setup\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::prefix('setup')->middleware('throttle:setup')->group(function (): void {
    Route::get('/status', [SetupController::class, 'status'])->middleware('lcf.setup:open');
    Route::post('/token', [SetupController::class, 'verifyToken'])->middleware('lcf.setup:open');
    Route::middleware('lcf.setup:token')->group(function (): void {
        Route::post('/mail-test', [SetupController::class, 'mailTest']);
        Route::post('/two-factor', [SetupController::class, 'twoFactor']);
        Route::post('/branding/{kind}', [SetupController::class, 'uploadBranding'])->whereIn('kind', ['logo', 'favicon']);
        Route::post('/complete', [SetupController::class, 'complete']);
    });
});
