<?php

declare(strict_types=1);

namespace App\Modules\Identity\Security;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Step-up confirmation for dangerous operations such as granting a dangerous
 * permission or a hard deny (architecture §10.4 `permissions.is_dangerous`):
 * a fresh TOTP code (or a recovery code) must accompany the request.
 */
final class StepUp
{
    public function __construct(private readonly TwoFactorAuthenticationProvider $totp) {}

    public function require(Request $request, User $user): void
    {
        $code = (string) $request->input('confirmation_code', '');
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['confirmation_code' => __('ui.stepup.two_factor_required')]);
        }
        if ($code !== '' && $this->totp->verify(decrypt((string) $user->two_factor_secret), $code)) {
            return;
        }
        $recovery = collect($user->recoveryCodes())->first(static fn (string $c): bool => hash_equals($c, $code));
        if ($code !== '' && $recovery !== null) {
            $user->replaceRecoveryCode($recovery);

            return;
        }
        throw ValidationException::withMessages(['confirmation_code' => __('ui.stepup.invalid_code')]);
    }
}
