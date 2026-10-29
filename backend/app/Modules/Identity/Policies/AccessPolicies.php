<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Access\AccessCache;
use App\Modules\Access\Models\Role;
use App\Modules\Audit\AuditWriter;
use App\Modules\Core\I18n\Translator;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\AccessPolicy;
use App\Modules\Identity\Models\User;
use App\Support\Json\Canonical;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Access policies per role (specification §4.36, ADR-0060).
 *
 * A user is governed by the policies of their active roles and, when they
 * hold an administrative role, by every administrative policy. The
 * effective policy is the strictest combination: every address list and
 * every set of time windows must be satisfied, the smallest session limits
 * win, and a second factor or a trusted device is required when any policy
 * asks for it. The global session settings stay an upper bound.
 */
final class AccessPolicies
{
    /** @var array<int, array<string, mixed>|null> */
    private array $memo = [];

    public function __construct(
        private readonly Translator $translator,
        private readonly AuditWriter $audit,
        private readonly SettingsService $settings,
        private readonly AccessCache $cache,
    ) {}

    /** @return array<string, mixed> */
    public function present(AccessPolicy $p): array
    {
        $doc = [
            'uuid' => strtolower($p->uuid), 'key' => $p->key,
            'i18n' => ['name' => (object) $p->translationsFor('name')],
            'ip_allowlist' => array_values($p->ip_allowlist ?? []),
            'time_windows' => array_values($p->time_windows ?? []),
            'max_concurrent_sessions' => $p->max_concurrent_sessions,
            'session_idle_minutes' => $p->session_idle_minutes, 'session_absolute_minutes' => $p->session_absolute_minutes,
            'require_trusted_device' => $p->require_trusted_device, 'require_2fa' => $p->require_2fa, 'is_admin_policy' => $p->is_admin_policy,
            'roles' => DB::table('roles')->where('access_policy_id', $p->id)->orderBy('id')->pluck('uuid')->map(static fn ($u) => strtolower((string) $u))->values()->all(),
        ];
        $doc['hash'] = hash('sha256', (string) json_encode(Canonical::of($doc), JSON_UNESCAPED_UNICODE));
        $doc['label'] = $this->translator->labelOf($p->translationsFor('name'), __('identity.policy'));

        return $doc;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function check(array $d, ?AccessPolicy $existing): array
    {
        $errors = [];
        $key = (string) ($d['key'] ?? '');
        if (preg_match('/^[a-z][a-z0-9_]{1,47}$/', $key) !== 1) {
            $errors['key'][] = __('identity.bad_key');
        } elseif (AccessPolicy::query()->where('key', $key)->when($existing !== null, static fn ($q) => $q->where('id', '!=', $existing->id))->exists()) {
            $errors['key'][] = __('identity.key_taken');
        }
        $name = [];
        foreach ((array) ($d['i18n']['name'] ?? []) as $locale => $text) {
            if (is_string($locale) && is_string($text) && trim($text) !== '') {
                $name[$locale] = mb_substr(trim($text), 0, 255);
            }
        }
        if ($name === []) {
            $errors['i18n.name'][] = __('identity.name_required');
        }
        $ips = [];
        foreach (array_values((array) ($d['ip_allowlist'] ?? [])) as $i => $cidr) {
            $cidr = trim((string) $cidr);
            if ($cidr === '') {
                continue;
            }
            self::validCidr($cidr) ? $ips[] = $cidr : $errors["ip_allowlist.{$i}"][] = __('identity.bad_cidr', ['value' => $cidr]);
        }
        if (count($ips) > 100) {
            $errors['ip_allowlist'][] = __('identity.too_many', ['max' => 100]);
        }
        $windows = [];
        $zones = DateTimeZone::listIdentifiers();
        foreach (array_values((array) ($d['time_windows'] ?? [])) as $i => $w) {
            $days = array_values(array_unique(array_map('intval', (array) ($w['days'] ?? []))));
            sort($days);
            $from = (string) ($w['from'] ?? '');
            $to = (string) ($w['to'] ?? '');
            $zone = (string) ($w['timezone'] ?? '');
            if ($days === [] || array_diff($days, range(1, 7)) !== []) {
                $errors["time_windows.{$i}.days"][] = __('identity.bad_days');
            }
            if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $from) !== 1 || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $to) !== 1 || $from === $to) {
                $errors["time_windows.{$i}.from"][] = __('identity.bad_times');
            }
            if (! in_array($zone, $zones, true)) {
                $errors["time_windows.{$i}.timezone"][] = __('identity.bad_timezone');
            }
            $windows[] = ['days' => $days, 'from' => $from, 'to' => $to, 'timezone' => $zone];
        }
        if (count($windows) > 20) {
            $errors['time_windows'][] = __('identity.too_many', ['max' => 20]);
        }
        $max = ($d['max_concurrent_sessions'] ?? null) === null ? null : (int) $d['max_concurrent_sessions'];
        if ($max !== null && ($max < 1 || $max > 100)) {
            $errors['max_concurrent_sessions'][] = __('identity.bad_sessions');
        }
        $idle = (int) ($d['session_idle_minutes'] ?? 0);
        $absolute = (int) ($d['session_absolute_minutes'] ?? 0);
        if ($idle < 5 || $idle > 1440) {
            $errors['session_idle_minutes'][] = __('identity.bad_idle');
        }
        if ($absolute < 15 || $absolute > 43200 || $absolute < $idle) {
            $errors['session_absolute_minutes'][] = __('identity.bad_absolute');
        }
        $roles = [];
        foreach (array_slice((array) ($d['roles'] ?? []), 0, 200) as $r) {
            $id = is_string($r) && Str::isUuid($r) ? DB::table('roles')->where('uuid', strtolower($r))->value('id') : null;
            $id === null ? $errors['roles'][] = __('reports.unknown_role') : $roles[] = (int) $id;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'key' => $key, 'name' => $name, 'ip_allowlist' => $ips === [] ? null : $ips, 'time_windows' => $windows === [] ? null : $windows,
            'max_concurrent_sessions' => $max, 'session_idle_minutes' => $idle, 'session_absolute_minutes' => $absolute,
            'require_trusted_device' => (bool) ($d['require_trusted_device'] ?? false), 'require_2fa' => (bool) ($d['require_2fa'] ?? false),
            'is_admin_policy' => (bool) ($d['is_admin_policy'] ?? false), 'roles' => array_values(array_unique($roles)),
        ];
    }

    /**
     * Saves the policy and the roles it governs. Refused when the policy
     * would shut out the administrator saving it from where and when they
     * are working now.
     *
     * @param  array<string, mixed>  $p  from check()
     */
    public function save(?AccessPolicy $existing, array $p, User $actor, Request $request): AccessPolicy
    {
        return DB::transaction(function () use ($existing, $p, $actor, $request): AccessPolicy {
            $policy = $existing ?? new AccessPolicy;
            if ($existing === null) {
                $policy->uuid = (string) Str::uuid7();
            }
            $policy->forceFill(array_diff_key($p, ['name' => true, 'roles' => true]))->save();
            $policy->setTranslations('name', $p['name']);
            DB::table('roles')->where('access_policy_id', $policy->id)->whereNotIn('id', $p['roles'] === [] ? [0] : $p['roles'])->update(['access_policy_id' => null]);
            if ($p['roles'] !== []) {
                DB::table('roles')->whereIn('id', $p['roles'])->update(['access_policy_id' => $policy->id]);
            }
            $this->memo = [];
            // An untrusted device is fixed by verifying a second factor on it; an address or a time is not.
            $why = $this->denial($actor, $request);
            if ($why === 'ip' || $why === 'time') {
                throw ValidationException::withMessages(['policy' => __('identity.locks_you_out', ['reason' => __("identity.denied_{$why}")])]);
            }
            if (! $this->anAdministratorCanSignIn($actor)) {
                throw ValidationException::withMessages(['policy' => __('identity.locks_out_administrators')]);
            }
            $this->audit->record($existing === null ? 'access_policy.created' : 'access_policy.updated', 'security', null, 'access_policy', $policy->id,
                ['key' => $policy->key, 'roles' => count($p['roles'])], $actor->id);
            $this->cache->bump();

            return $policy->refresh();
        });
    }

    /**
     * Someone must still be able to administer the system. When every active
     * Super Admin is held to addresses or hours, the person saving must be one
     * of them and be inside those limits now (checked just before): nobody
     * else can show that an administrator can still sign in.
     */
    private function anAdministratorCanSignIn(User $actor): bool
    {
        $role = Role::query()->where('key', 'super_admin')->value('id');
        if ($role === null) {
            return true;
        }
        $admins = User::query()->where('status', 'active')->whereHas('activeRoles', static fn ($q) => $q->where('roles.id', $role))->get();
        foreach ($admins as $admin) {
            $e = $this->effective($admin);
            if ($e === null || ($e['ip_lists'] === [] && $e['window_sets'] === [])) {
                return true;
            }
        }

        return $admins->contains('id', $actor->id);
    }

    public function delete(AccessPolicy $p, User $actor): void
    {
        DB::transaction(function () use ($p, $actor): void {
            DB::table('roles')->where('access_policy_id', $p->id)->update(['access_policy_id' => null]);
            $this->audit->record('access_policy.deleted', 'security', null, 'access_policy', $p->id, ['key' => $p->key], $actor->id);
            $p->delete();
            $this->memo = [];
        });
    }

    /**
     * The strictest combination of the policies governing the user, or null
     * when none does.
     *
     * @return array{keys: list<string>, ip_lists: list<list<string>>, window_sets: list<list<array{days: list<int>, from: string, to: string, timezone: string}>>, max_sessions: int|null, idle: int|null, absolute: int|null, trusted_device: bool, two_factor: bool}|null
     */
    public function effective(User $user): ?array
    {
        if (array_key_exists($user->id, $this->memo)) {
            /** @var array{keys: list<string>, ip_lists: list<list<string>>, window_sets: list<list<array{days: list<int>, from: string, to: string, timezone: string}>>, max_sessions: int|null, idle: int|null, absolute: int|null, trusted_device: bool, two_factor: bool}|null */
            return $this->memo[$user->id];
        }
        $roles = $user->activeRoles()->get(['roles.id', 'roles.access_policy_id', 'roles.is_admin_role']);
        $ids = $roles->pluck('access_policy_id')->filter()->map(static fn ($v) => (int) $v)->all();
        $policies = AccessPolicy::query()->withoutGlobalScope('organization')->where('organization_id', $user->organization_id)
            ->where(static fn ($q) => $q->whereIn('id', $ids === [] ? [0] : $ids)
                ->when($roles->contains(static fn (Model $r) => $r instanceof Role && $r->is_admin_role), static fn ($q) => $q->orWhere('is_admin_policy', true)))
            ->get();
        if ($policies->isEmpty()) {
            return $this->memo[$user->id] = null;
        }
        $min = static fn (array $values): ?int => ($values = array_values(array_filter($values, static fn ($v) => $v !== null))) === [] ? null : (int) min($values);

        return $this->memo[$user->id] = [
            'keys' => $policies->pluck('key')->sort()->values()->all(),
            'ip_lists' => $policies->map(static fn (AccessPolicy $p) => array_values($p->ip_allowlist ?? []))->filter(static fn ($l) => $l !== [])->values()->all(),
            'window_sets' => $policies->map(static fn (AccessPolicy $p) => array_values($p->time_windows ?? []))->filter(static fn ($l) => $l !== [])->values()->all(),
            'max_sessions' => $min($policies->pluck('max_concurrent_sessions')->all()),
            'idle' => $min($policies->pluck('session_idle_minutes')->all()),
            'absolute' => $min($policies->pluck('session_absolute_minutes')->all()),
            'trusted_device' => $policies->contains(static fn (AccessPolicy $p) => $p->require_trusted_device),
            'two_factor' => $policies->contains(static fn (AccessPolicy $p) => $p->require_2fa || $p->require_trusted_device),
        ];
    }

    /**
     * Why the user may not work from this request now — `ip`, `time` or
     * `device` — or null when they may.
     */
    public function denial(User $user, Request $request, ?Carbon $now = null): ?string
    {
        $e = $this->effective($user);
        if ($e === null) {
            return null;
        }
        $ip = (string) $request->ip();
        foreach ($e['ip_lists'] as $list) {
            if (! IpUtils::checkIp($ip, $list)) {
                return 'ip';
            }
        }
        $now ??= Carbon::now('UTC');
        foreach ($e['window_sets'] as $windows) {
            if (! self::within($windows, $now)) {
                return 'time';
            }
        }
        // A device becomes trusted when a second factor is verified on it; until the
        // user has a second factor, enrolling it is what they must do first.
        if ($e['trusted_device'] && $user->hasEnabledTwoFactorAuthentication() && app(TrustedDevices::class)->current($user, $request) === null) {
            return 'device';
        }

        return null;
    }

    /** Idle and absolute session limits in minutes: the global settings, tightened by the user's policies. */
    public function sessionLimits(User $user): array
    {
        $idle = (int) $this->settings->get('security', 'session_idle_minutes');
        $absolute = (int) $this->settings->get('security', 'session_absolute_minutes');
        $e = $this->effective($user);

        return ['idle' => min($idle, $e['idle'] ?? $idle), 'absolute' => min($absolute, $e['absolute'] ?? $absolute)];
    }

    /**
     * After a sign-in, ends the user's oldest sessions beyond the policy's
     * limit; the new session is kept.
     */
    public function limitSessions(User $user, ?string $currentSessionId): int
    {
        $max = $this->effective($user)['max_sessions'] ?? null;
        if ($max === null) {
            return 0;
        }
        $others = DB::table('sessions')->where('user_id', $user->id)->where('guard', 'web')
            ->when($currentSessionId !== null, static fn ($q) => $q->where('id', '!=', $currentSessionId))
            ->orderByDesc('last_activity')->pluck('id')->all();
        $excess = array_slice($others, max(0, $max - 1));
        if ($excess !== []) {
            DB::table('sessions')->whereIn('id', $excess)->delete();
            $this->audit->record('auth.sessions_limited', 'security', null, 'user', $user->id, ['ended' => count($excess), 'max' => $max], $user->id);
        }

        return count($excess);
    }

    /** @param  list<array{days: list<int>, from: string, to: string, timezone: string}>  $windows */
    public static function within(array $windows, Carbon $now): bool
    {
        foreach ($windows as $w) {
            $local = $now->copy()->setTimezone($w['timezone']);
            $time = $local->format('H:i');
            $day = (int) $local->format('N');
            $previous = $day === 1 ? 7 : $day - 1;
            if ($w['from'] < $w['to']) {
                if (in_array($day, $w['days'], true) && $time >= $w['from'] && $time < $w['to']) {
                    return true;
                }
            } elseif ((in_array($day, $w['days'], true) && $time >= $w['from']) || (in_array($previous, $w['days'], true) && $time < $w['to'])) {
                // A window past midnight belongs to the day it starts on.
                return true;
            }
        }

        return false;
    }

    private static function validCidr(string $cidr): bool
    {
        [$address, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if ($bits === null) {
            return true;
        }
        $max = str_contains((string) $address, ':') ? 128 : 32;

        return ctype_digit($bits) && (int) $bits >= 0 && (int) $bits <= $max;
    }
}
