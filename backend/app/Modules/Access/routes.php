<?php

declare(strict_types=1);

use App\Modules\Access\Http\Controllers\AccessToolsController;
use App\Modules\Access\Http\Controllers\PermissionController;
use App\Modules\Access\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/role-options', [RoleController::class, 'options']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);
    Route::patch('/roles/{role}', [RoleController::class, 'update']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    Route::post('/roles/{role}/copy-permissions', [RoleController::class, 'copyPermissions']);

    Route::get('/permissions', [PermissionController::class, 'catalog']);
    Route::get('/permission-assignments', [PermissionController::class, 'forSubject']);
    Route::put('/permission-assignments', [PermissionController::class, 'update']);

    Route::get('/access/view-as/{user}', [AccessToolsController::class, 'viewAs']);
    Route::get('/access/explain', [AccessToolsController::class, 'explain']);
    Route::get('/access/export', [AccessToolsController::class, 'export']);
    Route::post('/access/import', [AccessToolsController::class, 'import']);
});
