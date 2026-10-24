<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    // Converts Word documents to PDF (§4.17): the LibreOffice program, run headless and offline.
    'documents' => [
        'converter' => env('LCF_DOCUMENT_CONVERTER', 'soffice'),
    ],

    // Developer extensions (§4.19, ADR-0051): where their versioned files live, and the
    // esbuild program that compiles client extensions from TypeScript (run offline, no bundling).
    'extensions' => [
        'path' => env('LCF_EXTENSIONS_PATH', storage_path('app/extensions')),
        // Without LCF_ESBUILD: the esbuild the frontend installed (a development checkout), else one on the PATH.
        'esbuild' => env('LCF_ESBUILD') ?: (glob(base_path('../frontend/node_modules/@esbuild/*/bin/esbuild')) ?: ['esbuild'])[0],
        'vue' => env('LCF_EXTENSIONS_VUE', base_path('../frontend/node_modules/vue/dist/vue.runtime.global.prod.js')),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
