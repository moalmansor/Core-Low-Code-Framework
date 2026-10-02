<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Oidc;

use App\Infrastructure\Egress\EgressGateway;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;

/**
 * Generic OpenID Connect provider for Socialite. All HTTP (discovery, token,
 * JWKS) goes through the egress gateway; the ID token's signature, issuer,
 * audience, expiry, and nonce are verified before any claim is trusted.
 */
final class OidcProvider extends AbstractProvider
{
    protected $scopeSeparator = ' ';

    /** @var array<string, mixed>|null */
    private ?array $discovery = null;

    public function __construct(
        Request $request,
        private readonly OidcProviderConfig $config,
        private readonly EgressGateway $gateway,
        string $redirectUrl,
    ) {
        parent::__construct($request, $config->clientId, (string) $config->clientSecret, $redirectUrl);
        $this->scopes = $config->scopes;
        $this->setHttpClient($gateway->client(15));
        $this->enablePKCE();
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase((string) $this->discovery()['authorization_endpoint'], $state);
    }

    protected function getCodeFields($state = null): array
    {
        $nonce = Str::random(40);
        $this->request->session()->put('oidc.nonce', $nonce);

        return parent::getCodeFields($state) + ['nonce' => $nonce];
    }

    protected function getTokenUrl(): string
    {
        return (string) $this->discovery()['token_endpoint'];
    }

    protected function getUserByToken($token): array
    {
        throw new RuntimeException('Claims come from the verified ID token.');
    }

    /** @param array<string, mixed> $user */
    protected function mapUserToObject(array $user): SocialiteUser
    {
        return (new SocialiteUser)->setRaw($user)->map([
            'id' => (string) $user['sub'],
            'email' => isset($user['email']) && ($user['email_verified'] ?? false) ? (string) $user['email'] : null,
            'name' => (string) ($user['name'] ?? $user['preferred_username'] ?? $user['email'] ?? $user['sub']),
        ]);
    }

    public function user(): SocialiteUser
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }
        $response = $this->getAccessTokenResponse($this->getCode());
        $idToken = (string) ($response['id_token'] ?? '');
        if ($idToken === '') {
            throw new RuntimeException('The identity provider returned no ID token.');
        }
        $claims = $this->verifyIdToken($idToken);

        return $this->mapUserToObject($claims)->setToken((string) ($response['access_token'] ?? ''));
    }

    /** @return array<string, mixed> */
    private function verifyIdToken(string $idToken): array
    {
        JWT::$leeway = 60;
        $jwks = Cache::remember('oidc:jwks:'.$this->config->key, 3600, fn (): array => $this->fetchJson((string) $this->discovery()['jwks_uri']));
        $claims = (array) JWT::decode($idToken, JWK::parseKeySet($jwks, 'RS256'));
        $audience = (array) ($claims['aud'] ?? []);
        $nonce = $this->request->session()->pull('oidc.nonce');
        if (($claims['iss'] ?? null) !== $this->discovery()['issuer']) {
            throw new RuntimeException('ID token issuer mismatch.');
        }
        if (! in_array($this->config->clientId, $audience, true)) {
            throw new RuntimeException('ID token audience mismatch.');
        }
        if (count($audience) > 1 && ($claims['azp'] ?? null) !== $this->config->clientId) {
            throw new RuntimeException('ID token authorized party mismatch.');
        }
        if (! is_string($nonce) || ! hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new RuntimeException('ID token nonce mismatch.');
        }

        return json_decode((string) json_encode($claims), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function discovery(): array
    {
        return $this->discovery ??= Cache::remember('oidc:discovery:'.$this->config->key, 3600, function (): array {
            $doc = $this->fetchJson($this->config->issuer.'/.well-known/openid-configuration');
            if (rtrim((string) ($doc['issuer'] ?? ''), '/') !== $this->config->issuer) {
                throw new RuntimeException('Discovery issuer does not match the configured issuer.');
            }
            foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
                if (empty($doc[$key])) {
                    throw new RuntimeException("Discovery document lacks {$key}.");
                }
            }

            return $doc;
        });
    }

    /** @return array<string, mixed> */
    private function fetchJson(string $url): array
    {
        $response = $this->gateway->request('GET', $url, ['headers' => ['Accept' => 'application/json']], 10, 1_048_576);
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException("Identity provider returned HTTP {$response->getStatusCode()}.");
        }

        return json_decode((string) $response->getBody(), true, 64, JSON_THROW_ON_ERROR);
    }
}
