<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\DataApiController;
use App\Modules\Platform\Http\Controllers\InboundEndpointController;
use App\Modules\Platform\Http\Controllers\TokenController;
use App\Modules\Platform\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Signed-in people manage tokens, webhooks and inbound endpoints.
Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/me/tokens', [TokenController::class, 'mine']);
    Route::post('/me/tokens', [TokenController::class, 'store'])->middleware('throttle:20,1');
    Route::delete('/me/tokens/{token}', [TokenController::class, 'revokeMine'])->whereUuid('token');
    Route::get('/api-tokens', [TokenController::class, 'all']);
    Route::delete('/api-tokens/{token}', [TokenController::class, 'revoke'])->whereUuid('token');

    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::get('/webhooks/{webhook}', [WebhookController::class, 'show'])->whereUuid('webhook');
    Route::patch('/webhooks/{webhook}', [WebhookController::class, 'update'])->whereUuid('webhook');
    Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy'])->whereUuid('webhook');
    Route::post('/webhooks/{webhook}/secret', [WebhookController::class, 'rotateSecret'])->whereUuid('webhook');
    Route::post('/webhooks/{webhook}/test', [WebhookController::class, 'test'])->whereUuid('webhook')->middleware('throttle:20,1');
    Route::get('/webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries'])->whereUuid('webhook');
    Route::get('/webhook-deliveries/{delivery}', [WebhookController::class, 'delivery'])->whereUuid('delivery');
    Route::post('/webhook-deliveries/{delivery}/retry', [WebhookController::class, 'retry'])->whereUuid('delivery');

    Route::get('/inbound-endpoints', [InboundEndpointController::class, 'index']);
    Route::post('/inbound-endpoints', [InboundEndpointController::class, 'store']);
    Route::get('/inbound-endpoints/{endpoint}', [InboundEndpointController::class, 'show'])->whereUuid('endpoint');
    Route::patch('/inbound-endpoints/{endpoint}', [InboundEndpointController::class, 'update'])->whereUuid('endpoint');
    Route::delete('/inbound-endpoints/{endpoint}', [InboundEndpointController::class, 'destroy'])->whereUuid('endpoint');
    Route::post('/inbound-endpoints/{endpoint}/secret', [InboundEndpointController::class, 'rotateSecret'])->whereUuid('endpoint');
    Route::get('/inbound-endpoints/{endpoint}/deliveries', [InboundEndpointController::class, 'deliveries'])->whereUuid('endpoint');
});

// The data API: personal API tokens only.
Route::middleware('lcf.token')->group(function (): void {
    Route::get('/openapi.json', [DataApiController::class, 'openapi']);
    Route::get('/data/{form}', [DataApiController::class, 'index'])->where('form', '[a-z][a-z0-9_]{0,47}');
    Route::post('/data/{form}', [DataApiController::class, 'store'])->where('form', '[a-z][a-z0-9_]{0,47}');
    Route::get('/data/{form}/{record}', [DataApiController::class, 'show'])->where('form', '[a-z][a-z0-9_]{0,47}')->whereUuid('record');
    Route::patch('/data/{form}/{record}', [DataApiController::class, 'update'])->where('form', '[a-z][a-z0-9_]{0,47}')->whereUuid('record');
    Route::delete('/data/{form}/{record}', [DataApiController::class, 'destroy'])->where('form', '[a-z][a-z0-9_]{0,47}')->whereUuid('record');
    Route::post('/data/{form}/{record}/transitions/{transition}', [DataApiController::class, 'transition'])
        ->where('form', '[a-z][a-z0-9_]{0,47}')->whereUuid('record')->where('transition', '[a-z][a-z0-9_]{0,47}');
});
