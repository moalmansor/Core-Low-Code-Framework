<?php

declare(strict_types=1);

use App\Modules\Scheduling\Http\Controllers\ScheduledTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/scheduled-tasks', [ScheduledTaskController::class, 'index']);
});
