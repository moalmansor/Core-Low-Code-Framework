<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\SsoController;
use App\Modules\Identity\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/sso/providers', [SsoController::class, 'providers']);

// Own profile and sessions: reachable before 2FA enrollment so the SPA can guide it.
Route::middleware(['auth:sanctum', 'lcf.session'])->prefix('me')->group(function (): void {
    Route::get('/', [MeController::class, 'show']);
    Route::patch('/', [MeController::class, 'update']);
    Route::get('/sessions', [MeController::class, 'sessions']);
    Route::delete('/sessions/{handle}', [MeController::class, 'revokeSession'])->where('handle', '[a-f0-9]{64}');
    Route::delete('/sessions', [MeController::class, 'revokeOtherSessions']);
});

Route::middleware(['auth:sanctum', 'lcf.secure'])->prefix('users')->group(function (): void {
    Route::get('/', [UserController::class, 'index']);
    Route::post('/', [UserController::class, 'store']);
    Route::get('/{user}', [UserController::class, 'show']);
    Route::patch('/{user}', [UserController::class, 'update']);
    Route::delete('/{user}', [UserController::class, 'destroy']);
    Route::post('/{user}/status', [UserController::class, 'setStatus']);
    Route::post('/{user}/unlock', [UserController::class, 'unlock']);
    Route::post('/{user}/reset-2fa', [UserController::class, 'resetTwoFactor']);
    Route::post('/{user}/password-link', [UserController::class, 'sendPasswordLink']);
    Route::get('/{user}/sessions', [UserController::class, 'sessions']);
    Route::delete('/{user}/sessions', [UserController::class, 'revokeSessions']);
});
