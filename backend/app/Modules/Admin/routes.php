<?php

declare(strict_types=1);

use App\Modules\Admin\Http\Controllers\AdminConsoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->prefix('admin')->group(function (): void {
    Route::get('/console', [AdminConsoleController::class, 'index']);
    Route::get('/health', [AdminConsoleController::class, 'health']);
});
