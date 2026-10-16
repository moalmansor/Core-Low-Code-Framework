<?php

declare(strict_types=1);

return [
    'approve_first' => 'Only an approved version can be deployed.',
    'checksum_mismatch' => 'The files of :key version :version on disk no longer match what was approved. It will not run until it is approved again.',
    'disabled_first' => 'This extension is turned off. Turn it back on first.',
    'failed' => 'A custom rule of this form failed, so nothing was saved. The administrators have been told.',
    'forbidden' => 'You do not have the permission this endpoint needs.',
    'form_required' => 'Choose the form this extension belongs to.',
    'form_unpublished' => 'Publish the form first: a test runs against its published definition.',
    'no_compiler' => 'Client extensions cannot be compiled on this server: the esbuild program is not installed (LCF_ESBUILD).',
    'no_version' => 'This extension has no code yet.',
    'not_a_hook' => 'The class Hook must implement ServerHook.',
    'not_an_endpoint' => 'The class Endpoint must implement ApiEndpoint.',
    'not_deployed' => 'This endpoint is not deployed.',
    'not_pending' => 'This version is not waiting for approval.',
    'own_code' => 'Someone other than its author and its last editor must approve it.',
    'permission_required' => 'Choose the permission a caller must hold.',
    'point_required' => 'Choose when the hook runs.',
    'test_first' => 'Run a test of this exact code and pass it before submitting.',
    'timed_out' => 'It ran longer than its limit of :ms ms and was stopped.',
    'was_deployed' => 'A version of this extension has been deployed. Turn it off instead of deleting it, so its history is kept.',
    'guard' => [
        'line' => 'Line :line: :message',
        'syntax' => 'This is not valid PHP: :error',
        'class_missing' => 'Define a class named :class that implements :contract.',
        'forbidden' => ':what is not allowed in an extension.',
        'dynamic_call' => 'Calling a variable, a string or an expression is not allowed; call a function by its name.',
        'dynamic_new' => 'Creating an object from a variable is not allowed.',
        'function' => 'The function :name is not allowed in an extension.',
        'class' => 'The class :name is not allowed in an extension.',
        'callback' => ':name must be given an inline function (fn or function), not a name.',
    ],
];
