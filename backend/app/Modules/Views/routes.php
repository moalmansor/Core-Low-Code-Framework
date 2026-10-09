<?php

declare(strict_types=1);

use App\Modules\Views\Http\Controllers\BulkController;
use App\Modules\Views\Http\Controllers\RecordPagesController;
use App\Modules\Views\Http\Controllers\SavedViewController;
use App\Modules\Views\Http\Controllers\ViewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/forms/{form}/views', [ViewController::class, 'show']);
    Route::put('/forms/{form}/views', [ViewController::class, 'update']);
    Route::get('/r/{form}/saved-views', [SavedViewController::class, 'index']);
    Route::post('/r/{form}/saved-views', [SavedViewController::class, 'store']);
    Route::patch('/r/{form}/saved-views/{saved}', [SavedViewController::class, 'update'])->whereUuid('saved');
    Route::delete('/r/{form}/saved-views/{saved}', [SavedViewController::class, 'destroy'])->whereUuid('saved');
    Route::post('/r/{form}/bulk-delete', [BulkController::class, 'delete']);
    Route::post('/r/{form}/bulk-restore', [BulkController::class, 'restore']);
    Route::get('/forms/{form}/view-panels', [RecordPagesController::class, 'panelsDocument']);
    Route::put('/forms/{form}/view-panels', [RecordPagesController::class, 'savePanels']);
    Route::get('/forms/{form}/reference-previews', [RecordPagesController::class, 'previewsDocument']);
    Route::put('/forms/{form}/reference-previews', [RecordPagesController::class, 'savePreviews']);
    Route::get('/forms/{form}/print-layouts', [RecordPagesController::class, 'layoutsDocument']);
    Route::put('/forms/{form}/print-layouts', [RecordPagesController::class, 'saveLayouts']);
    Route::get('/r/{form}/{record}/panels', [RecordPagesController::class, 'recordPanels'])->whereUuid('record');
    Route::get('/r/{form}/{record}/attachments', [RecordPagesController::class, 'attachments'])->whereUuid('record');
    Route::get('/r/{form}/{record}/print', [RecordPagesController::class, 'print'])->whereUuid('record')->middleware('throttle:30,1');
    Route::get('/r/{form}/preview/{field}/{value}', [RecordPagesController::class, 'preview'])->where('field', '[a-z][a-z0-9_]{0,47}')->whereUuid('value');
});
