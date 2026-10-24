<?php

declare(strict_types=1);

use App\Modules\Extensions\Http\Controllers\ExtensionController;
use App\Modules\Extensions\Http\Controllers\ExtensionRuntimeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/extensions', [ExtensionController::class, 'index']);
    Route::get('/extensions/meta', [ExtensionController::class, 'meta']);
    Route::post('/extensions', [ExtensionController::class, 'store']);
    Route::get('/extensions/{extension}', [ExtensionController::class, 'show'])->whereUuid('extension');
    Route::put('/extensions/{extension}', [ExtensionController::class, 'update'])->whereUuid('extension');
    Route::delete('/extensions/{extension}', [ExtensionController::class, 'destroy'])->whereUuid('extension');
    Route::post('/extensions/{extension}/test', [ExtensionController::class, 'test'])->whereUuid('extension')->middleware('throttle:30,1');
    Route::post('/extensions/{extension}/submit', [ExtensionController::class, 'submit'])->whereUuid('extension');
    Route::post('/extensions/{extension}/approve', [ExtensionController::class, 'approve'])->whereUuid('extension');
    Route::post('/extensions/{extension}/reject', [ExtensionController::class, 'reject'])->whereUuid('extension');
    Route::post('/extensions/{extension}/deploy', [ExtensionController::class, 'deploy'])->whereUuid('extension');
    Route::post('/extensions/{extension}/disable', [ExtensionController::class, 'disable'])->whereUuid('extension');
    Route::get('/extensions/{extension}/versions/{version}', [ExtensionController::class, 'version'])->whereUuid('extension')->whereUuid('version');

    // Deployed extensions at work.
    Route::get('/extension-components', [ExtensionRuntimeController::class, 'components']);
    Route::get('/forms/{form}/client-extensions', [ExtensionRuntimeController::class, 'forForm']);
    Route::match(['get', 'post'], '/ext-api/{key}', [ExtensionRuntimeController::class, 'call'])->where('key', '[a-z][a-z0-9_]{1,47}')->middleware('throttle:120,1');
});
