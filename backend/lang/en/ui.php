<?php

// Server-side messages (validation errors, API errors, e-mails). Interface
// strings of the SPA live in resources/ui-strings/{locale}.json.
return [
    'access' => [
        'copy_same_role' => 'A role cannot copy its own permissions.',
        'lockout_guard' => 'This change would leave no active Super Admin who can manage permissions, so it was not applied.',
        'system_role_protected' => 'Core system roles cannot be deleted or have their key changed.',
    ],
    'audit' => [
        'export_too_large' => 'The export is too large. Narrow the filters and try again.',
    ],
    'auth' => [
        'external_account' => 'This account signs in through an external identity provider; its password is managed there.',
        'inactive' => 'This account is not active. Contact your administrator.',
        'locked' => 'Too many failed attempts. Try again in :minutes minute(s).',
        'password_expired' => 'Your password has expired. Choose a new one to continue.',
        'session_expired' => 'Your session has expired. Sign in again.',
        'setup_required' => 'The system has not been set up yet.',
        'two_factor_mandatory' => 'Two-factor authentication is mandatory for your role and cannot be turned off.',
        'two_factor_required' => 'Two-factor authentication is required for your role. Set it up to continue.',
        'unauthenticated' => 'Sign in to continue.',
    ],
    'departments' => [
        'cycle' => 'A department cannot be moved under itself or one of its sub-departments.',
        'has_children' => 'Move or archive the sub-departments first.',
        'has_members' => 'Move the members of this department first.',
    ],
    'errors' => [
        'session_required' => 'This request needs a browser session. Reload the page and try again.',
        'unexpected' => 'Something went wrong. Please try again; if it keeps happening, give your administrator the reference below.',
    ],
    'files' => [
        'image_too_big' => 'The image dimensions are too large.',
        'infected' => 'The file was rejected by the virus scanner.',
        'invalid_image' => 'The file is not a valid image.',
        'scan_unavailable' => 'The virus scanner is unavailable, so the file cannot be accepted right now.',
        'too_large' => 'The file is larger than the :mb MB limit.',
        'type_not_allowed' => 'This file type is not allowed.',
    ],
    'locales' => [
        'cannot_disable_default' => 'The default language cannot be disabled.',
        'default_must_be_enabled' => 'The default language must be enabled.',
        'self_fallback' => 'A language cannot fall back to itself.',
    ],
    'mail' => [
        'error_alert' => [
            'class' => 'Error',
            'heading' => 'A new error needs attention',
            'occurrences' => 'Occurrences',
            'open' => 'Open in Error Monitoring',
            'reference' => 'Reference',
            'severity' => 'Severity',
            'subject' => 'Error alert :reference',
        ],
        'test' => [
            'body' => 'This is a test message. Outgoing e-mail is configured correctly.',
            'subject' => 'Test e-mail',
        ],
    ],
    'password' => [
        'common' => 'This password is too common. Choose a less predictable one.',
        'contains_identity' => 'The password must not contain your name or e-mail address.',
        'reused' => 'You cannot reuse one of your last :count passwords.',
    ],
    'sessions' => [
        'cannot_revoke_current' => 'Use sign out to end the current session.',
    ],
    'settings' => [
        'immutable_after_setup' => 'This setting is fixed after setup.',
        'mail_not_configured' => 'Outgoing e-mail is not configured yet.',
        'mail_test_failed' => 'The test e-mail could not be sent. Check the SMTP settings.',
        'unknown_keys' => 'Unknown settings: :keys',
    ],
    'setup' => [
        'default_locale_must_be_enabled' => 'The default language must be one of the enabled languages.',
        'invalid_token' => 'The setup token is not valid. Run "php artisan setup:token" on the server to get one.',
        'invalid_two_factor_code' => 'The authenticator code is not valid. Check the time on your device and try again.',
    ],
    'stepup' => [
        'invalid_code' => 'The authentication code is not valid.',
        'two_factor_required' => 'This change needs two-factor authentication. Enable it in your profile first.',
    ],
    'users' => [
        'cannot_change_own_access' => 'You cannot change your own roles or department.',
        'department_exceeds_own_permissions' => 'This department grants permissions you do not hold yourself.',
        'role_exceeds_own_permissions' => 'This role grants permissions you do not hold yourself.',
        'target_more_privileged' => 'This user holds permissions you do not have, so you cannot manage their account.',
        'cannot_delete_self' => 'You cannot delete your own account.',
        'cannot_suspend_self' => 'You cannot suspend or disable your own account.',
        'reset_own_2fa' => 'Reset your own two-factor authentication from your profile.',
        'self_manager' => 'A user cannot be their own manager.',
    ],
];
