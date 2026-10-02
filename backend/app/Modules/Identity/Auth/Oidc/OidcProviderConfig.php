<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth\Oidc;

use App\Modules\Core\Settings\SettingsService;

/** One OIDC provider as configured in the `sso` settings group. */
final readonly class OidcProviderConfig
{
    /**
     * @param  array<string, string>  $name  locale => display name
     * @param  list<string>  $scopes
     * @param  array<string, string>  $roleMap  claim value => role key
     */
    public function __construct(
        public string $key,
        public array $name,
        public string $issuer,
        public string $clientId,
        public ?string $clientSecret,
        public array $scopes,
        public ?string $roleClaim,
        public array $roleMap,
        public bool $jitProvisioning,
        public bool $linkByEmail,
        public bool $enabled,
    ) {}

    public static function find(SettingsService $settings, string $key): ?self
    {
        foreach (self::all($settings) as $config) {
            if ($config->key === $key) {
                return $config;
            }
        }

        return null;
    }

    /** @return list<self> */
    public static function all(SettingsService $settings): array
    {
        /** @var array<string, string> $secrets */
        $secrets = (array) ($settings->get('sso', 'client_secrets') ?? []);
        $out = [];
        foreach ((array) $settings->get('sso', 'providers') as $p) {
            if (! is_array($p) || empty($p['key'])) {
                continue;
            }
            $out[] = new self(
                key: (string) $p['key'],
                name: (array) ($p['name'] ?? []),
                issuer: rtrim((string) ($p['issuer'] ?? ''), '/'),
                clientId: (string) ($p['client_id'] ?? ''),
                clientSecret: $secrets[$p['key']] ?? null,
                scopes: array_values(array_unique(array_merge(['openid', 'email', 'profile'], (array) ($p['scopes'] ?? [])))),
                roleClaim: isset($p['role_claim']) ? (string) $p['role_claim'] : null,
                roleMap: (array) ($p['role_map'] ?? []),
                jitProvisioning: (bool) ($p['jit_provisioning'] ?? false),
                linkByEmail: (bool) ($p['link_by_email'] ?? false),
                enabled: (bool) ($p['enabled'] ?? true),
            );
        }

        return $out;
    }
}
