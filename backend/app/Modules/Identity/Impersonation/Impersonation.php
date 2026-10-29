<?php

declare(strict_types=1);

namespace App\Modules\Identity\Impersonation;

use App\Modules\Access\EscalationGuard;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Actors\ImpersonationContext;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\ImpersonationSession;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Impersonation (specification §4.36, §5; architecture §7.7; ADR-0061).
 *
 * An administrator with "Impersonate users" acts as another user to
 * reproduce a problem, for a stated reason and a limited time. They cannot
 * impersonate someone holding a permission they lack, a Super Admin unless
 * they are one, themselves, an inactive account, or anyone while already
 * impersonating. Account security is never reachable during it, and the
 * sensitive actions chosen in the security settings are refused. Every
 * audit entry names the administrator as the actor and the user as the
 * subject.
 */
final class Impersonation
{
    public const SESSION_KEY = 'impersonation.id';

    /** Blockable actions: method and path patterns under /api/v1. */
    public const BLOCKABLE = [
        'delete_records' => [['DELETE', 'api/v1/r/*/*'], ['POST', 'api/v1/r/*/bulk-delete'], ['POST', 'api/v1/r/*/merge']],
        'export_data' => [['POST', 'api/v1/r/*/exports'], ['POST', 'api/v1/r/*/downloads/*'], ['POST', 'api/v1/dashboards/*/export'], ['POST', 'api/v1/reports/*/export'], ['POST', 'api/v1/packages/export']],
        'run_actions' => [['POST', 'api/v1/r/*/actions/*/run']],
        'change_access' => [['*', 'api/v1/users*'], ['*', 'api/v1/roles*'], ['*', 'api/v1/permissions*'], ['*', 'api/v1/access-policies*'], ['*', 'api/v1/forms/*/access*'], ['*', 'api/v1/departments*']],
    ];

    /** Never reachable while impersonating: the impersonated person's own account security. */
    private const ALWAYS = [['*', 'api/v1/auth/user/*'], ['*', 'api/v1/me/tokens*'], ['*', 'api/v1/me/sessions*'], ['*', 'api/v1/me/devices*'], ['*', 'api/v1/api-tokens*'], ['PATCH', 'api/v1/me'], ['POST', 'api/v1/users/*/impersonate']];

    public function __construct(
        private readonly EscalationGuard $escalation,
        private readonly SettingsService $settings,
        private readonly AuditWriter $audit,
        private readonly ImpersonationContext $context,
    ) {}

    public function start(User $actor, User $target, string $reason, int $minutes, Request $request): ImpersonationSession
    {
        abort_if($this->current($request) !== null, 403, __('identity.already_impersonating'));
        abort_if($actor->is($target), 422, __('identity.not_yourself'));
        abort_if($target->status !== 'active' || $target->trashed(), 422, __('identity.target_inactive'));
        abort_if(in_array('super_admin', $target->roleKeys(), true) && ! in_array('super_admin', $actor->roleKeys(), true), 403, __('identity.target_super_admin'));
        // No escalation: the target may hold nothing the administrator lacks.
        $this->escalation->assertCanManage($actor, $target);
        $max = (int) $this->settings->get('security', 'impersonation_max_minutes');
        if ($minutes < 5 || $minutes > $max) {
            throw ValidationException::withMessages(['minutes' => __('identity.bad_minutes', ['max' => $max])]);
        }
        $now = Carbon::now('UTC');
        $session = DB::transaction(function () use ($actor, $target, $reason, $minutes, $request, $now): ImpersonationSession {
            $s = new ImpersonationSession;
            $s->forceFill([
                'uuid' => (string) Str::uuid7(), 'impersonator_user_id' => $actor->id, 'impersonated_user_id' => $target->id,
                'reason' => $reason, 'started_at' => $now->format('Y-m-d H:i:s.u'), 'expires_at' => $now->copy()->addMinutes($minutes)->format('Y-m-d H:i:s.u'),
                'ip_address' => (string) $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            ])->save();
            $this->audit->record('impersonation.started', 'security', null, 'user', $target->id, ['minutes' => $minutes, 'reason' => $reason, 'session' => $s->uuid], $actor->id, $target->id);

            return $s;
        });
        $request->session()->put(self::SESSION_KEY, $session->id);
        $request->session()->migrate(true);
        $this->switchTo($target, $request);
        DB::table('sessions')->where('id', $request->session()->getId())->update(['impersonation_session_id' => $session->id]);

        return $session;
    }

    /** The impersonation this request runs under, still open, or null. */
    public function current(Request $request): ?ImpersonationSession
    {
        $id = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return is_int($id) ? ImpersonationSession::query()->whereKey($id)->whereNull('ended_at')->first() : null;
    }

    /** Ends it and signs the administrator back in as themselves. */
    public function stop(ImpersonationSession $s, Request $request, string $reason): User
    {
        $s->forceFill(['ended_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'end_reason' => $reason])->save();
        $this->audit->record('impersonation.ended', 'security', null, 'user', $s->impersonated_user_id, ['reason' => $reason, 'session' => $s->uuid], $s->impersonator_user_id, $s->impersonated_user_id);
        $request->session()->forget(self::SESSION_KEY);
        $admin = User::query()->findOrFail($s->impersonator_user_id);
        $request->session()->migrate(true);
        $this->switchTo($admin, $request);
        DB::table('sessions')->where('id', $request->session()->getId())->update(['impersonation_session_id' => null]);
        $this->context->sessionId = $this->context->impersonatorId = $this->context->subjectId = null;

        return $admin;
    }

    /** Marks it ended when the session ends by signing out. */
    public function ended(Request $request, string $reason): void
    {
        $s = $this->current($request);
        if ($s !== null) {
            $s->forceFill(['ended_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u'), 'end_reason' => $reason])->save();
            $this->audit->record('impersonation.ended', 'security', null, 'user', $s->impersonated_user_id, ['reason' => $reason, 'session' => $s->uuid], $s->impersonator_user_id, $s->impersonated_user_id);
        }
    }

    /** Which blocked action the request is, or null. */
    public function blocked(Request $request): ?string
    {
        $path = ltrim($request->path(), '/');
        $method = $request->method();
        $matches = static fn (array $rules): bool => array_filter($rules, static fn (array $r) => ($r[0] === '*' ? $method !== 'GET' && $method !== 'HEAD' : $r[0] === $method) && Str::is($r[1], $path)) !== [];
        if ($matches(self::ALWAYS)) {
            return 'account_security';
        }
        foreach ((array) $this->settings->get('security', 'impersonation_blocked_actions') as $category) {
            if (isset(self::BLOCKABLE[$category]) && $matches(self::BLOCKABLE[$category])) {
                return (string) $category;
            }
        }

        return null;
    }

    /** Signs the session in as the user without the sign-in events (no sign-in happened). */
    private function switchTo(User $user, Request $request): void
    {
        // The session remembers the signed-in person's password hash to end it on a password change;
        // it is taken again for the person now signed in.
        foreach (array_keys($request->session()->all()) as $key) {
            if (str_starts_with((string) $key, 'password_hash_')) {
                $request->session()->forget((string) $key);
            }
        }
        $guard = Auth::guard('web');
        if ($guard instanceof SessionGuard) {
            $request->session()->put($guard->getName(), $user->getAuthIdentifier());
            $guard->setUser($user);
        }
        // The API guard holds the person it resolved for this request; it follows the switch.
        $api = Auth::guard('sanctum');
        if (method_exists($api, 'setUser')) {
            $api->setUser($user);
        }
    }
}
