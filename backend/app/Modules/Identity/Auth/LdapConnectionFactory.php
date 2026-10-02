<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth;

use App\Modules\Core\Settings\SettingsService;
use LdapRecord\Connection;

/** Builds an LdapRecord connection from the `ldap` settings group. */
class LdapConnectionFactory
{
    public function __construct(private readonly SettingsService $settings) {}

    public function make(): Connection
    {
        $s = fn (string $key) => $this->settings->get('ldap', $key);

        return new Connection([
            'hosts' => array_values((array) $s('hosts')),
            'port' => (int) $s('port'),
            'base_dn' => (string) $s('base_dn'),
            'username' => $s('bind_username'),
            'password' => $s('bind_password'),
            'use_ssl' => (bool) $s('use_ssl'),
            'use_tls' => (bool) $s('use_tls'),
            'timeout' => (int) $s('timeout_seconds'),
            'options' => [LDAP_OPT_X_TLS_REQUIRE_CERT => LDAP_OPT_X_TLS_HARD],
        ]);
    }
}
