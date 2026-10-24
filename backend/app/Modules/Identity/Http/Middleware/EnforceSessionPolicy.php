<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Policies\AccessPolicies;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idle and absolute session timeouts from the security settings
 * (specification §5 "session timeout"), tightened by the user's access
 * policies (§4.36, ADR-0060).
 */
final class EnforceSessionPolicy
{
    public function __construct(private readonly AccessPolicies $policies) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $request->hasSession() || ! $user instanceof User) {
            return $next($request);
        }
        $session = $request->session();
        $now = now()->getTimestamp();
        $started = (int) $session->get('_lcf.started_at', $now);
        $lastSeen = (int) $session->get('_lcf.last_seen', $now);
        $limits = $this->policies->sessionLimits($user);
        $idle = 60 * $limits['idle'];
        $absolute = 60 * $limits['absolute'];
        if ($now - $lastSeen > $idle || $now - $started > $absolute) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();

            return response()->json(['message' => __('ui.auth.session_expired'), 'code' => 'session_expired'], 401);
        }
        $session->put('_lcf.started_at', $started);
        $session->put('_lcf.last_seen', $now);

        return $next($request);
    }
}
