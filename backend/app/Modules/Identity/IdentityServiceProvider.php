<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Actions\ResetUserPassword;
use App\Modules\Identity\Actions\UpdateUserPassword;
use App\Modules\Identity\Auth\LoginAuthenticator;
use App\Modules\Identity\Auth\TenantAwareUserProvider;
use App\Modules\Identity\Listeners\AuthEventSubscriber;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Sessions\SessionHandler;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // A reset request for an unknown address gets the same answer as a
        // known one, so the form cannot be used to discover accounts.
        $this->app->bind(\Laravel\Fortify\Actions\DisableTwoFactorAuthentication::class, \App\Modules\Identity\Actions\DisableTwoFactorWhenAllowed::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, static fn () => new SuccessfulPasswordResetLinkRequestResponse(PasswordBroker::RESET_LINK_SENT));
    }

    public function boot(): void
    {
        Auth::provider('lcf-eloquent', static fn ($app, array $config) => new TenantAwareUserProvider($app['hash'], $config['model']));
        Fortify::authenticateUsing(fn (Request $request): ?User => app(LoginAuthenticator::class)($request));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);

        ResetPassword::createUrlUsing(static fn (User $user, string $token): string => rtrim((string) config('app.url'), '/')
            .'/reset-password/'.$token.'?email='.rawurlencode($user->email));

        RateLimiter::for('login', static fn (Request $request): array => [
            Limit::perMinute(5)->by(Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip()),
            Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('two-factor', static fn (Request $request): Limit => Limit::perMinute(5)->by((string) $request->session()->get('login.id').'|'.$request->ip()));

        Session::extend('lcf-database', static fn ($app) => new SessionHandler(
            $app['db']->connection($app['config']['session.connection']),
            'sessions',
            (int) $app['config']['session.lifetime'],
            $app,
        ));

        Event::subscribe(AuthEventSubscriber::class);
    }
}
