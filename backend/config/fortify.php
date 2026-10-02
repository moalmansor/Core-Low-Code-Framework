<?php

use Laravel\Fortify\Features;

/*
 * Fortify serves the SPA's authentication endpoints under /api/v1/auth
 * (architecture §7.1, §21.2). Self-registration, profile updates through
 * Fortify, e-mail verification, and passkeys are not part of the specification
 * and stay disabled; users are created by administrators.
 */
return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/',
    'prefix' => 'api/v1/auth',
    'domain' => null,
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
    ],
    'views' => false,
    'features' => [
        Features::resetPasswords(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            'window' => 1,
        ]),
    ],
];
