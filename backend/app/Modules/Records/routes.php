<?php

declare(strict_types=1);

use App\Modules\Records\Http\Controllers\CommentController;
use App\Modules\Records\Http\Controllers\FileController;
use App\Modules\Records\Http\Controllers\RecordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/r/{form}/definition', [RecordController::class, 'definition']);
    Route::get('/r/{form}/options/{field}', [RecordController::class, 'options'])->where('field', '[a-z][a-z0-9_]{0,47}');
    Route::post('/r/{form}/validate-field', [RecordController::class, 'validateField']);
    Route::get('/r/{form}', [RecordController::class, 'index']);
    Route::post('/r/{form}', [RecordController::class, 'store']);
    Route::get('/r/{form}/{record}', [RecordController::class, 'show'])->whereUuid('record');
    Route::patch('/r/{form}/{record}', [RecordController::class, 'update'])->whereUuid('record');
    Route::delete('/r/{form}/{record}', [RecordController::class, 'destroy'])->whereUuid('record');
    Route::post('/r/{form}/{record}/restore', [RecordController::class, 'restore'])->whereUuid('record');
    Route::get('/r/{form}/{record}/history', [RecordController::class, 'history'])->whereUuid('record');
    Route::get('/r/{form}/{record}/comments', [CommentController::class, 'index'])->whereUuid('record');
    Route::post('/r/{form}/{record}/comments', [CommentController::class, 'store'])->whereUuid('record');
    Route::delete('/r/{form}/{record}/comments/{comment}', [CommentController::class, 'destroy'])->whereUuid('record')->whereUuid('comment');

    Route::post('/files', [FileController::class, 'upload'])->middleware('throttle:60,1');
    Route::get('/files/{file}/url', [FileController::class, 'url'])->whereUuid('file');
});

Route::get('/files/download/{file}', [FileController::class, 'download'])->whereUuid('file')->name('files.download')->middleware('throttle:120,1');
