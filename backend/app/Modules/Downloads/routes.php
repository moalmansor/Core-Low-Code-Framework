<?php

declare(strict_types=1);

use App\Modules\Downloads\Http\Controllers\DownloadProfileController;
use App\Modules\Downloads\Http\Controllers\DownloadRunController;
use App\Modules\Downloads\Http\Controllers\DownloadScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    // Building profiles (§4.23).
    Route::get('/forms/{form}/download-profiles', [DownloadProfileController::class, 'index']);
    Route::post('/forms/{form}/download-profiles', [DownloadProfileController::class, 'store']);
    Route::get('/forms/{form}/download-tree', [DownloadProfileController::class, 'tree']);
    Route::post('/forms/{form}/download-preview', [DownloadProfileController::class, 'preview'])->middleware('throttle:30,1');
    Route::post('/forms/{form}/download-effective', [DownloadProfileController::class, 'effective']);
    Route::get('/download-profiles/{profile}', [DownloadProfileController::class, 'show'])->whereUuid('profile');
    Route::put('/download-profiles/{profile}', [DownloadProfileController::class, 'update'])->whereUuid('profile');
    Route::delete('/download-profiles/{profile}', [DownloadProfileController::class, 'destroy'])->whereUuid('profile');
    Route::post('/download-profiles/{profile}/duplicate', [DownloadProfileController::class, 'duplicate'])->whereUuid('profile');
    // Schedules.
    Route::get('/download-profiles/{profile}/schedules', [DownloadScheduleController::class, 'index'])->whereUuid('profile');
    Route::post('/download-profiles/{profile}/schedules', [DownloadScheduleController::class, 'store'])->whereUuid('profile');
    Route::put('/download-schedules/{schedule}', [DownloadScheduleController::class, 'update'])->whereUuid('schedule');
    Route::delete('/download-schedules/{schedule}', [DownloadScheduleController::class, 'destroy'])->whereUuid('schedule');
    // Running.
    Route::get('/r/{form}/downloads', [DownloadRunController::class, 'index']);
    Route::post('/r/{form}/downloads/{profile}', [DownloadRunController::class, 'run'])->whereUuid('profile')->middleware('throttle:20,1');
    Route::get('/download-jobs/{job}', [DownloadRunController::class, 'status'])->whereUuid('job');
    Route::post('/download-jobs/{job}/cancel', [DownloadRunController::class, 'cancel'])->whereUuid('job');
    Route::post('/download-jobs/{job}/retry', [DownloadRunController::class, 'retry'])->whereUuid('job');
});
