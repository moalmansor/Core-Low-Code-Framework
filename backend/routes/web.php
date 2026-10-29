<?php

declare(strict_types=1);

use App\Modules\Extensions\Http\Controllers\ExtensionRuntimeController;
use App\Modules\Identity\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

// Single sign-on round trip (browser redirects, so web routes with the session).
Route::middleware('throttle:login')->group(function (): void {
    Route::get('/auth/sso/{key}/redirect', [SsoController::class, 'redirect'])->where('key', '[a-z0-9_-]{1,64}');
    Route::get('/auth/sso/{key}/callback', [SsoController::class, 'callback'])->where('key', '[a-z0-9_-]{1,64}');
});

// Client extensions' sandboxed frame, its runtime and the deployed builds (ADR-0051): no sign-in needed.
Route::get('/ext/frame', [ExtensionRuntimeController::class, 'frame']);
Route::get('/ext/vue.js', [ExtensionRuntimeController::class, 'vue']);
Route::get('/ext/runtime.js', [ExtensionRuntimeController::class, 'runtime']);
Route::get('/ext/b/{version}.js', [ExtensionRuntimeController::class, 'bundle'])->whereUuid('version');

// The single-page application. Every other GET path that is not an API call
// renders the shell; the client router decides what to show.
Route::view('/{any?}', 'app')->where('any', '^(?!api/|sanctum/|horizon|up$|build/|ext/).*$')->name('spa');
