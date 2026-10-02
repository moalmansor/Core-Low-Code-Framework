<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-factor authentication is mandatory for roles that require it
 * (specification §2, §5): until it is confirmed, every protected API call
 * answers 403 `two_factor_enrollment_required`.
 */
final class EnsureTwoFactorEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && ! $user->hasEnabledTwoFactorAuthentication() && $user->requiresTwoFactor()) {
            return response()->json(['message' => __('ui.auth.two_factor_required'), 'code' => 'two_factor_enrollment_required'], 403);
        }

        return $next($request);
    }
}
