<?php

declare(strict_types=1);

use App\Modules\Experience\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Pages, home screens, navigation extras, announcements, search and themes (specification §4.27–4.29, ADR-0054).
Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/pages', [PageController::class, 'index']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::get('/pages/{page}', [PageController::class, 'show'])->whereUuid('page');
    Route::put('/pages/{page}', [PageController::class, 'update'])->whereUuid('page');
    Route::delete('/pages/{page}', [PageController::class, 'destroy'])->whereUuid('page');
    Route::get('/pages/{page}/view', [PageController::class, 'view'])->whereUuid('page');
});
