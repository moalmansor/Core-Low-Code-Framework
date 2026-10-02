<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Modules\Identity\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

/**
 * Two-factor authentication is mandatory for roles that require it
 * (specification §2, §5): their holders cannot switch it off themselves. An
 * administrator can still reset it (forcing re-enrollment at next sign-in).
 */
final class DisableTwoFactorWhenAllowed extends DisableTwoFactorAuthentication
{
    public function __invoke($user)
    {
        if ($user instanceof User && $user->requiresTwoFactor()) {
            throw ValidationException::withMessages(['two_factor' => __('ui.auth.two_factor_mandatory')]);
        }
        parent::__invoke($user);
    }
}
