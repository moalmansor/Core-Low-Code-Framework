<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\DepartmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/departments/tree', [DepartmentController::class, 'tree']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::patch('/departments/{department}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
});
