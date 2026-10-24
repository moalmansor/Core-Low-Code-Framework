<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Actors\ImpersonationContext;
use App\Modules\Identity\Impersonation\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs every signed-in request of an impersonation (ADR-0061): ends it at
 * its time limit, refuses account security and the blocked actions, and
 * tells the audit trail who is really acting.
 */
final class GuardImpersonation
{
    public function __construct(private readonly Impersonation $impersonation, private readonly ImpersonationContext $context, private readonly AuditWriter $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(Impersonation::SESSION_KEY)) {
            return $next($request);
        }
        $s = $this->impersonation->current($request);
        if ($s === null) {
            // Ended elsewhere (signed out, or the record was closed): back to the administrator's own session.
            $request->session()->forget(Impersonation::SESSION_KEY);

            return response()->json(['message' => __('identity.impersonation_ended'), 'code' => 'impersonation_ended'], 409);
        }
        if (Carbon::parse($s->expires_at, 'UTC')->isPast()) {
            $this->impersonation->stop($s, $request, 'expired');

            return response()->json(['message' => __('identity.impersonation_expired'), 'code' => 'impersonation_ended'], 409);
        }
        $this->context->sessionId = $s->id;
        $this->context->impersonatorId = $s->impersonator_user_id;
        $this->context->subjectId = $s->impersonated_user_id;
        $blocked = $this->impersonation->blocked($request);
        if ($blocked !== null && ! $request->is('api/v1/impersonation/stop')) {
            // The attempt is part of the record too: the administrator tried it, acting as the user.
            $this->audit->record('impersonation.blocked', 'security', null, 'user', $s->impersonated_user_id,
                ['action' => $blocked, 'method' => $request->method(), 'path' => '/'.ltrim($request->path(), '/'), 'session' => $s->uuid], $s->impersonator_user_id, $s->impersonated_user_id);

            return response()->json(['message' => __('identity.impersonation_blocked'), 'code' => 'impersonation_blocked', 'action' => $blocked], 403);
        }

        return $next($request);
    }
}
