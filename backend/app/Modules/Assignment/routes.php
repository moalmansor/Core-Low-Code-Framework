<?php

declare(strict_types=1);

use App\Modules\Assignment\Http\Controllers\AssignmentController;
use App\Modules\Assignment\Http\Controllers\DelegationController;
use App\Modules\Assignment\Http\Controllers\MyWorkController;
use App\Modules\Assignment\Http\Controllers\QueueController;
use App\Modules\Assignment\Http\Controllers\SubjectOptionsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/my-work', [MyWorkController::class, 'index']);
    Route::get('/subject-options', SubjectOptionsController::class)->middleware('throttle:120,1');
    Route::post('/r/{form}/{record}/assign', [AssignmentController::class, 'assign'])->whereUuid('record');
    Route::post('/r/{form}/{record}/claim', [AssignmentController::class, 'claim'])->whereUuid('record');
    Route::post('/r/{form}/{record}/release', [AssignmentController::class, 'release'])->whereUuid('record');
    Route::post('/approvals/{approval}/decide', [AssignmentController::class, 'decide'])->whereUuid('approval');
    Route::get('/forms/{form}/assignment-rules', [AssignmentController::class, 'rules']);
    Route::put('/forms/{form}/assignment-rules', [AssignmentController::class, 'saveRules']);
    Route::get('/queues', [QueueController::class, 'index']);
    Route::post('/queues', [QueueController::class, 'store']);
    Route::patch('/queues/{queue}', [QueueController::class, 'update'])->whereUuid('queue');
    Route::get('/delegations', [DelegationController::class, 'index']);
    Route::post('/delegations', [DelegationController::class, 'store']);
    Route::delete('/delegations/{delegation}', [DelegationController::class, 'destroy'])->whereUuid('delegation');
});
