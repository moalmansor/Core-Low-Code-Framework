<?php

declare(strict_types=1);

use App\Modules\Records\Http\Controllers\CommentController;
use App\Modules\Records\Http\Controllers\FileController;
use App\Modules\Records\Http\Controllers\RecordController;
use App\Modules\Records\Http\Controllers\RecordExchangeController;
use App\Modules\Records\Http\Controllers\SubformController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/r/{form}/definition', [RecordController::class, 'definition']);
    Route::get('/r/{form}/options/{field}', [RecordController::class, 'options'])->where('field', '[a-z][a-z0-9_]{0,47}');
    Route::post('/r/{form}/validate-field', [RecordController::class, 'validateField']);
    // Import and export (§4.15): background jobs with progress, cancel and error reports.
    Route::post('/r/{form}/exports', [RecordExchangeController::class, 'export'])->middleware('throttle:30,1');
    Route::get('/exports/{job}', [RecordExchangeController::class, 'exportStatus'])->whereUuid('job');
    Route::post('/exports/{job}/cancel', [RecordExchangeController::class, 'cancelExport'])->whereUuid('job');
    Route::get('/r/{form}/import/template', [RecordExchangeController::class, 'template']);
    Route::post('/r/{form}/imports/inspect', [RecordExchangeController::class, 'inspect'])->middleware('throttle:20,1');
    Route::post('/r/{form}/imports/preview', [RecordExchangeController::class, 'preview'])->middleware('throttle:60,1');
    Route::post('/r/{form}/imports', [RecordExchangeController::class, 'start'])->middleware('throttle:30,1');
    Route::get('/r/{form}/imports', [RecordExchangeController::class, 'imports']);
    Route::get('/r/{form}/import-mappings', [RecordExchangeController::class, 'mappingsIndex']);
    Route::delete('/import-mappings/{mapping}', [RecordExchangeController::class, 'deleteMapping'])->whereUuid('mapping');
    Route::get('/imports/{job}', [RecordExchangeController::class, 'importStatus'])->whereUuid('job');
    Route::post('/imports/{job}/cancel', [RecordExchangeController::class, 'cancelImport'])->whereUuid('job');
    Route::get('/imports/{job}/report', [RecordExchangeController::class, 'importReport'])->whereUuid('job');
    Route::post('/{kind}/{job}/retry', [RecordExchangeController::class, 'retry'])->whereIn('kind', ['imports', 'exports'])->whereUuid('job');
    Route::get('/r/{form}', [RecordController::class, 'index']);
    Route::post('/r/{form}', [RecordController::class, 'store']);
    Route::get('/r/{form}/{record}', [RecordController::class, 'show'])->whereUuid('record');
    Route::patch('/r/{form}/{record}', [RecordController::class, 'update'])->whereUuid('record');
    Route::delete('/r/{form}/{record}', [RecordController::class, 'destroy'])->whereUuid('record');
    Route::post('/r/{form}/{record}/restore', [RecordController::class, 'restore'])->whereUuid('record');
    Route::get('/r/{form}/{record}/history', [RecordController::class, 'history'])->whereUuid('record');
    Route::get('/r/{form}/{record}/subforms/{group}', [SubformController::class, 'index'])->whereUuid('record')->where('group', '[a-z][a-z0-9_]{0,47}');
    Route::post('/r/{form}/{record}/subforms/{group}', [SubformController::class, 'store'])->whereUuid('record')->where('group', '[a-z][a-z0-9_]{0,47}');
    Route::get('/r/{form}/{record}/comments', [CommentController::class, 'index'])->whereUuid('record');
    Route::post('/r/{form}/{record}/comments', [CommentController::class, 'store'])->whereUuid('record');
    Route::delete('/r/{form}/{record}/comments/{comment}', [CommentController::class, 'destroy'])->whereUuid('record')->whereUuid('comment');

    Route::post('/files', [FileController::class, 'upload'])->middleware('throttle:60,1');
    Route::get('/files/{file}/url', [FileController::class, 'url'])->whereUuid('file');
});

Route::get('/files/download/{file}', [FileController::class, 'download'])->whereUuid('file')->name('files.download')->middleware('throttle:120,1');
