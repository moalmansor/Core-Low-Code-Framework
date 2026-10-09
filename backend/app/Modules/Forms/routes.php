<?php

declare(strict_types=1);

use App\Modules\Forms\Http\Controllers\ApplicationController;
use App\Modules\Forms\Http\Controllers\ExpressionController;
use App\Modules\Forms\Http\Controllers\FieldTemplateController;
use App\Modules\Forms\Http\Controllers\FieldTypeController;
use App\Modules\Forms\Http\Controllers\FormController;
use App\Modules\Forms\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::get('/applications/{application}', [ApplicationController::class, 'show']);
    Route::patch('/applications/{application}', [ApplicationController::class, 'update']);
    Route::post('/applications/{application}/maintenance', [ApplicationController::class, 'maintenance']);
    Route::post('/applications/{application}/{action}', [ApplicationController::class, 'status'])->whereIn('action', ['archive', 'retire', 'activate']);
    Route::get('/applications/{application}/menu', [MenuController::class, 'show']);
    Route::put('/applications/{application}/menu', [MenuController::class, 'update']);
    Route::get('/navigation', [MenuController::class, 'navigation']);

    Route::get('/field-types', [FieldTypeController::class, 'index']);
    Route::get('/field-templates', [FieldTemplateController::class, 'index']);
    Route::post('/field-templates', [FieldTemplateController::class, 'store']);
    Route::patch('/field-templates/{template}', [FieldTemplateController::class, 'update']);
    Route::post('/field-templates/{template}/used', [FieldTemplateController::class, 'used']);
    Route::delete('/field-templates/{template}', [FieldTemplateController::class, 'destroy']);

    Route::get('/forms', [FormController::class, 'index']);
    Route::get('/form-options', [FormController::class, 'options']);
    Route::post('/forms', [FormController::class, 'store']);
    Route::get('/forms/{form}', [FormController::class, 'show']);
    Route::delete('/forms/{form}', [FormController::class, 'destroy']);
    Route::get('/forms/{form}/draft', [FormController::class, 'draft']);
    Route::put('/forms/{form}/draft', [FormController::class, 'saveDraft']);
    Route::post('/forms/{form}/draft/validate', [FormController::class, 'validateDraft']);
    Route::get('/forms/{form}/preview', [FormController::class, 'preview']);
    Route::post('/forms/{form}/impact', [FormController::class, 'impact']);
    Route::post('/forms/{form}/publish', [FormController::class, 'publish']);
    Route::get('/forms/{form}/versions', [FormController::class, 'versions']);
    Route::get('/forms/{form}/versions/{number}', [FormController::class, 'version'])->whereNumber('number');
    Route::get('/forms/{form}/versions/{from}/diff/{to}', [FormController::class, 'diff'])->where(['from' => '[0-9]+|draft', 'to' => '[0-9]+|draft']);
    Route::post('/forms/{form}/versions/{number}/rollback', [FormController::class, 'rollback'])->whereNumber('number');
    Route::post('/forms/{form}/duplicate', [FormController::class, 'duplicate']);
    Route::post('/forms/{form}/{action}', [FormController::class, 'changeState'])->whereIn('action', ['unpublish', 'archive', 'republish']);

    Route::post('/expressions/parse', [ExpressionController::class, 'parse']);
    Route::post('/expressions/check', [ExpressionController::class, 'check']);
    Route::post('/expressions/evaluate', [ExpressionController::class, 'evaluate']);
});
