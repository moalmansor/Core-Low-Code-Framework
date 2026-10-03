<?php

declare(strict_types=1);

use App\Infrastructure\Egress\AddressPolicy;
use App\Infrastructure\Egress\EgressGateway;
use App\Infrastructure\Egress\HostResolver;
use App\Modules\Access\Models\Role;
use App\Modules\Core\Correlation\CorrelationId;
use App\Modules\Core\Models\EgressAllowlistEntry;
use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Auth\LdapAuthenticator;
use App\Modules\Identity\Auth\LdapConnectionFactory;
use App\Modules\Identity\Models\User;
use Firebase\JWT\JWT;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Testing\TestResponse;
use LdapRecord\Connection;

final class SsoTestResolver implements HostResolver
{
    public function resolve(string $host): array
    {
        return ['93.184.216.34'];
    }
}

beforeEach(function () {
    $this->completeSetup();
    EgressAllowlistEntry::query()->create(['host_pattern' => 'idp.example.test', 'ports' => [443], 'allow_http' => false, 'is_active' => true]);
    app(SettingsService::class)->write('sso', 'providers', [[
        'key' => 'corp', 'name' => ['en' => 'Corp'], 'issuer' => 'https://idp.example.test', 'client_id' => 'lcf-client',
        'role_claim' => 'groups', 'role_map' => ['lcf-admins' => 'admin'], 'jit_provisioning' => true, 'enabled' => true,
    ]]);
    app(SettingsService::class)->write('sso', 'client_secrets', ['corp' => 'client-secret']);

    $this->rsa = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $details = openssl_pkey_get_details($this->rsa);
    $b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    $this->jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'k1', 'use' => 'sig', 'alg' => 'RS256', 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]];
    $this->discovery = ['issuer' => 'https://idp.example.test', 'authorization_endpoint' => 'https://idp.example.test/authorize',
        'token_endpoint' => 'https://idp.example.test/token', 'jwks_uri' => 'https://idp.example.test/jwks'];
});

/** One mock identity provider per test; responses are appended as the flow needs them. */
function useIdp(array $responses): void
{
    if (! app()->bound('test.idp')) {
        $mock = new MockHandler;
        app()->instance('test.idp', $mock);
        app()->instance(EgressGateway::class, new EgressGateway(new AddressPolicy, new SsoTestResolver, app(CorrelationId::class), HandlerStack::create($mock)));
    }
    $mock = app('test.idp');
    $mock->reset();
    $mock->append(...$responses);
}

function idToken(array $claims, $key): string
{
    openssl_pkey_export($key, $pem);

    return JWT::encode($claims + ['iss' => 'https://idp.example.test', 'aud' => 'lcf-client', 'iat' => time(), 'exp' => time() + 300], $pem, 'RS256', 'k1');
}

function ssoRoundTrip(array $claims, $key, array $jwks, array $discovery): TestResponse
{
    useIdp([new Response(200, [], json_encode($discovery))]);
    $redirect = test()->get('/auth/sso/corp/redirect')->assertRedirect();
    parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query)->toHaveKeys(['state', 'nonce', 'code_challenge']);
    $token = idToken($claims + ['nonce' => $query['nonce']], $key);
    useIdp([new Response(200, [], json_encode(['access_token' => 'at', 'id_token' => $token])), new Response(200, [], json_encode($jwks))]);

    return test()->get('/auth/sso/corp/callback?state='.$query['state'].'&code=abc');
}

it('signs a user in through OpenID Connect with PKCE, nonce and a verified ID token, creating the account', function () {
    ssoRoundTrip(['sub' => 'u-1', 'email' => 'nora@example.test', 'email_verified' => true, 'name' => 'Nora', 'groups' => ['lcf-admins']], $this->rsa, $this->jwks, $this->discovery)
        ->assertRedirect('/');
    $user = User::query()->where('email', 'nora@example.test')->firstOrFail();
    expect($user->auth_source)->toBe('oidc')->and($user->roleKeys())->toBe(['admin']);
    $this->assertAuthenticatedAs($user, 'web');
});

it('rejects ID tokens signed by another key', function () {
    $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    ssoRoundTrip(['sub' => 'u-2', 'email' => 'x@example.test', 'email_verified' => true], $other, $this->jwks, $this->discovery)
        ->assertRedirect('/login?sso_error=verification_failed');
    $this->assertGuest('web');
});

it('never lets an identity provider take over a local account with the same e-mail', function () {
    $local = $this->makeUser(attributes: ['email' => 'owner@example.test']);
    ssoRoundTrip(['sub' => 'evil', 'email' => 'owner@example.test', 'email_verified' => true], $this->rsa, $this->jwks, $this->discovery)
        ->assertRedirect('/login?sso_error=account_not_allowed');
    $this->assertGuest('web');
    expect($local->fresh()->auth_source)->toBe('local');
});

it('hands users with enrolled 2FA to the challenge unless the provider did MFA', function () {
    ssoRoundTrip(['sub' => 'u-3', 'email' => 'mfa@example.test', 'email_verified' => true], $this->rsa, $this->jwks, $this->discovery)->assertRedirect('/');
    $user = User::query()->where('email', 'mfa@example.test')->firstOrFail();
    $user->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
    $this->post('/api/v1/auth/logout');
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    ssoRoundTrip(['sub' => 'u-3', 'email' => 'mfa@example.test', 'email_verified' => true], $this->rsa, $this->jwks, $this->discovery)->assertRedirect('/login/two-factor');
    $this->assertGuest('web');
});

it('authenticates LDAP users with a bind, maps groups to roles, and provisions them', function () {
    app(SettingsService::class)->write('ldap', 'enabled', true);
    app(SettingsService::class)->write('ldap', 'jit_provisioning', true);
    app(SettingsService::class)->write('ldap', 'group_role_map', ['cn=admins,dc=corp' => 'admin']);
    $entry = ['dn' => 'CN=Omar,DC=corp', 'mail' => ['omar@corp.test'], 'cn' => ['Omar'], 'memberof' => ['CN=Admins,DC=corp']];

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('connect')->andReturnNull();
    $connection->shouldReceive('query->where->first')->andReturn($entry);
    $connection->shouldReceive('auth->attempt')->andReturnUsing(static fn (string $dn, string $password): bool => $password === 'right-password');
    $factory = Mockery::mock(LdapConnectionFactory::class);
    $factory->shouldReceive('make')->andReturn($connection);
    $this->app->instance(LdapConnectionFactory::class, $factory);

    expect(app(LdapAuthenticator::class)->authenticate('omar@corp.test', 'wrong', null))->toBeNull();
    expect(app(LdapAuthenticator::class)->authenticate('omar@corp.test', '', null))->toBeNull();

    $this->postJson('/api/v1/auth/login', ['email' => 'omar@corp.test', 'password' => 'right-password'])->assertOk();
    $user = User::query()->where('email', 'omar@corp.test')->firstOrFail();
    expect($user->auth_source)->toBe('ldap')->and($user->roleKeys())->toBe(['admin'])->and($user->password)->toBeNull();
    expect(Role::query()->where('key', 'admin')->value('requires_2fa'))->toBeTruthy();
});
