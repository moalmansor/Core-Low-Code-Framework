<?php

declare(strict_types=1);

namespace App\Modules\Identity\Security;

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\PasswordHistory;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Configurable password policy (specification §2, §5): length and character
 * classes, history, a bundled common-password list, and no reuse of the
 * e-mail or name. Every rule is read from the `security` settings group.
 */
final class PasswordPolicy
{
    /** @var array<string, true>|null */
    private static ?array $common = null;

    public function __construct(private readonly SettingsService $settings) {}

    /** @return list<mixed> validation rules for a new password */
    public function rules(?User $user = null, ?string $email = null, ?string $name = null): array
    {
        $s = fn (string $key) => $this->settings->get('security', $key);
        $rule = Password::min((int) $s('password_min_length'))->max(128);
        if ($s('password_require_uppercase') || $s('password_require_lowercase')) {
            $rule = $rule->letters();
        }
        if ($s('password_require_uppercase') && $s('password_require_lowercase')) {
            $rule = $rule->mixedCase();
        }
        if ($s('password_require_digit')) {
            $rule = $rule->numbers();
        }
        if ($s('password_require_symbol')) {
            $rule = $rule->symbols();
        }
        $email ??= $user?->email;
        $name ??= $user?->name;

        return [
            'required', 'string', 'confirmed', $rule,
            function (string $attribute, mixed $value, Closure $fail) use ($email, $name): void {
                $password = mb_strtolower((string) $value);
                if ($this->isCommon($password)) {
                    $fail(__('ui.password.common'));
                }
                $local = $email !== null ? mb_strtolower((string) strstr($email, '@', true)) : '';
                if ($local !== '' && mb_strlen($local) >= 3 && str_contains($password, $local)) {
                    $fail(__('ui.password.contains_identity'));
                }
                foreach (preg_split('/\s+/u', mb_strtolower((string) $name)) ?: [] as $part) {
                    if (mb_strlen($part) >= 3 && str_contains($password, $part)) {
                        $fail(__('ui.password.contains_identity'));
                        break;
                    }
                }
            },
            function (string $attribute, mixed $value, Closure $fail) use ($user): void {
                if ($user !== null && $this->inHistory($user, (string) $value)) {
                    $fail(__('ui.password.reused', ['count' => (int) $this->settings->get('security', 'password_history')]));
                }
            },
        ];
    }

    public function isCommon(string $password): bool
    {
        if (self::$common === null) {
            $lines = file(resource_path('security/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$common = array_fill_keys($lines, true);
        }

        return isset(self::$common[mb_strtolower($password)]);
    }

    public function inHistory(User $user, string $password): bool
    {
        $depth = (int) $this->settings->get('security', 'password_history');
        if ($depth === 0) {
            return false;
        }
        if ($user->password !== null && Hash::check($password, $user->password)) {
            return true;
        }
        $hashes = PasswordHistory::query()->where('user_id', $user->id)->orderByDesc('id')->limit($depth)->pluck('password_hash');
        foreach ($hashes as $hash) {
            if (Hash::check($password, (string) $hash)) {
                return true;
            }
        }

        return false;
    }

    /** Store the new password, remember the old hash, and stamp the change. */
    public function apply(User $user, string $password): void
    {
        if ($user->password !== null) {
            PasswordHistory::query()->create(['user_id' => $user->id, 'password_hash' => $user->password]);
        }
        $user->forceFill(['password' => $password, 'password_changed_at' => now()])->save();
        $keep = max(1, (int) $this->settings->get('security', 'password_history'));
        $stale = PasswordHistory::query()->where('user_id', $user->id)->orderByDesc('id')->skip($keep)->take(PHP_INT_MAX)->pluck('id');
        PasswordHistory::query()->whereIn('id', $stale)->delete();
    }

    public function isExpired(User $user): bool
    {
        $days = (int) $this->settings->get('security', 'password_expiry_days');

        return $days > 0 && $user->auth_source === 'local' && $user->password_changed_at !== null
            && $user->password_changed_at->lt(now()->subDays($days));
    }
}
