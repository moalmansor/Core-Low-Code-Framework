<?php

declare(strict_types=1);

use App\Modules\Core\Http\Controllers\EgressAllowlistController;
use App\Modules\Core\Http\Controllers\FeatureFlagController;
use App\Modules\Core\Http\Controllers\LocaleController;
use App\Modules\Core\Http\Controllers\PublicController;
use App\Modules\Core\Http\Controllers\SettingsController;
use App\Modules\Core\Http\Controllers\TranslationController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:public')->group(function (): void {
    Route::get('/bootstrap', [PublicController::class, 'bootstrap']);
    Route::get('/i18n/{locale}', [PublicController::class, 'catalog'])->where('locale', '[A-Za-z-]{2,10}');
    Route::get('/branding/{kind}', [PublicController::class, 'brandingAsset']);
});

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/settings/{group}', [SettingsController::class, 'show']);
    Route::patch('/settings/{group}', [SettingsController::class, 'update']);
    Route::post('/settings/branding/{kind}', [SettingsController::class, 'uploadBrandingAsset']);
    Route::post('/settings/mail/test', [SettingsController::class, 'testMail']);

    // Feature flags (specification §4.36, ADR-0062).
    Route::get('/feature-flags', [FeatureFlagController::class, 'index']);
    Route::post('/feature-flags', [FeatureFlagController::class, 'store']);
    Route::put('/feature-flags/{flag}', [FeatureFlagController::class, 'update'])->whereUuid('flag');
    Route::delete('/feature-flags/{flag}', [FeatureFlagController::class, 'destroy'])->whereUuid('flag');

    Route::get('/egress-allowlist', [EgressAllowlistController::class, 'index']);
    Route::post('/egress-allowlist', [EgressAllowlistController::class, 'store']);
    Route::patch('/egress-allowlist/{entry}', [EgressAllowlistController::class, 'update']);
    Route::delete('/egress-allowlist/{entry}', [EgressAllowlistController::class, 'destroy']);

    Route::get('/locales', [LocaleController::class, 'index']);
    Route::post('/locales', [LocaleController::class, 'store']);
    Route::patch('/locales/{locale:code}', [LocaleController::class, 'update']);

    Route::get('/translations/types', [TranslationController::class, 'types']);
    Route::get('/translations', [TranslationController::class, 'index']);
    Route::put('/translations', [TranslationController::class, 'update']);
    Route::get('/translations/export', [TranslationController::class, 'export']);
    Route::post('/translations/import', [TranslationController::class, 'import']);
});
