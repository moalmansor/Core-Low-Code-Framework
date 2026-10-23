<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\AccessPolicies;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The access policies of the user's roles on every signed-in request
 * (specification §4.36, ADR-0060): outside the allowed addresses or hours,
 * or on a device that is not trusted when one is required, the session
 * ends and the reason is given.
 */
final class EnforceAccessPolicy
{
    public function __construct(private readonly AccessPolicies $policies, private readonly AuditWriter $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }
        $why = $this->policies->denial($user, $request);
        if ($why === null) {
            return $next($request);
        }
        $this->audit->record('auth.policy_denied', 'security', null, 'user', $user->id, ['reason' => $why, 'ip' => $request->ip()], $user->id);
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __("identity.denied_{$why}"), 'code' => 'access_policy', 'reason' => $why], 401);
    }
}
