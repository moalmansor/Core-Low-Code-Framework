<?php

declare(strict_types=1);

use App\Modules\Documents\Http\Controllers\DocumentTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    // Document templates (§4.17).
    Route::get('/documents/templates', [DocumentTemplateController::class, 'index']);
    Route::post('/documents/templates', [DocumentTemplateController::class, 'store']);
    Route::post('/documents/templates/preview', [DocumentTemplateController::class, 'preview'])->middleware('throttle:60,1');
    Route::post('/documents/uploads', [DocumentTemplateController::class, 'upload'])->middleware('throttle:30,1');
    Route::get('/documents/placeholders', [DocumentTemplateController::class, 'placeholders']);
    Route::get('/documents/templates/{template}', [DocumentTemplateController::class, 'show'])->whereUuid('template');
    Route::put('/documents/templates/{template}', [DocumentTemplateController::class, 'update'])->whereUuid('template');
    Route::delete('/documents/templates/{template}', [DocumentTemplateController::class, 'destroy'])->whereUuid('template');
    Route::post('/documents/templates/{template}/generate', [DocumentTemplateController::class, 'generate'])->whereUuid('template')->middleware('throttle:20,1');
});
