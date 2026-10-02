<?php

declare(strict_types=1);

use App\Modules\Monitoring\Http\Controllers\ErrorController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->prefix('errors')->group(function (): void {
    Route::get('/groups', [ErrorController::class, 'groups']);
    Route::get('/groups/{id}', [ErrorController::class, 'show'])->whereNumber('id');
    Route::patch('/groups/{id}', [ErrorController::class, 'update'])->whereNumber('id');
    Route::get('/reference/{code}', [ErrorController::class, 'byReference'])->where('code', 'E-[A-Z0-9]{10}');
});
