<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Audit\AuditWriter;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Security\PasswordPolicy;
use App\Modules\Identity\Sessions\SessionRevoker;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

final class UpdateUserPassword implements UpdatesUserPasswords
{
    public function __construct(
        private readonly PasswordPolicy $policy,
        private readonly AuditWriter $audit,
        private readonly SessionRevoker $sessions,
    ) {}

    /** @param array<string, string> $input */
    public function update(User $user, array $input): void
    {
        abort_if($user->auth_source !== 'local', 422, __('ui.auth.external_account'));
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->policy->rules($user),
        ])->validateWithBag('updatePassword');
        $this->policy->apply($user, $input['password']);
        $this->sessions->revokeAllFor($user->id, exceptCurrent: true);
        $this->audit->record('auth.password_changed', 'auth', objectType: 'user', objectId: $user->id);
    }
}
