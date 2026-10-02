<?php

declare(strict_types=1);

namespace Tests;

use App\Modules\Access\Models\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    public const PASSWORD = 'Corr3ct-Horse-Battery!';

    public const TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        // Requests look like the SPA's: same origin, so Sanctum starts the session.
        $this->withHeaders(['Origin' => 'http://localhost', 'Referer' => 'http://localhost/']);
        // The SPA bundle is built by the frontend pipeline; the shell renders without it here.
        $this->withoutVite();
    }

    /**
     * Each request of a real browser starts with fresh guards; in-process test
     * requests share the application, so forget cached guard users first.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        $this->app['auth']->forgetGuards();

        return parent::actingAs($user, $guard);
    }

    /** Mark setup as complete (most tests run against an installed system). */
    protected function completeSetup(): void
    {
        app(\App\Modules\Core\Settings\SettingsService::class)->write('setup', 'completed_at', now()->toIso8601String());
    }

    /**
     * Create an active local user with the given role keys. Users of roles that
     * require 2FA are enrolled unless told otherwise.
     *
     * @param  list<string>  $roles
     */
    protected function makeUser(array $roles = ['user'], array $attributes = [], ?bool $twoFactor = null): User
    {
        static $n = 0;
        $n++;
        $user = new User;
        $user->forceFill(array_merge([
            'name' => "Person {$n}",
            'email' => "person{$n}@example.test",
            'status' => 'active',
            'auth_source' => 'local',
            'password' => self::PASSWORD,
            'password_changed_at' => now(),
        ], $attributes))->save();
        foreach ($roles as $key) {
            $user->roles()->attach(Role::query()->where('key', $key)->value('id'), ['created_at' => now()->format('Y-m-d H:i:s.u')]);
        }
        $needs2fa = $twoFactor ?? $user->requiresTwoFactor();
        if ($needs2fa) {
            $user->forceFill([
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt(self::TOTP_SECRET),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code-1', 'recovery-code-2'])),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }
        app(\App\Modules\Access\AccessCache::class)->bump();

        return $user->fresh();
    }

    protected function superAdmin(): User
    {
        return $this->makeUser(['super_admin']);
    }

    protected function totp(string $secret = self::TOTP_SECRET): string
    {
        return (new Google2FA)->getCurrentOtp($secret);
    }
}
