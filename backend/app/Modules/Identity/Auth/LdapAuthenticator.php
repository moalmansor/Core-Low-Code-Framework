<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth;

use App\Modules\Core\Settings\SettingsService;
use App\Modules\Identity\Models\User;
use Throwable;

/**
 * LDAP login (specification §5): find the entry by the configured login
 * attribute (values are escaped by the query builder), bind as that entry with
 * the supplied password, then map directory groups to roles.
 */
final class LdapAuthenticator
{
    public function __construct(
        private readonly LdapConnectionFactory $connections,
        private readonly SettingsService $settings,
        private readonly UserProvisioner $provisioner,
    ) {}

    public function authenticate(string $identifier, string $password, ?User $existing): ?User
    {
        if ($password === '') {
            return null; // an empty password would perform an anonymous bind
        }
        $s = fn (string $key) => $this->settings->get('ldap', $key);
        try {
            $connection = $this->connections->make();
            $connection->connect();
            $entry = $connection->query()->where((string) $s('login_attribute'), '=', $identifier)->first();
            if (! is_array($entry) || empty($entry['dn'])) {
                return null;
            }
            $dn = is_array($entry['dn']) ? (string) $entry['dn'][0] : (string) $entry['dn'];
            if (! $connection->auth()->attempt($dn, $password)) {
                return null;
            }
        } catch (Throwable $e) {
            report($e);

            return null;
        }
        $email = $this->first($entry, (string) $s('email_attribute'));
        $name = $this->first($entry, (string) $s('name_attribute')) ?? $email ?? $identifier;
        $groups = array_map('strtolower', $this->all($entry, 'memberof'));
        /** @var array<string, string> $map */
        $map = array_change_key_case((array) $s('group_role_map'), CASE_LOWER);
        $roleKeys = array_values(array_unique(array_filter(array_map(static fn (string $g): ?string => $map[$g] ?? null, $groups))));

        return $this->provisioner->resolve(
            source: 'ldap',
            subject: strtolower($dn),
            email: $email,
            name: $name,
            username: $identifier,
            mappedRoleKeys: $roleKeys,
            managedRoleKeys: array_values(array_unique(array_values($map))),
            allowCreate: (bool) $s('jit_provisioning'),
            existing: $existing,
        );
    }

    /** @param array<string, mixed> $entry */
    private function first(array $entry, string $attribute): ?string
    {
        $values = $this->all($entry, $attribute);

        return $values[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    private function all(array $entry, string $attribute): array
    {
        $value = $entry[strtolower($attribute)] ?? $entry[$attribute] ?? [];
        $values = is_array($value) ? $value : [$value];
        unset($values['count']);

        return array_values(array_map('strval', array_filter($values, 'is_scalar')));
    }
}
