<?php

declare(strict_types=1);

use App\Modules\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->prefix('audit')->group(function (): void {
    Route::get('/', [AuditLogController::class, 'index']);
    Route::get('/export', [AuditLogController::class, 'export']);
    Route::post('/verify-chain', [AuditLogController::class, 'verify']);
    Route::get('/{id}', [AuditLogController::class, 'show'])->whereNumber('id');
});
