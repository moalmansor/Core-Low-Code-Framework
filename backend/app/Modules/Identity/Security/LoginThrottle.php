<?php

declare(strict_types=1);

namespace App\Modules\Identity\Security;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Core\Tenancy\TenantContext;
use App\Modules\Identity\Models\LoginAttempt;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;

/**
 * Account lockout after repeated failures (specification §5). Every attempt is
 * recorded in `login_attempts` with an HMAC of the identifier (never the
 * plaintext) and audited.
 */
final class LoginThrottle
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly TenantContext $tenant,
        private readonly AuditWriter $audit,
    ) {}

    public function identifierHash(string $identifier): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($identifier)), (string) config('app.key'));
    }

    public function failed(Request $request, string $identifier, ?User $user, string $reason): void
    {
        $this->log($request, $identifier, $user, false, $reason);
        if ($user !== null && $reason === 'bad_password') {
            $count = $user->failed_login_count + 1;
            $max = (int) $this->settings->get('security', 'lockout_max_attempts');
            $updates = ['failed_login_count' => $count];
            if ($count >= $max) {
                $updates = ['failed_login_count' => 0, 'locked_until' => now()->addMinutes((int) $this->settings->get('security', 'lockout_minutes'))];
                $this->audit->record('auth.locked_out', 'auth', objectType: 'user', objectId: $user->id, actorUserId: null, subjectUserId: $user->id);
            }
            $user->forceFill($updates)->saveQuietly();
        }
        $this->audit->record('auth.login_failed', 'auth', objectType: $user !== null ? 'user' : null, objectId: $user?->id, meta: ['reason' => $reason], actorUserId: null, subjectUserId: $user?->id);
    }

    public function succeeded(Request $request, User $user): void
    {
        $this->log($request, $user->email, $user, true, null);
        $user->forceFill([
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();
    }

    private function log(Request $request, string $identifier, ?User $user, bool $ok, ?string $reason): void
    {
        LoginAttempt::query()->create([
            'organization_id' => $user->organization_id ?? $this->tenant->platformOrganizationId(),
            'identifier_hash' => $this->identifierHash($identifier),
            'user_id' => $user?->id,
            'guard' => 'web',
            'ip_address' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'successful' => $ok,
            'failure_reason' => $reason,
            'attempted_at' => now(),
        ]);
    }
}
