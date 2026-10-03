<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth;

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\LoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Credential check used by Fortify's login pipeline (specification §5): local
 * passwords or LDAP bind, account status, lockout, and attempt logging.
 * Two-factor challenge and session creation remain Fortify's.
 */
final class LoginAuthenticator
{
    public function __construct(
        private readonly LoginThrottle $throttle,
        private readonly LdapAuthenticator $ldap,
        private readonly SettingsService $settings,
    ) {}

    public function __invoke(Request $request): ?User
    {
        $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        if ($this->settings->get('setup', 'completed_at') === null) {
            throw ValidationException::withMessages(['email' => __('ui.auth.setup_required')]);
        }
        $identifier = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        $user = User::query()->withoutGlobalScope('organization')
            ->where(static fn ($q) => $q->where('email', $identifier)->orWhere('username', $identifier))
            ->first();

        if ($user !== null && $user->isLocked()) {
            $this->throttle->failed($request, $identifier, $user, 'locked');
            throw ValidationException::withMessages(['email' => __('ui.auth.locked', ['minutes' => (int) ceil(now()->diffInMinutes($user->locked_until, true))])]);
        }

        $authenticated = match (true) {
            $user !== null && $user->auth_source === 'local' => $user->password !== null && Hash::check($password, $user->password),
            ($user === null || $user->auth_source === 'ldap') && (bool) $this->settings->get('ldap', 'enabled') => ($user = $this->ldap->authenticate($identifier, $password, $user)) !== null,
            default => false,
        };

        if (! $authenticated || $user === null) {
            $this->throttle->failed($request, $identifier, $user, 'bad_password');

            return null; // Fortify answers with a generic "credentials do not match"
        }
        if ($user->status !== 'active') {
            $this->throttle->failed($request, $identifier, $user, 'inactive');
            throw ValidationException::withMessages(['email' => __('ui.auth.inactive')]);
        }
        if ($user->password !== null && Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->saveQuietly();
        }

        return $user;
    }
}
