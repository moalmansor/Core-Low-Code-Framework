<?php

declare(strict_types=1);

namespace App\Modules\External;

use App\Modules\Audit\AuditWriter;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\External\Models\ExternalUser;
use App\Modules\Identity\Security\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * External accounts and their portal sign-in (specification §4.32, ADR-0058).
 *
 * - Registration is open only when the administrator opens it. A new account
 *   gets the configured external role and must prove its email address with
 *   a one-time code; then it is approved at once or waits for an
 *   administrator, as configured.
 * - Sign-in checks the password, the account's state and the lockout policy
 *   of internal users. The portal session lives in the same session store
 *   as internal sign-ins but under its own key, so an external user is
 *   never an internal user: no internal route, permission or guard sees
 *   them. The session id is regenerated on sign-in and sign-out.
 * - Answers never reveal whether an address has an account.
 */
final class ExternalAccounts
{
    public const SESSION_KEY = 'external_user_id';

    private const SEEN_KEY = 'external_seen_at';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly VerificationCodes $codes,
        private readonly AuditWriter $audit,
        private readonly PasswordPolicy $passwords,
    ) {}

    public function registrationOpen(): bool
    {
        return (bool) $this->settings->get('external', 'registration_open') && $this->registrationRoleId() !== null;
    }

    /**
     * Registers an account and sends its verification code. An address that
     * already has an account gets no second one, and the answer is the same.
     *
     * @param  array<string, mixed>  $input
     */
    public function register(array $input, string $ip, string $locale): void
    {
        $v = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => $this->passwords->rules(null, is_string($input['email'] ?? null) ? $input['email'] : null, is_string($input['name'] ?? null) ? $input['name'] : null),
        ]);
        $data = $v->validate();
        $email = mb_strtolower(trim((string) $data['email']));
        $existing = ExternalUser::query()->where('email', $email)->first();
        if ($existing !== null) {
            if ($existing->status === 'pending_verification') {
                $this->codes->send('account', 'email', $email, $ip, $locale);
            }

            return;
        }
        $user = new ExternalUser;
        $user->forceFill([
            'email' => $email, 'name' => trim((string) $data['name']), 'password' => (string) $data['password'],
            'role_id' => (int) $this->registrationRoleId(), 'status' => 'pending_verification', 'failed_login_count' => 0,
        ])->save();
        $this->audit->record('external.registered', 'auth', null, 'external_user', $user->id, ['email' => $email]);
        $this->codes->send('account', 'email', $email, $ip, $locale);
    }

    /** Sends a new code to an account still waiting for verification; silent otherwise. */
    public function resend(string $email, string $ip, string $locale): void
    {
        $email = mb_strtolower(trim($email));
        if (ExternalUser::query()->where('email', $email)->where('status', 'pending_verification')->exists()) {
            $this->codes->send('account', 'email', $email, $ip, $locale);
        }
    }

    /** Verifies the address with its code. Returns the account's new state, or null when the code is wrong. */
    public function verify(string $email, mixed $code): ?string
    {
        $email = mb_strtolower(trim($email));
        $user = ExternalUser::query()->where('email', $email)->where('status', 'pending_verification')->first();
        if ($user === null || ! $this->codes->check('account', $email, $code)) {
            return null;
        }
        $approve = ! (bool) $this->settings->get('external', 'registration_requires_approval');
        $user->forceFill([
            'email_verified_at' => Carbon::now('UTC'), 'status' => $approve ? 'approved' : 'pending_approval',
            'approved_at' => $approve ? Carbon::now('UTC') : null,
        ])->save();
        $this->audit->record('external.verified', 'auth', null, 'external_user', $user->id, ['status' => $user->status]);

        return $user->status;
    }

    /** Signs in; throws a validation error with one message for every failure. */
    public function login(Request $request, string $email, string $password): ExternalUser
    {
        $email = mb_strtolower(trim($email));
        $user = ExternalUser::query()->where('email', $email)->first();
        $fail = static fn (?string $message = null) => throw ValidationException::withMessages(['email' => $message ?? __('external.login_failed')]);
        if ($user === null) {
            Hash::check($password, '$2y$12$'.str_repeat('a', 53)); // same work as for a real account

            $fail();
        }
        /** @var ExternalUser $user */
        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            $fail(__('external.locked'));
        }
        if (! Hash::check($password, $user->password)) {
            $max = (int) $this->settings->get('security', 'lockout_max_attempts');
            $count = $user->failed_login_count + 1;
            $user->forceFill($count >= $max
                ? ['failed_login_count' => 0, 'locked_until' => Carbon::now('UTC')->addMinutes((int) $this->settings->get('security', 'lockout_minutes'))]
                : ['failed_login_count' => $count])->save();
            $this->audit->record('external.login_failed', 'auth', null, 'external_user', $user->id);
            $fail();
        }
        if ($user->status !== 'approved') {
            $fail(__('external.status.'.$user->status));
        }
        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null, 'last_login_at' => Carbon::now('UTC')])->save();
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $user->id);
        $request->session()->put(self::SEEN_KEY, time());
        $this->audit->record('external.login', 'auth', null, 'external_user', $user->id);

        return $user;
    }

    public function logout(Request $request): void
    {
        $id = $request->session()->pull(self::SESSION_KEY);
        $request->session()->forget(self::SEEN_KEY);
        $request->session()->regenerate();
        if (is_int($id)) {
            $this->audit->record('external.logout', 'auth', null, 'external_user', $id);
        }
    }

    /**
     * The signed-in external user of a request, if the session holds one that
     * is still approved and not idle for longer than the security policy allows.
     */
    public function current(Request $request): ?ExternalUser
    {
        if (! $request->hasSession()) {
            return null;
        }
        $id = $request->session()->get(self::SESSION_KEY);
        if (! is_int($id)) {
            return null;
        }
        $idle = (int) $this->settings->get('security', 'session_idle_minutes') * 60;
        $seen = (int) $request->session()->get(self::SEEN_KEY, 0);
        $user = time() - $seen > $idle ? null : ExternalUser::query()->withoutGlobalScope('organization')->whereKey($id)->whereNull('deleted_at')->where('status', 'approved')->first();
        if ($user === null) {
            $request->session()->forget([self::SESSION_KEY, self::SEEN_KEY]);

            return null;
        }
        $request->session()->put(self::SEEN_KEY, time());

        return $user;
    }

    /**
     * An administrator's decision on an account.
     *
     * @param  'approve'|'reject'|'suspend'|'reactivate'  $decision
     */
    public function decide(ExternalUser $user, string $decision, int $actorId): void
    {
        $allowed = [
            'approve' => ['pending_approval', 'rejected'], 'reject' => ['pending_approval'],
            'suspend' => ['approved'], 'reactivate' => ['suspended'],
        ];
        if (! in_array($user->status, $allowed[$decision], true)) {
            throw ValidationException::withMessages(['status' => __('external.decision_not_allowed')]);
        }
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended', 'reactivate' => 'approved'][$decision];
        $from = $user->status;
        $user->forceFill($status === 'approved' && $decision === 'approve'
            ? ['status' => $status, 'approved_by' => $actorId, 'approved_at' => Carbon::now('UTC')]
            : ['status' => $status])->save();
        if ($status !== 'approved') {
            // Signed-in sessions of the account end now.
            DB::table('sessions')->where('external_user_id', $user->id)->delete();
        }
        $this->audit->record('external.account_'.$decision, 'access', [['field_key' => 'status', 'old' => $from, 'new' => $status]], 'external_user', $user->id, null, $actorId);
    }

    private function registrationRoleId(): ?int
    {
        $uuid = $this->settings->get('external', 'registration_role');
        $id = is_string($uuid) ? DB::table('roles')->where('uuid', strtolower($uuid))->where('audience', 'external')->value('id') : null;

        return $id === null ? null : (int) $id;
    }
}
