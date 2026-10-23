<?php

declare(strict_types=1);

use App\Modules\Experience\Http\Controllers\AnnouncementController;
use App\Modules\Experience\Http\Controllers\HelpContentController;
use App\Modules\Experience\Http\Controllers\HomeScreenController;
use App\Modules\Experience\Http\Controllers\MaintenanceController;
use App\Modules\Experience\Http\Controllers\PageController;
use App\Modules\Experience\Http\Controllers\SearchController;
use App\Modules\Experience\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

// Pages, home screens, navigation extras, announcements, help, search and themes (specification §4.27–4.29, §4.37, ADR-0054, ADR-0064).
Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/pages', [PageController::class, 'index']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::get('/pages/{page}', [PageController::class, 'show'])->whereUuid('page');
    Route::put('/pages/{page}', [PageController::class, 'update'])->whereUuid('page');
    Route::delete('/pages/{page}', [PageController::class, 'destroy'])->whereUuid('page');
    Route::get('/pages/{page}/view', [PageController::class, 'view'])->whereUuid('page');
    Route::post('/pages/{page}/announce', [PageController::class, 'announce'])->whereUuid('page')->middleware('throttle:10,1');

    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::get('/announcements/active', [AnnouncementController::class, 'active'])->middleware('throttle:120,1');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->whereUuid('announcement');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->whereUuid('announcement');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->whereUuid('announcement');
    Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->whereUuid('announcement');

    Route::get('/help-content', [HelpContentController::class, 'index']);
    Route::post('/help-content', [HelpContentController::class, 'store']);
    Route::get('/help-content/targets', [HelpContentController::class, 'targets']);
    Route::get('/help-content/{help}', [HelpContentController::class, 'show'])->whereUuid('help');
    Route::put('/help-content/{help}', [HelpContentController::class, 'update'])->whereUuid('help');
    Route::delete('/help-content/{help}', [HelpContentController::class, 'destroy'])->whereUuid('help');
    Route::get('/help', [HelpContentController::class, 'context'])->middleware('throttle:120,1');
    Route::get('/help/{help}', [HelpContentController::class, 'page'])->whereUuid('help');

    Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:60,1');
    Route::get('/search-configs', [SearchController::class, 'index']);
    Route::put('/search-configs/{form}', [SearchController::class, 'update'])->whereUuid('form');

    Route::get('/themes', [ThemeController::class, 'index']);
    Route::post('/themes', [ThemeController::class, 'store']);
    Route::get('/themes/presets', [ThemeController::class, 'presets']);
    Route::post('/themes/check', [ThemeController::class, 'check'])->middleware('throttle:120,1');
    Route::post('/themes/import', [ThemeController::class, 'import']);
    Route::delete('/themes/default', [ThemeController::class, 'clearDefault']);
    Route::get('/themes/{theme}', [ThemeController::class, 'show'])->whereUuid('theme');
    Route::put('/themes/{theme}', [ThemeController::class, 'update'])->whereUuid('theme');
    Route::delete('/themes/{theme}', [ThemeController::class, 'destroy'])->whereUuid('theme');
    Route::post('/themes/{theme}/default', [ThemeController::class, 'makeDefault'])->whereUuid('theme');
    Route::post('/themes/{theme}/reset', [ThemeController::class, 'reset'])->whereUuid('theme');
    Route::get('/themes/{theme}/export', [ThemeController::class, 'export'])->whereUuid('theme');
    Route::post('/themes/{theme}/assets/{kind}', [ThemeController::class, 'storeAsset'])->whereUuid('theme')->middleware('throttle:30,1');
    Route::delete('/themes/{theme}/assets/{kind}', [ThemeController::class, 'removeAsset'])->whereUuid('theme');

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

// Theme artwork and stylesheets are public: the sign-in page and emails show them (ADR-0055).
Route::middleware(['throttle:public'])->group(function (): void {
    Route::get('/theme-assets/{theme}/{kind}', [ThemeController::class, 'asset'])->whereUuid('theme');
    Route::get('/theme-css/{theme}', [ThemeController::class, 'css'])->where('theme', '[0-9a-fA-F-]{36}\\.css');
});
