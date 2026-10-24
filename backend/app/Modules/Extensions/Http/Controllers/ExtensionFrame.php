<?php

declare(strict_types=1);

namespace App\Modules\Extensions\Http\Controllers;

/**
 * Marks the response of the client-extension frame: the security headers
 * middleware gives it the policy carried in this header instead of the
 * application's, and lets this application frame it.
 */
final class ExtensionFrame
{
    public const HEADER = 'X-LCF-Extension-Frame-Policy';
}
