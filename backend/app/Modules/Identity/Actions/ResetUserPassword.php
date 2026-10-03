<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\PasswordPolicy;
use App\Modules\Identity\Sessions\SessionRevoker;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

final class ResetUserPassword implements ResetsUserPasswords
{
    public function __construct(
        private readonly PasswordPolicy $policy,
        private readonly AuditWriter $audit,
        private readonly SessionRevoker $sessions,
    ) {}

    /** @param array<string, string> $input */
    public function reset(User $user, array $input): void
    {
        abort_if($user->auth_source !== 'local', 422, __('ui.auth.external_account'));
        Validator::make($input, ['password' => $this->policy->rules($user)])->validate();
        $this->policy->apply($user, $input['password']);
        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->saveQuietly();
        $this->sessions->revokeAllFor($user->id);
        $this->audit->record('auth.password_reset', 'auth', objectType: 'user', objectId: $user->id, actorUserId: $user->id);
    }
}
