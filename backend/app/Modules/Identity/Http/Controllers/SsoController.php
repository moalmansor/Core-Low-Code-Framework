<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Infrastructure\Egress\EgressGateway;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Auth\Oidc\OidcProvider;
use App\Modules\Identity\Auth\Oidc\OidcProviderConfig;
use App\Modules\Identity\Auth\UserProvisioner;
use App\Modules\Identity\Security\LoginThrottle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * OIDC single sign-on (specification §5). Runs in the `web` middleware group
 * because the identity provider redirects the browser back here.
 */
final class SsoController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly EgressGateway $gateway,
    ) {}

    public function providers(): JsonResponse
    {
        $list = array_values(array_map(
            static fn (OidcProviderConfig $c): array => ['key' => $c->key, 'name' => $c->name[app()->getLocale()] ?? (array_values($c->name)[0] ?? $c->key)],
            array_filter(OidcProviderConfig::all($this->settings), static fn (OidcProviderConfig $c): bool => $c->enabled),
        ));

        return response()->json(['data' => $list]);
    }

    public function redirect(Request $request, string $key): RedirectResponse
    {
        $config = $this->config($key);
        if ($config === null) {
            return redirect('/login?sso_error=unknown_provider');
        }
        try {
            return $this->provider($request, $config)->redirect();
        } catch (Throwable $e) {
            report($e);

            return redirect('/login?sso_error=provider_unavailable');
        }
    }

    public function callback(Request $request, string $key, UserProvisioner $provisioner, LoginThrottle $throttle): RedirectResponse
    {
        $config = $this->config($key);
        if ($config === null) {
            return redirect('/login?sso_error=unknown_provider');
        }
        try {
            $remote = $this->provider($request, $config)->user();
        } catch (Throwable $e) {
            report($e);

            return redirect('/login?sso_error=verification_failed');
        }
        $claims = $remote->getRaw();
        $roleKeys = [];
        if ($config->roleClaim !== null) {
            foreach ((array) ($claims[$config->roleClaim] ?? []) as $value) {
                if (is_string($value) && isset($config->roleMap[$value])) {
                    $roleKeys[] = $config->roleMap[$value];
                }
            }
        }
        $user = $provisioner->resolve(
            source: 'oidc',
            subject: $config->key.'|'.$remote->getId(),
            email: $remote->getEmail(),
            name: (string) $remote->getName(),
            username: null,
            mappedRoleKeys: array_values(array_unique($roleKeys)),
            managedRoleKeys: array_values(array_unique(array_values($config->roleMap))),
            allowCreate: $config->jitProvisioning,
            linkByEmail: $config->linkByEmail,
        );
        if ($user === null || $user->status !== 'active') {
            $throttle->failed($request, (string) ($remote->getEmail() ?? $remote->getId()), $user, 'sso_rejected');

            return redirect('/login?sso_error=account_not_allowed');
        }
        $amr = array_map('strval', (array) ($claims['amr'] ?? []));
        $idpDidMfa = array_intersect($amr, ['mfa', 'otp', 'hwk', 'swk', 'fpt']) !== [];
        if ($user->hasEnabledTwoFactorAuthentication() && ! $idpDidMfa) {
            // Hand over to Fortify's two-factor challenge.
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect('/login/two-factor');
        }
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect('/');
    }

    private function config(string $key): ?OidcProviderConfig
    {
        $config = OidcProviderConfig::find($this->settings, $key);

        return $config !== null && $config->enabled ? $config : null;
    }

    private function provider(Request $request, OidcProviderConfig $config): OidcProvider
    {
        return new OidcProvider($request, $config, $this->gateway, url('/auth/sso/'.$config->key.'/callback'));
    }
}
