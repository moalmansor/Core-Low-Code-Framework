<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\LoginThrottle;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

/** Audits authentication events and keeps login bookkeeping (specification §4.20). */
final class AuthEventSubscriber
{
    public function __construct(
        private readonly AuditWriter $audit,
        private readonly LoginThrottle $throttle,
    ) {}

    public function onLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }
        $this->throttle->succeeded(app(Request::class), $event->user);
        $this->audit->record('auth.login', 'auth', objectType: 'user', objectId: $event->user->id, actorUserId: $event->user->id);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->record('auth.logout', 'auth', objectType: 'user', objectId: $event->user->id, actorUserId: $event->user->id);
        }
    }

    public function onTwoFactorPassed(ValidTwoFactorAuthenticationCodeProvided $event): void
    {
        if (Session::isStarted()) {
            DB::table('sessions')->where('id', Session::getId())->update(['two_factor_passed_at' => now()->format('Y-m-d H:i:s.u')]);
        }
    }

    public function onTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $this->throttle->failed(app(Request::class), (string) ($user?->email ?? 'unknown'), $user, '2fa_failed');
    }

    public function onTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->audit->record('auth.2fa_enabled', 'security', objectType: 'user', objectId: (int) $event->user->getAuthIdentifier());
    }

    public function onTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->audit->record('auth.2fa_disabled', 'security', objectType: 'user', objectId: (int) $event->user->getAuthIdentifier());
    }

    public function onRecoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        $this->audit->record('auth.recovery_code_used', 'security', objectType: 'user', objectId: (int) $event->user->getAuthIdentifier());
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'onLogin',
            Logout::class => 'onLogout',
            ValidTwoFactorAuthenticationCodeProvided::class => 'onTwoFactorPassed',
            TwoFactorAuthenticationFailed::class => 'onTwoFactorFailed',
            TwoFactorAuthenticationConfirmed::class => 'onTwoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'onTwoFactorDisabled',
            RecoveryCodeReplaced::class => 'onRecoveryCodeUsed',
        ];
    }
}
