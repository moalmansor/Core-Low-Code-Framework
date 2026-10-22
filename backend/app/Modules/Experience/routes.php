<?php

declare(strict_types=1);

use App\Modules\Experience\Http\Controllers\AnnouncementController;
use App\Modules\Experience\Http\Controllers\HomeScreenController;
use App\Modules\Experience\Http\Controllers\MaintenanceController;
use App\Modules\Experience\Http\Controllers\PageController;
use App\Modules\Experience\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Pages, home screens, navigation extras, announcements, search and themes (specification §4.27–4.29, ADR-0054).
Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/pages', [PageController::class, 'index']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::get('/pages/{page}', [PageController::class, 'show'])->whereUuid('page');
    Route::put('/pages/{page}', [PageController::class, 'update'])->whereUuid('page');
    Route::delete('/pages/{page}', [PageController::class, 'destroy'])->whereUuid('page');
    Route::get('/pages/{page}/view', [PageController::class, 'view'])->whereUuid('page');

    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::get('/announcements/active', [AnnouncementController::class, 'active'])->middleware('throttle:120,1');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->whereUuid('announcement');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->whereUuid('announcement');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->whereUuid('announcement');
    Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->whereUuid('announcement');

    Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:60,1');
    Route::get('/search-configs', [SearchController::class, 'index']);
    Route::put('/search-configs/{form}', [SearchController::class, 'update'])->whereUuid('form');

    Route::get('/maintenance', [MaintenanceController::class, 'show']);
    Route::put('/maintenance', [MaintenanceController::class, 'update']);

    Route::get('/home', [HomeScreenController::class, 'home']);
    Route::get('/home/activity', [HomeScreenController::class, 'activity'])->middleware('throttle:60,1');
    Route::get('/home-screens', [HomeScreenController::class, 'index']);
    Route::post('/home-screens', [HomeScreenController::class, 'store']);
    Route::get('/home-screens/{screen}', [HomeScreenController::class, 'show'])->whereUuid('screen');
    Route::put('/home-screens/{screen}', [HomeScreenController::class, 'update'])->whereUuid('screen');
    Route::delete('/home-screens/{screen}', [HomeScreenController::class, 'destroy'])->whereUuid('screen');
});
