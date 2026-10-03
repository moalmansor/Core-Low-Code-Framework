<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Core\Settings\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idle and absolute session timeouts from the security settings
 * (specification §5 "session timeout").
 */
final class EnforceSessionPolicy
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || $request->user() === null) {
            return $next($request);
        }
        $session = $request->session();
        $now = now()->getTimestamp();
        $started = (int) $session->get('_lcf.started_at', $now);
        $lastSeen = (int) $session->get('_lcf.last_seen', $now);
        $idle = 60 * (int) $this->settings->get('security', 'session_idle_minutes');
        $absolute = 60 * (int) $this->settings->get('security', 'session_absolute_minutes');
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
