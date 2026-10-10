<?php

declare(strict_types=1);

use App\Modules\Workflow\Http\Controllers\RecordWorkflowController;
use App\Modules\Workflow\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/forms/{form}/workflow', [WorkflowController::class, 'show']);
    Route::put('/forms/{form}/workflow', [WorkflowController::class, 'update']);
    Route::get('/forms/{form}/workflow/status-mapping', [WorkflowController::class, 'mapping']);
    Route::post('/forms/{form}/workflow/status-mapping', [WorkflowController::class, 'chooseMapping']);
    Route::get('/forms/{form}/sla-rules', [WorkflowController::class, 'sla']);
    Route::put('/forms/{form}/sla-rules', [WorkflowController::class, 'updateSla']);

    Route::get('/r/{form}/{record}/workflow', [RecordWorkflowController::class, 'show'])->whereUuid('record');
    Route::get('/r/{form}/{record}/transitions', [RecordWorkflowController::class, 'transitions'])->whereUuid('record');
    Route::post('/r/{form}/{record}/transitions/{transition}', [RecordWorkflowController::class, 'perform'])->whereUuid('record')->whereUuid('transition');
    Route::get('/r/{form}/{record}/status-history', [RecordWorkflowController::class, 'history'])->whereUuid('record');
});
