<?php

declare(strict_types=1);

use App\Modules\External\Http\Controllers\ExternalAccountController;
use App\Modules\External\Http\Controllers\ExternalFormController;
use App\Modules\External\Http\Controllers\PortalController;
use App\Modules\External\Http\Controllers\PublicFormController;
use App\Modules\External\Http\Controllers\PublicLinkController;
use App\Modules\External\Http\Controllers\RecordSharingController;
use Illuminate\Support\Facades\Route;

// External access (§4.32, ADR-0058): administration.
Route::middleware(['auth:sanctum', 'lcf.secure'])->group(function (): void {
    Route::get('/external-forms', [ExternalFormController::class, 'index']);
    Route::get('/external-forms/fields', [ExternalFormController::class, 'fields']);
    Route::post('/external-forms', [ExternalFormController::class, 'store']);
    Route::get('/external-forms/{form}', [ExternalFormController::class, 'show'])->whereUuid('form');
    Route::put('/external-forms/{form}', [ExternalFormController::class, 'update'])->whereUuid('form');
    Route::delete('/external-forms/{form}', [ExternalFormController::class, 'destroy'])->whereUuid('form');

    Route::get('/external-accounts', [ExternalAccountController::class, 'index']);
    Route::post('/external-accounts/{account}/decision', [ExternalAccountController::class, 'decide'])->whereUuid('account');
    Route::put('/external-accounts/{account}/role', [ExternalAccountController::class, 'changeRole'])->whereUuid('account');
    Route::delete('/external-accounts/{account}', [ExternalAccountController::class, 'destroy'])->whereUuid('account');
    Route::get('/external-settings', [ExternalAccountController::class, 'settings']);
    Route::put('/external-settings', [ExternalAccountController::class, 'updateSettings']);
    Route::get('/external-roles', [ExternalAccountController::class, 'roles']);
    Route::post('/external-roles', [ExternalAccountController::class, 'storeRole']);
    Route::put('/external-roles/{role}', [ExternalAccountController::class, 'updateRole'])->whereUuid('role');
    Route::delete('/external-roles/{role}', [ExternalAccountController::class, 'destroyRole'])->whereUuid('role');

    // Links and signature requests sent from a record.
    Route::get('/r/{form}/{record}/sharing', [RecordSharingController::class, 'index'])->whereUuid('record');
    Route::post('/r/{form}/{record}/links', [RecordSharingController::class, 'storeLink'])->whereUuid('record')->middleware('throttle:30,1');
    Route::post('/r/{form}/{record}/signature-requests', [RecordSharingController::class, 'storeSignature'])->whereUuid('record')->middleware('throttle:20,1');
    Route::delete('/record-links/{link}', [RecordSharingController::class, 'revokeLink'])->whereUuid('link');
    Route::post('/signature-requests/{request}/cancel', [RecordSharingController::class, 'cancelSignature'])->whereUuid('request');
    Route::get('/signature-requests/{request}/signed', [RecordSharingController::class, 'signedFile'])->whereUuid('request');
});

// Visitors, external users and link recipients: no internal sign-in, a limit per address.
Route::prefix('x')->middleware('throttle:external')->group(function (): void {
    Route::get('/forms/{slug}', [PublicFormController::class, 'show'])->where('slug', '[a-z0-9-]{3,64}');
    Route::post('/forms/{slug}/unlock', [PublicFormController::class, 'unlock'])->where('slug', '[a-z0-9-]{3,64}');
    Route::get('/forms/{slug}/challenge', [PublicFormController::class, 'challenge'])->where('slug', '[a-z0-9-]{3,64}');
    Route::post('/forms/{slug}/code', [PublicFormController::class, 'code'])->where('slug', '[a-z0-9-]{3,64}');
    Route::post('/forms/{slug}', [PublicFormController::class, 'submit'])->where('slug', '[a-z0-9-]{3,64}');

    Route::get('/portal', [PortalController::class, 'show']);
    Route::post('/portal/register', [PortalController::class, 'register']);
    Route::post('/portal/verify', [PortalController::class, 'verify']);
    Route::post('/portal/resend', [PortalController::class, 'resend']);
    Route::post('/portal/login', [PortalController::class, 'login'])->middleware('throttle:login');
    Route::post('/portal/logout', [PortalController::class, 'logout']);
    Route::get('/portal/forms', [PortalController::class, 'forms']);
    Route::get('/portal/records', [PortalController::class, 'records']);
    Route::get('/portal/records/{slug}/{record}', [PortalController::class, 'record'])->where('slug', '[a-z0-9-]{3,64}')->whereUuid('record');

    Route::get('/links/{token}', [PublicLinkController::class, 'show'])->where('token', '[A-Za-z0-9_-]{43}');
    Route::post('/links/{token}', [PublicLinkController::class, 'perform'])->where('token', '[A-Za-z0-9_-]{43}');
    Route::get('/signatures/{token}', [PublicLinkController::class, 'signature'])->where('token', '[A-Za-z0-9_-]{43}');
    Route::get('/signatures/{token}/document', [PublicLinkController::class, 'document'])->where('token', '[A-Za-z0-9_-]{43}');
    Route::post('/signatures/{token}/sign', [PublicLinkController::class, 'sign'])->where('token', '[A-Za-z0-9_-]{43}');
    Route::post('/signatures/{token}/decline', [PublicLinkController::class, 'decline'])->where('token', '[A-Za-z0-9_-]{43}');
});
