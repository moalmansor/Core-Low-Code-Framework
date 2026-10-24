<?php

declare(strict_types=1);

namespace App\Modules\Identity\Policies;

use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trusted devices (specification §4.36, ADR-0060). A browser carries a
 * random device cookie; only an HMAC of it is stored. A device becomes
 * trusted for a user when a second factor is verified on it — the
 * system's own code or the identity provider's multi-factor sign-in — and
 * stays trusted for 90 days unless revoked.
 */
final class TrustedDevices
{
    public const COOKIE = 'lcf_device';

    private const DAYS = 90;

    public function __construct(private readonly AuditWriter $audit) {}

    /** The trusted device row id this request comes from, or null. */
    public function current(User $user, Request $request): ?int
    {
        $value = $request->cookie(self::COOKIE);
        if (! is_string($value) || strlen($value) < 32) {
            return null;
        }
        $id = DB::table('trusted_devices')->where('user_id', $user->id)->where('device_hash', self::hash($value))
            ->whereNull('revoked_at')->where('expires_at', '>', Carbon::now('UTC')->format('Y-m-d H:i:s.u'))->value('id');

        return $id === null ? null : (int) $id;
    }

    /** Trusts the device of this request for the user, giving it a cookie when it has none. */
    public function trust(User $user, Request $request): int
    {
        $value = $request->cookie(self::COOKIE);
        if (! is_string($value) || strlen($value) < 32) {
            $value = Str::random(64);
        }
        // Long-lived, HTTP-only, same-site; encrypted like every cookie of the app.
        Cookie::queue(Cookie::make(self::COOKIE, $value, 60 * 24 * 400, '/', null, null, true, false, 'lax'));
        $now = Carbon::now('UTC');
        $hash = self::hash($value);
        $row = ['updated_at' => $now->format('Y-m-d H:i:s.u'), 'last_used_at' => $now->format('Y-m-d H:i:s.u'),
            'expires_at' => $now->copy()->addDays(self::DAYS)->format('Y-m-d H:i:s.u'), 'revoked_at' => null,
            'label' => mb_substr(self::label((string) $request->userAgent()), 0, 255)];
        $id = DB::table('trusted_devices')->where('user_id', $user->id)->where('device_hash', $hash)->value('id');
        if ($id === null) {
            $id = DB::table('trusted_devices')->insertGetId($row + ['user_id' => $user->id, 'device_hash' => $hash, 'created_at' => $row['updated_at']]);
            $this->audit->record('auth.device_trusted', 'security', null, 'user', $user->id, ['device' => $row['label']], $user->id);
        } else {
            DB::table('trusted_devices')->where('id', $id)->update($row);
        }
        if ($request->hasSession()) {
            DB::table('sessions')->where('id', $request->session()->getId())->update(['trusted_device_id' => $id]);
        }

        return (int) $id;
    }

    /** @return list<array{id: int, label: string|null, last_used_at: string|null, expires_at: string, current: bool}> */
    public function list(User $user, Request $request): array
    {
        $current = $this->current($user, $request);

        return DB::table('trusted_devices')->where('user_id', $user->id)->whereNull('revoked_at')
            ->where('expires_at', '>', Carbon::now('UTC')->format('Y-m-d H:i:s.u'))->orderByDesc('last_used_at')->get()
            ->map(static fn (object $d) => [
                'id' => (int) $d->id, 'label' => $d->label,
                'last_used_at' => $d->last_used_at === null ? null : Carbon::parse((string) $d->last_used_at, 'UTC')->toIso8601ZuluString(),
                'expires_at' => Carbon::parse((string) $d->expires_at, 'UTC')->toIso8601ZuluString(), 'current' => (int) $d->id === $current,
            ])->values()->all();
    }

    public function revoke(User $user, int $id, int $actorId): bool
    {
        $done = DB::table('trusted_devices')->where('user_id', $user->id)->where('id', $id)->whereNull('revoked_at')
            ->update(['revoked_at' => Carbon::now('UTC')->format('Y-m-d H:i:s.u')]) > 0;
        if ($done) {
            $this->audit->record('auth.device_revoked', 'security', null, 'user', $user->id, ['device' => $id], $actorId);
        }

        return $done;
    }

    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    /** "Chrome on Windows" from a user agent, never the raw string. */
    private static function label(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $os === null ? $browser : "{$browser} · {$os}";
    }
}
