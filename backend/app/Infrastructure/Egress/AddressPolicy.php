<?php

declare(strict_types=1);

namespace App\Infrastructure\Egress;

/**
 * Blocked address ranges for outbound requests (architecture §7.4,
 * specification §4.15): private, loopback, link-local, shared, documentation,
 * multicast, reserved, and cloud metadata ranges, in IPv4 and IPv6. IPv4-mapped
 * and NAT64 IPv6 addresses are checked as their embedded IPv4 address.
 */
final class AddressPolicy
{
    /** @var list<string> */
    private const BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16',
        '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '255.255.255.255/32',
        '::/128', '::1/128', '64:ff9b:1::/48', '2002::/16', '100::/64', '2001::/23', '2001:db8::/32',
        'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
        // Cloud metadata endpoints (covered above, listed for clarity)
        '169.254.169.254/32', 'fd00:ec2::254/128',
    ];

    public function isBlocked(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return true; // unparseable addresses are never allowed
        }
        if (strlen($packed) === 16) {
            $embedded = $this->embeddedIpv4($packed);
            if ($embedded !== null) {
                return $this->isBlocked($embedded);
            }
        }
        foreach (self::BLOCKED as $cidr) {
            if ($this->inRange($packed, $cidr)) {
                return true;
            }
        }

        return false;
    }

    /** IPv4-mapped (::ffff:a.b.c.d), IPv4-compatible (::a.b.c.d) and NAT64 (64:ff9b::/96). */
    private function embeddedIpv4(string $packed): ?string
    {
        $prefix12 = substr($packed, 0, 12);
        $mapped = str_repeat("\0", 10)."\xff\xff";
        $nat64 = "\x00\x64\xff\x9b".str_repeat("\0", 8);
        $compatible = str_repeat("\0", 12);
        if ($prefix12 === $mapped || $prefix12 === $nat64 || ($prefix12 === $compatible && substr($packed, 12) !== "\0\0\0\0" && substr($packed, 12) !== "\0\0\0\1")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return null;
    }

    private function inRange(string $packed, string $cidr): bool
    {
        [$network, $bits] = explode('/', $cidr);
        $net = inet_pton($network);
        if ($net === false || strlen($net) !== strlen($packed)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (strncmp($packed, $net, $bytes) !== 0) {
            return false;
        }
        $remainder = $bits % 8;
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($net[$bytes]) & $mask);
    }
}
